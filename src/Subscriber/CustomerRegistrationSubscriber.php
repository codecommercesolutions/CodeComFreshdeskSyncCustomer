<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Subscriber;

use CodeCom\FreshdeskSyncCustomer\Service\FreshdeskService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
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

        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enableRegistrationSyncCheckbox', $salesChannelId)) {
            return;
        }

        $customer = $this->loadCustomer($event->getCustomer(), $event->getContext());

        if (!$this->hasFreshdeskConsent($customer)) {
            return;
        }

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
            $customer->getLanguage()?->getLocale()?->getCode()
        );

        if (!$result['success']) {
            $this->logger->warning('Freshdesk registration contact sync failed', [
                'customerId' => $customer->getId(),
                'salesChannelId' => $salesChannelId,
                'message' => $result['message'] ?? 'unknown error',
            ]);
        }
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

        $parts = array_filter([
            trim($address->getStreet()),
            trim((string) $address->getAdditionalAddressLine1()),
            trim((string) $address->getAdditionalAddressLine2()),
            trim($address->getZipcode() ?? ''),
            trim($address->getCity()),
            trim((string) $address->getCountry()?->getName()),
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