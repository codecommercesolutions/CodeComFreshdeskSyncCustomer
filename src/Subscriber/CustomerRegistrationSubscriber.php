<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Subscriber;

use CodeCom\FreshdeskSyncCustomer\Service\CustomerSyncService;
use CodeCom\FreshdeskSyncCustomer\Service\FreshdeskService;
use Psr\Log\LoggerInterface;
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
        private readonly CustomerSyncService $customerSyncService,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?FreshdeskService $freshdeskService = null
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
        try {
            $salesChannelId = $event->getSalesChannelId();

            if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enabled', $salesChannelId)) {
                return;
            }

            $this->customerSyncService->syncCustomer($event->getCustomer(), $event->getContext());
        } catch (\Throwable $e) {
            $this->logger?->error('Freshdesk customer registration sync error: ' . $e->getMessage(), [
                'exception' => $e,
                'customerId' => $event->getCustomer()->getId(),
            ]);
            $this->freshdeskService?->sendErrorEmailNotification(
                'Registration Sync Exception',
                "Customer ID: {$event->getCustomer()->getId()}\nEmail: {$event->getCustomer()->getEmail()}\nMessage: {$e->getMessage()}\nTrace:\n" . $e->getTraceAsString(),
                $event->getSalesChannelId()
            );
        }
    }

    public function onCustomerDoubleOptInRegistration(CustomerDoubleOptInRegistrationEvent $event): void
    {
        try {
            $salesChannelId = $event->getSalesChannelId();
            if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enabled', $salesChannelId)) {
                return;
            }

            $this->customerSyncService->syncCustomer($event->getCustomer(), $event->getContext());
        } catch (\Throwable $e) {
            $this->logger?->error('Freshdesk double opt-in customer sync error: ' . $e->getMessage(), [
                'exception' => $e,
                'customerId' => $event->getCustomer()->getId(),
            ]);
            $this->freshdeskService?->sendErrorEmailNotification(
                'Double Opt-In Sync Exception',
                "Customer ID: {$event->getCustomer()->getId()}\nEmail: {$event->getCustomer()->getEmail()}\nMessage: {$e->getMessage()}\nTrace:\n" . $e->getTraceAsString(),
                $event->getSalesChannelId()
            );
        }
    }
}
