<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Schedule;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class FreshdeskCustomerSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
            return 'codecom.freshdesk.customer_sync';
    }

    public static function getDefaultInterval(): int
    {
        return 3600;
    }
}
