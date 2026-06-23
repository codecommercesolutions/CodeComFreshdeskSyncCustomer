<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Subscriber;

use CodeCom\FreshdeskSyncCustomer\Service\FreshdeskService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerRegistrationSubscriber implements EventSubscriberInterface
{
    private const CONSENT_FIELD = 'freshdeskSyncContactConsent';

    private const CONSENT_CUSTOM_FIELD = 'freshdesk_sync_contact_consent';

    private const OPTIN_SYNC_MODE_DIRECT_CHECKBOX = 'direct_checkbox';

    private const OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN = 'shopware_double_optin';

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly FreshdeskService $freshdeskService,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::MAPPING_REGISTER_CUSTOMER => 'mapRegistrationConsent',
            CustomerDoubleOptInRegistrationEvent::class => 'onCustomerDoubleOptInRegistration',
            CustomerRegisterEvent::class => 'onCustomerRegister',
            GuestCustomerRegisterEvent::class => 'onCustomerRegister',
        ];
    }

    public function mapRegistrationConsent(DataMappingEvent $event): void
    {
        $input = $event->getInput();
        if (!$input->has(self::CONSENT_FIELD)) {
            return;
        }

        $output = $event->getOutput();
        $customFields = $output['customFields'] ?? [];

        if (!is_array($customFields)) {
            $customFields = [];
        }

        $customFields[self::CONSENT_CUSTOM_FIELD] = $input->getBoolean(self::CONSENT_FIELD);
        $output['customFields'] = $customFields;

        $event->setOutput($output);
    }

    public function onCustomerRegister(CustomerRegisterEvent|GuestCustomerRegisterEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();

        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enabled', $salesChannelId)) {
            return;
        }

        $customer = $this->loadCustomer($event->getCustomer(), $event->getContext());
        $optin = $this->getRegistrationOptin($customer, $salesChannelId);

        $this->syncCustomerToFreshdesk($customer, $salesChannelId, $optin, 'Freshdesk registration contact sync failed');
    }

    public function onCustomerDoubleOptInRegistration(CustomerDoubleOptInRegistrationEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();
        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enabled', $salesChannelId)) {
            return;
        }

        $customer = $this->loadCustomer($event->getCustomer(), $event->getContext());
        $optin = $this->getOptinSyncMode($salesChannelId) === self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN
            ? false
            : $this->hasFreshdeskConsent($customer);

        $this->syncCustomerToFreshdesk($customer, $salesChannelId, $optin, 'Freshdesk initial double Optin registration contact sync failed');
    }

    private function syncCustomerToFreshdesk(CustomerEntity $customer, string $salesChannelId, bool $optin, string $failureMessage): void
    {
        $email = trim((string) $customer->getEmail());
        if ($email === '') {
            return;
        }

        $result = $this->freshdeskService->createOrUpdateRegistrationContact(
            $email,
            $salesChannelId,
            $this->buildCustomerName($customer),
            $customer->getDefaultBillingAddress()?->getPhoneNumber(),
            $this->buildCustomerAddress($customer),
            $customer->getLanguage()?->getLocale()?->getCode(),
            $optin
        );

        if (!$result['success']) {
            $this->logger->warning($failureMessage, [
                'customerId' => $customer->getId(),
                'salesChannelId' => $salesChannelId,
                'optin' => $optin,
                'message' => $result['message'] ?? 'unknown error',
            ]);
        }
    }

    private function getRegistrationOptin(CustomerEntity $customer, string $salesChannelId): bool
    {
        if ($this->getOptinSyncMode($salesChannelId) === self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN) {
            return $this->isConfirmedDoubleOptInRegistration($customer);
        }

        return $this->hasFreshdeskConsent($customer);
    }

    private function getOptinSyncMode(string $salesChannelId): string
    {
        $mode = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.optinSyncMode', $salesChannelId);

        if ($mode === self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN) {
            return self::OPTIN_SYNC_MODE_SHOPWARE_DOUBLE_OPTIN;
        }

        return self::OPTIN_SYNC_MODE_DIRECT_CHECKBOX;
    }

    private function isConfirmedDoubleOptInRegistration(CustomerEntity $customer): bool
    {
        return $customer->getDoubleOptInRegistration() && $customer->getDoubleOptInConfirmDate() !== null;
    }

    private function hasFreshdeskConsent(CustomerEntity $customer): bool
    {
        $customFields = $customer->getCustomFields() ?? [];
        if (!is_array($customFields)) {
            return false;
        }

        return (bool) ($customFields[self::CONSENT_CUSTOM_FIELD] ?? false);
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

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function loadCustomer(CustomerEntity $customer, Context $context): CustomerEntity
    {
        $criteria = new Criteria([$customer->getId()]);
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');

        $loadedCustomer = $this->customerRepository
            ->search($criteria, $context)
            ->first();

        if ($loadedCustomer instanceof CustomerEntity) {
            return $loadedCustomer;
        }

        return $customer;
    }
}
