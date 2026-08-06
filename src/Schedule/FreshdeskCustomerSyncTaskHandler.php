<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Schedule;

use CodeCom\FreshdeskSyncCustomer\Service\CustomerSyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: FreshdeskCustomerSyncTask::class)]
class FreshdeskCustomerSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly CustomerSyncService $customerSyncService,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enableAutomaticCustomerSync')) {
            $this->customerSyncService->markCronDisabled();
            return;
        }

        $context = Context::createCLIContext();
        $summary = $this->customerSyncService->syncCustomerBatch(
            $context,
            $this->customerSyncService->getCronBatchSize(),
            true,
            true,
            true
        );

        $this->customerSyncService->updateCronStatus($summary);
    }
}
