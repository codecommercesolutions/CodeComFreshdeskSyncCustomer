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

# 3.2.6
- Added automatic user_id extraction and contact update retry when Freshdesk API returns HTTP 400 duplicate_value validation error.

# 3.2.7
- Updated reset-sync-status command to also reset all cumulative total sync counters (totalCustomerSyncProcessedCount, totalCustomerSyncSyncedCount, totalCustomerSyncSkippedCount, totalCustomerSyncFailedCount) in plugin configuration to 0.

# 3.2.8
- Added administration extension configuration Reset Sync Status button component and API action endpoint to allow resetting customer sync status, counters, and logs directly from the plugin settings page.

# 3.2.9
- Fixed Symfony route order and path for resetStatus controller endpoint to prevent UUID route conflict error.

# 3.3.0
- Enhanced Freshdesk duplicate contact handling with fallback email search when Freshdesk API returns validation errors containing unique value messages.

# 3.3.1
- Updated CLI sync command to output formatted real-time customer progress lines (1) Customer ID xx (email) - status) and persist sync counters to plugin configuration when run with --mark-processed.

# 3.3.2
- Implemented real-time in-loop configuration counter updates and dynamic database remaining count calculation during customer batch processing.

# 3.3.3
- Added dynamic country-based Freshdesk tag assignment: invoice addresses from Switzerland (CH) and Liechtenstein (LI) are tagged with "Webshop-CH", and all other countries with "Webshop-EU".

# 3.3.4
- Added configurable Countries for Webshop-CH Tag multiselect configuration setting (chWebshopCountries) in plugin administration settings with fallback to default Switzerland and Liechtenstein country matching.












