<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Command;

use CodeCom\FreshdeskSyncCustomer\Service\CustomerSyncService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'codecom:freshdesk:sync-customers',
    description: 'Synchronize Shopware customers to Freshdesk.'
)]
class SyncCustomersCommand extends Command
{
    public function __construct(
        private readonly CustomerSyncService $customerSyncService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('customer-id', null, InputOption::VALUE_REQUIRED, 'Sync a single customer by Shopware customer ID.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of customers to process. Defaults to the plugin CLI customer limit.')
            ->addOption('only-unprocessed', null, InputOption::VALUE_NONE, 'Process only customers not yet handled by cron/CLI batch sync.')
            ->addOption('mark-processed', null, InputOption::VALUE_NONE, 'Mark successfully synced or intentionally skipped customers as processed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        $customerId = $input->getOption('customer-id');
        if (is_string($customerId) && $customerId !== '') {
            $result = $this->customerSyncService->syncCustomerById(
                $customerId,
                $context,
                null,
                (bool) $input->getOption('mark-processed'),
                true
            );

            $io->writeln(json_encode($result, JSON_PRETTY_PRINT) ?: '');

            return ($result['success'] ?? false) ? Command::SUCCESS : Command::FAILURE;
        }

        $limitOption = $input->getOption('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : $this->customerSyncService->getCliBatchSize();

        $outputCallback = function (int $index, string $customerId, string $email, array $result) use ($io): void {
            $statusStr = match (true) {
                ($result['skipped'] ?? false) === true => 'skipped (' . ($result['message'] ?? '') . ')',
                ($result['success'] ?? false) === true => 'successfully synced',
                default => 'failed (' . ($result['message'] ?? 'error') . ')',
            };
            $io->writeln(sprintf('%d) Customer ID %s (%s) - %s', $index, $customerId, $email, $statusStr));
        };

        $summary = $this->customerSyncService->syncCustomerBatch(
            $context,
            $limit,
            (bool) $input->getOption('only-unprocessed'),
            (bool) $input->getOption('mark-processed'),
            true,
            $outputCallback
        );

        if ((bool) $input->getOption('mark-processed')) {
            $this->customerSyncService->updateCronStatus($summary);
        }

        $io->newLine();
        $io->success(sprintf(
            'Processed %d customers: %d synced, %d skipped, %d failed, %d remaining.',
            $summary['processed'],
            $summary['synced'],
            $summary['skipped'],
            $summary['failed'],
            $summary['remaining']
        ));

        return $summary['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
