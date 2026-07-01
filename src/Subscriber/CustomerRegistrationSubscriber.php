<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Subscriber;

use CodeCom\FreshdeskSyncCustomer\Service\CustomerSyncService;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerRegistrationSubscriber implements EventSubscriberInterface
{
    private const CONSENT_FIELD = 'freshdeskSyncContactConsent';

    private const CONSENT_CUSTOM_FIELD = 'freshdesk_sync_contact_consent';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly CustomerSyncService $customerSyncService
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

        $this->customerSyncService->syncCustomer($event->getCustomer(), $event->getContext());
    }

    public function onCustomerDoubleOptInRegistration(CustomerDoubleOptInRegistrationEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();
        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enabled', $salesChannelId)) {
            return;
        }

        $this->customerSyncService->syncCustomer($event->getCustomer(), $event->getContext());
    }
}
