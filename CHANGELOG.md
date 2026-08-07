# 1.0.0
- First version of the freshdesk sync customer data for Shopware 6.5

# 2.0.0
- First version of the freshdesk sync customer data for Shopware 6.6

# 3.0.0
- First version of the freshdesk sync customer data for Shopware 6.7

# 3.2.0
- Added configurable customer transfer mode for syncing all customers or only customers with Optin.
- Added scheduled customer batch sync with cron progress/status tracking.
- Added manual CLI customer sync command.
- Added customer detail administration controls for saving Optin and manually syncing a customer to Freshdesk.
- Sync Shopware customer tags to Freshdesk contact tags and retain Optin information in the configured Freshdesk custom field.

# 3.2.1
- Added SalesChannel specific contactTag plugin configuration tag appending for customer sync.
- Added custom field freshdesk_api_response under Freshdesk set on Customer entity to log API error or success ("customer synced suceesfully in freshdesk") during CLI command and cron executions.
- Added public log file freshdesk.log (public/freshdesk.log) for tracking failed customer API sync attempts with Customer ID during CLI command and cron executions.

# 3.2.2
- Added codecom:freshdesk:reset-sync-status CLI command to reset all customer sync status flags and clear public/freshdesk.log file.

# 3.2.3
- Enabled Freshdesk customfield API response logging and saleschannel-based contactTag appending when manually triggering customer sync via the Administration customer detail panel Sync button.

# 3.2.4
- Added enableEventLog configuration toggle under API Connection settings to manage system event log entries (log_entry database table).

# 3.2.5
- Updated markProcessed handling to set processed_at timestamp for all processed customers (including failed attempts) during batch syncs, ensuring subsequent --only-unprocessed runs advance to next customer batches without getting stuck on failed records.





