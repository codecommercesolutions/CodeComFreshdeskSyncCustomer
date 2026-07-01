<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Service;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CustomerSyncService
{
    public const CONSENT_CUSTOM_FIELD = 'freshdesk_sync_contact_consent';
    public const PROCESSED_AT_CUSTOM_FIELD = 'freshdesk_sync_customer_processed_at';
    public const SYNCED_AT_CUSTOM_FIELD = 'freshdesk_sync_customer_synced_at';
    public const LAST_RESULT_CUSTOM_FIELD = 'freshdesk_sync_customer_last_result';

    private const OPTIN_SYNC_MODE_DIRECT_CHECKBOX = 'direct_checkbox';
    private const OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN = 'shopware_double_optin';
    private const TRANSFER_MODE_ALL = 'all';
    private const TRANSFER_MODE_OPTIN_ONLY = 'optin_only';

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly FreshdeskService $freshdeskService,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $logEntryRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{success: bool, skipped?: bool, id?: int|null, created?: bool, message?: string, optin?: bool, tags?: list<string>}
     */
    public function syncCustomer(CustomerEntity $customer, Context $context, bool $markProcessed = false): array
    {
        $customer = $this->loadCustomer($customer, $context);
        $salesChannelId = $customer->getSalesChannelId();

        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enabled', $salesChannelId)) {
            return $this->finish($customer, $context, [
                'success' => true,
                'skipped' => true,
                'message' => 'Freshdesk integration disabled for customer sales channel',
            ], $markProcessed);
        }

        $optin = $this->getRegistrationOptin($customer, $salesChannelId);
        if ($this->shouldSkipWithoutOptin($salesChannelId, $optin)) {
            return $this->finish($customer, $context, [
                'success' => true,
                'skipped' => true,
                'message' => 'Customer skipped because transfer mode is Optin only',
                'optin' => $optin,
                'tags' => $this->getFreshdeskTags($customer, $salesChannelId),
            ], $markProcessed);
        }

        $email = trim((string) $customer->getEmail());
        if ($email === '') {
            return $this->finish($customer, $context, [
                'success' => false,
                'message' => 'Customer email is empty',
                'optin' => $optin,
            ], $markProcessed);
        }

        $tags = $this->getFreshdeskTags($customer, $salesChannelId);
        $result = $this->freshdeskService->createOrUpdateRegistrationContact(
            $email,
            $salesChannelId,
            $this->buildCustomerName($customer),
            $customer->getDefaultBillingAddress()?->getPhoneNumber(),
            $this->buildCustomerAddress($customer),
            $customer->getLanguage()?->getLocale()?->getCode(),
            $optin,
            $tags
        );

        $result['optin'] = $optin;
        $result['tags'] = $tags;

        $this->logEntryRepository->create([
            [
                'message' => 'Freshdesk Sync Result',
                'level' => ($result['success'] ?? false) ? Logger::INFO : Logger::WARNING,
                'channel' => 'Freshdesk Sync',
                'context' => [
                    'result' => $result,
                    'customerId' => $customer->getId(),
                    'salesChannelId' => $salesChannelId,
                ],
            ],
        ], $context);

        if (!($result['success'] ?? false)) {
            $this->logger->warning('Freshdesk customer sync failed', [
                'customerId' => $customer->getId(),
                'salesChannelId' => $salesChannelId,
                'optin' => $optin,
                'tags' => $tags,
                'message' => $result['message'] ?? 'unknown error',
            ]);
        }

        return $this->finish($customer, $context, $result, $markProcessed);
    }

    /**
     * @return array{success: bool, skipped?: bool, id?: int|null, created?: bool, message?: string, optin?: bool, tags?: list<string>}
     */
    public function syncCustomerById(string $customerId, Context $context, ?bool $optin = null, bool $markProcessed = false): array
    {
        $customer = $this->loadCustomerById($customerId, $context);
        if (!$customer instanceof CustomerEntity) {
            return ['success' => false, 'message' => 'Customer not found'];
        }

        if ($optin !== null) {
            $this->updateCustomerCustomFields($customer, $context, [
                self::CONSENT_CUSTOM_FIELD => $optin,
            ]);
            $customer = $this->loadCustomerById($customerId, $context) ?? $customer;
        }

        return $this->syncCustomer($customer, $context, $markProcessed);
    }

    /**
     * @return array{success: bool, message: string, optin?: bool}
     */
    public function updateCustomerOptin(string $customerId, bool $optin, Context $context): array
    {
        $customer = $this->loadCustomerById($customerId, $context);
        if (!$customer instanceof CustomerEntity) {
            return ['success' => false, 'message' => 'Customer not found'];
        }

        $this->updateCustomerCustomFields($customer, $context, [
            self::CONSENT_CUSTOM_FIELD => $optin,
        ]);

        return [
            'success' => true,
            'message' => 'Customer Optin saved',
            'optin' => $optin,
        ];
    }

    /**
     * @return array{processed: int, synced: int, skipped: int, failed: int, remaining: int, results: list<array<string, mixed>>}
     */
    public function syncCustomerBatch(Context $context, int $limit, bool $onlyUnprocessed, bool $markProcessed): array
    {
        $limit = max(1, $limit);
        $criteria = $this->createCustomerCriteria();
        $criteria->setLimit($limit);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        if ($onlyUnprocessed) {
            $criteria->addFilter(new EqualsFilter('customFields.' . self::PROCESSED_AT_CUSTOM_FIELD, null));
        }

        $customers = $this->customerRepository->search($criteria, $context);

        $summary = [
            'processed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'remaining' => 0,
            'results' => [],
        ];

        foreach ($customers as $customer) {
            if (!$customer instanceof CustomerEntity) {
                continue;
            }

            $result = $this->syncCustomer($customer, $context, $markProcessed);
            $summary['processed']++;
            $summary['results'][] = [
                'customerId' => $customer->getId(),
                'email' => $customer->getEmail(),
                'result' => $result,
            ];

            if (!($result['success'] ?? false)) {
                $summary['failed']++;
                continue;
            }

            if (($result['skipped'] ?? false) === true) {
                $summary['skipped']++;
                continue;
            }

            $summary['synced']++;
        }

        if ($onlyUnprocessed) {
            $remainingCriteria = new Criteria();
            $remainingCriteria->addFilter(new EqualsFilter('customFields.' . self::PROCESSED_AT_CUSTOM_FIELD, null));
            $summary['remaining'] = $this->customerRepository->searchIds($remainingCriteria, $context)->getTotal();
        }

        return $summary;
    }

    /**
     * @param array{processed: int, synced: int, skipped: int, failed: int, remaining: int, results?: list<array<string, mixed>>} $summary
     */
    public function updateCronStatus(array $summary): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $status = match (true) {
            $summary['failed'] > 0 => 'failed',
            $summary['remaining'] > 0 => 'in_progress',
            default => 'completed',
        };

        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncStatus', $status);
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncRunAt', $now);
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncProcessedCount', $summary['processed']);
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncSyncedCount', $summary['synced']);
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncSkippedCount', $summary['skipped']);
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncFailedCount', $summary['failed']);
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.remainingCustomerSyncCount', $summary['remaining']);
        $this->incrementConfigInt('totalCustomerSyncProcessedCount', $summary['processed']);
        $this->incrementConfigInt('totalCustomerSyncSyncedCount', $summary['synced']);
        $this->incrementConfigInt('totalCustomerSyncSkippedCount', $summary['skipped']);
        $this->incrementConfigInt('totalCustomerSyncFailedCount', $summary['failed']);

        if ($status === 'completed') {
            $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastSuccessfulCustomerSyncAt', $now);
        }
    }

    public function markCronDisabled(): void
    {
        $this->systemConfigService->set('CodeComFreshdeskSyncCustomer.config.lastCustomerSyncStatus', 'disabled');
    }

    public function getCronBatchSize(?string $salesChannelId = null): int
    {
        return $this->getPositiveConfigInt('cronCustomerBatchSize', 50, $salesChannelId);
    }

    public function getCliBatchSize(?string $salesChannelId = null): int
    {
        return $this->getPositiveConfigInt('cliCustomerBatchSize', 50, $salesChannelId);
    }

    public function getRegistrationOptin(CustomerEntity $customer, string $salesChannelId): bool
    {
        if ($this->getOptinSyncMode($salesChannelId) === self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN) {
            return $this->isConfirmedDoubleOptInRegistration($customer);
        }

        return $this->hasFreshdeskConsent($customer);
    }

    public function hasFreshdeskConsent(CustomerEntity $customer): bool
    {
        $customFields = $customer->getCustomFields() ?? [];
        if (!is_array($customFields)) {
            return false;
        }

        return (bool) ($customFields[self::CONSENT_CUSTOM_FIELD] ?? false);
    }

    private function finish(CustomerEntity $customer, Context $context, array $result, bool $markProcessed): array
    {
        if ($markProcessed && (($result['success'] ?? false) === true)) {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $fields = [
                self::PROCESSED_AT_CUSTOM_FIELD => $now,
                self::LAST_RESULT_CUSTOM_FIELD => ($result['skipped'] ?? false) ? 'skipped' : 'synced',
            ];

            if (($result['skipped'] ?? false) !== true) {
                $fields[self::SYNCED_AT_CUSTOM_FIELD] = $now;
            }

            $this->updateCustomerCustomFields($customer, $context, $fields);
        }

        return $result;
    }

    private function shouldSkipWithoutOptin(string $salesChannelId, bool $optin): bool
    {
        return $this->getCustomerTransferMode($salesChannelId) === self::TRANSFER_MODE_OPTIN_ONLY && !$optin;
    }

    private function getCustomerTransferMode(string $salesChannelId): string
    {
        $mode = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.customerTransferMode', $salesChannelId);

        return $mode === self::TRANSFER_MODE_OPTIN_ONLY ? self::TRANSFER_MODE_OPTIN_ONLY : self::TRANSFER_MODE_ALL;
    }

    private function getOptinSyncMode(string $salesChannelId): string
    {
        $mode = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.optinSyncMode', $salesChannelId);

        return $mode === self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN
            ? self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN
            : self::OPTIN_SYNC_MODE_DIRECT_CHECKBOX;
    }

    private function isConfirmedDoubleOptInRegistration(CustomerEntity $customer): bool
    {
        return $customer->getDoubleOptInRegistration() && $customer->getDoubleOptInConfirmDate() !== null;
    }

    /**
     * @return list<string>
     */
    private function getFreshdeskTags(CustomerEntity $customer, string $salesChannelId): array
    {
        $tagNames = [];
        $tags = $customer->getTags();
        if ($tags !== null) {
            foreach ($tags as $tag) {
                $tagName = trim((string) $tag->getName());
                if ($tagName !== '') {
                    $tagNames[] = $tagName;
                }
            }
        }

        return $tagNames !== []
            ? array_values(array_unique($tagNames))
            : [$this->getConfigString('contactTag', 'Webshop', $salesChannelId)];
    }

    private function buildCustomerName(CustomerEntity $customer): ?string
    {
        $name = trim(trim((string) $customer->getFirstName()) . ' ' . trim((string) $customer->getLastName()));

        return $name !== '' ? $name : null;
    }

    private function buildCustomerAddress(CustomerEntity $customer): ?string
    {
        $address = $customer->getDefaultBillingAddress();
        if ($address === null) {
            return null;
        }

        $country = $address->getCountry();
        $countryName = $country?->getTranslated()['name']
            ?? $country?->getName()
            ?? $country?->getIso();

        $parts = array_filter([
            trim($address->getStreet()),
            trim((string) $address->getAdditionalAddressLine1()),
            trim((string) $address->getAdditionalAddressLine2()),
            trim($address->getZipcode() ?? ''),
            trim($address->getCity()),
            trim((string) $countryName),
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function loadCustomer(CustomerEntity $customer, Context $context): CustomerEntity
    {
        return $this->loadCustomerById($customer->getId(), $context) ?? $customer;
    }

    private function loadCustomerById(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = $this->createCustomerCriteria([$customerId]);

        $customer = $this->customerRepository->search($criteria, $context)->first();

        return $customer instanceof CustomerEntity ? $customer : null;
    }

    /**
     * @param list<string> $ids
     */
    private function createCustomerCriteria(array $ids = []): Criteria
    {
        $criteria = $ids !== [] ? new Criteria($ids) : new Criteria();
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('tags');

        return $criteria;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateCustomerCustomFields(CustomerEntity $customer, Context $context, array $fields): void
    {
        $customFields = $customer->getCustomFields() ?? [];
        if (!is_array($customFields)) {
            $customFields = [];
        }

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'customFields' => array_merge($customFields, $fields),
            ],
        ], $context);
    }

    private function getConfigString(string $name, string $fallback, ?string $salesChannelId = null): string
    {
        $value = trim($this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.' . $name, $salesChannelId));

        return $value !== '' ? $value : $fallback;
    }

    private function getPositiveConfigInt(string $name, int $fallback, ?string $salesChannelId = null): int
    {
        $value = $this->systemConfigService->getInt('CodeComFreshdeskSyncCustomer.config.' . $name, $salesChannelId);

        return $value > 0 ? $value : $fallback;
    }

    private function incrementConfigInt(string $name, int $increment): void
    {
        if ($increment <= 0) {
            return;
        }

        $key = 'CodeComFreshdeskSyncCustomer.config.' . $name;
        $this->systemConfigService->set($key, $this->systemConfigService->getInt($key) + $increment);
    }
}
