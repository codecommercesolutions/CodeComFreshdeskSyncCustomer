<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Command;

use CodeCom\FreshdeskSyncCustomer\Service\CustomerSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'codecom:freshdesk:reset-sync-status',
    description: 'Reset customer Freshdesk sync status custom fields and clear public freshdesk.log file.'
)]
class ResetSyncStatusCommand extends Command
{
    public function __construct(
        private readonly CustomerSyncService $customerSyncService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->customerSyncService->resetSyncStatus();

        $message = sprintf(
            'Reset sync status for %d customer(s).',
            $result['affectedCustomers']
        );

        if ($result['logCleared']) {
            $message .= ' Cleared public/freshdesk.log file.';
        } else {
            $message .= ' public/freshdesk.log file was empty or not found.';
        }

        $io->success($message);

        return Command::SUCCESS;
    }
}
