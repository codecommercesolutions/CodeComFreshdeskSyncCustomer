<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FreshdeskService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly \Psr\Log\LoggerInterface $logger,
        private readonly EntityRepository $logEntryRepository,
        private readonly ?MailerInterface $mailer = null
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Logging helper
    // Routes plugin diagnostics through Shopware's logger.
    // ─────────────────────────────────────────────────────────────────────────
    private function log(string $message): void
    {
        $this->logger->info($message, ['plugin' => 'CodeComFreshdeskSyncCustomer']);
    }

    private function logToDatabase(string $requestUrl, array $requestPayload, ResponseInterface $response, Context $context, ?string $salesChannelId = null): void
    {
        if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enableEventLog', $salesChannelId)) {
            return;
        }

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getContent(false);

        $this->logEntryRepository->create([
            [
                'message' => 'Freshdesk API Call',
                'level' => $statusCode >= 400 ? \Monolog\Logger::ERROR : \Monolog\Logger::INFO,
                'channel' => 'Freshdesk API',
                'context' => [
                    'request' => [
                        'url' => $requestUrl,
                        'payload' => $requestPayload,
                    ],
                    'response' => [
                        'statusCode' => $statusCode,
                        'body' => json_decode($responseBody, true) ?? $responseBody,
                    ],
                ],
            ],
        ], $context);
    }

    /**
     * Find a Freshdesk contact by email address.
     * @return array<string, mixed>|null
     */
    public function findContactByEmail(string $email, ?string $salesChannelId = null): ?array
    {
        $this->log("findContactByEmail() called | email={$email}");

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('findContactByEmail() aborted: API not configured');
            return null;
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts?email=' . urlencode($email);
            $this->log("findContactByEmail() → GET {$url}");

            $response = $this->httpClient->request('GET', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
            ]);
            
            $this->logToDatabase($url, [], $response, Context::createDefaultContext(), $salesChannelId);
            
            $data     = $response->toArray(false);

            $this->log("findContactByEmail() ← HTTP " . $response->getStatusCode() . ' | body=' . json_encode($data));

            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $this->log("findContactByEmail() contact found | id=" . ($data[0]['id'] ?? '-'));
                return $data[0];
            }
            if (is_array($data) && isset($data['id'])) {
                $this->log("findContactByEmail() contact found | id=" . $data['id']);
                return $data;
            }

            $this->log("findContactByEmail() no contact found for email={$email}");
            return null;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($e instanceof HttpExceptionInterface) {
                try {
                    $message .= ' | Response: ' . $e->getResponse()->getContent(false);
                } catch (\Exception $e) {}
            }
            $this->log('findContactByEmail() EXCEPTION | ' . $message);
            return null;
        }
    }

    /**
     * Update a Freshdesk contact (PUT /api/v2/contacts/:id).
     * @param array<string, mixed> $data
     * @return array{success: bool, message?: string}
     */
    public function updateFreshdeskContact(int $contactId, array $data, ?string $salesChannelId = null): array
    {
        $this->log("updateFreshdeskContact() called | contact_id={$contactId} | data=" . json_encode($data));

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('updateFreshdeskContact() aborted: API not configured');
            return ['success' => false, 'message' => 'API not configured'];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts/' . $contactId;
            $this->log("updateFreshdeskContact() → PUT {$url}");

            $response   = $this->httpClient->request('PUT', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers'    => ['Content-Type' => 'application/json'],
                'json'       => $data,
            ]);

            $this->logToDatabase($url, $data, $response, Context::createDefaultContext(), $salesChannelId);

            $statusCode = $response->getStatusCode();

            $responseBody = $response->toArray(false);
            $this->log("updateFreshdeskContact() ← HTTP {$statusCode} | body=" . json_encode($responseBody));

            if ($statusCode === 200) {
                $this->log("updateFreshdeskContact() SUCCESS | contact_id={$contactId}");
                return ['success' => true];
            }

            $errMsg = 'Update contact failed: HTTP ' . $statusCode . ' | ' . json_encode($responseBody);
            $this->sendErrorEmailNotification('Update Contact Failed (HTTP ' . $statusCode . ')', "Contact ID: {$contactId}\nURL: {$url}\nStatus: {$statusCode}\nResponse: " . json_encode($responseBody, JSON_PRETTY_PRINT), $salesChannelId);

            return ['success' => false, 'message' => $errMsg];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($e instanceof HttpExceptionInterface) {
                try {
                    $message .= ' | Response: ' . $e->getResponse()->getContent(false);
                } catch (\Exception $e) {}
            }
            $this->log('updateFreshdeskContact() EXCEPTION | ' . $message);
            $this->sendErrorEmailNotification('Update Contact Exception', "Contact ID: {$contactId}\nMessage: {$message}\nTrace:\n" . $e->getTraceAsString(), $salesChannelId);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Map Shopware locale (e.g. de-DE, fr-CH) to Freshdesk language code.
     */
    public function mapShopwareLocaleToFreshdeskLanguage(string $locale): string
    {
        $this->log("mapShopwareLocaleToFreshdeskLanguage() called | locale={$locale}");

        // Normalize locale (e.g. de-DE -> de, fr-CH -> fr)
        $parts = explode('-', $locale);
        $shortCode = strtolower($parts[0]);

        $supportedLanguages = [
            'ar', 'ca', 'cs', 'cy-GB', 'da', 'de', 'en', 'es', 'es-LA', 'et',
            'fi', 'fr', 'he', 'hr', 'hu', 'id', 'it', 'ja-JP', 'ko', 'lv-LV',
            'nb-NO', 'nl', 'pl', 'pt-BR', 'pt-PT', 'ro', 'ru-RU', 'sk', 'sl',
            'sv-SE', 'th', 'tr', 'uk', 'vi', 'zh-CN', 'zh-TW'
        ];

        // Check for full match first (e.g. cy-GB)
        if (in_array($locale, $supportedLanguages, true)) {
            return $locale;
        }

        // Check for short code match (e.g. de)
        if (in_array($shortCode, $supportedLanguages, true)) {
            return $shortCode;
        }

        return 'en'; // Default to English
    }

    /**
     * Create or update a Freshdesk contact from the Shopware registration flow.
     *
     * @return array{success: bool, id?: int|null, created?: bool, message?: string}
     */
    public function createOrUpdateRegistrationContact(
        string $email,
        ?string $salesChannelId = null,
        ?string $name = null,
        ?string $phone = null,
        ?string $address = null,
        ?string $shopwareLanguageCode = null,
        bool $optin = false,
        array|string|null $contactTags = null
    ): array {
        $this->log("createOrUpdateRegistrationContact() called | email={$email} | name={$name} | address={$address} | language={$shopwareLanguageCode} | optin=" . ($optin ? 'true' : 'false'));

        $email = trim($email);
        if ($email === '') {
            $this->log('createOrUpdateRegistrationContact() aborted: empty email');
            return ['success' => false, 'message' => 'Email is required'];
        }

        $phone = trim((string) $phone);
        if (strlen($phone) < 5) {
            $phone = null;
        }

        $tags = $this->normalizeContactTags($contactTags);
        if ($tags === []) {
            $fallbackTag = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.contactTag', $salesChannelId);
            $tags = [$fallbackTag !== '' ? $fallbackTag : 'Webshop'];
        }

        $language = 'en';
        if ($shopwareLanguageCode !== null) {
            $language = $this->mapShopwareLocaleToFreshdeskLanguage($shopwareLanguageCode);
        }
        $optinCustomField = $this->getOptinCustomField($salesChannelId);

        $existingContact = $this->findContactByEmail($email, $salesChannelId);
        if ($existingContact !== null && !empty($existingContact['id'])) {
            $updateData = [];

            if (!empty($name)) {
                $updateData['name'] = $name;
            }

            if (!empty($phone)) {
                $updateData['phone'] = $phone;
            }

            if (!empty($address)) {
                $updateData['address'] = $address;
            }

            $updateData['language'] = $language;

            if ($optinCustomField !== null) {
                $updateData['custom_fields'] = [$optinCustomField => $this->formatOptinValue($optin, $salesChannelId)];
            }

            $existingTags = $existingContact['tags'] ?? [];
            if (!is_array($existingTags)) {
                $existingTags = [];
            }
            $mergedTags = array_values(array_unique(array_merge($existingTags, $tags)));
            if ($mergedTags !== $existingTags) {
                $updateData['tags'] = $mergedTags;
            }

            if ($updateData === []) {
                $this->log("createOrUpdateRegistrationContact() contact already exists with no updates needed | contact_id={$existingContact['id']}");
                return [
                    'success' => true,
                    'id' => (int) $existingContact['id'],
                    'created' => false,
                    'message' => 'Contact already exists',
                ];
            }

            $updateResult = $this->updateFreshdeskContact((int) $existingContact['id'], $updateData, $salesChannelId);

            return [
                'success' => $updateResult['success'],
                'id' => (int) $existingContact['id'],
                'created' => false,
                'message' => $updateResult['message'] ?? 'Contact updated successfully',
            ];
        }

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('createOrUpdateRegistrationContact() aborted: API not configured');
            return ['success' => false, 'message' => 'API not configured'];
        }

        $payload = ['email' => $email];

        if (!empty($name)) {
            $payload['name'] = $name;
        }

        if (!empty($phone)) {
            $payload['phone'] = $phone;
        }

        if (!empty($address)) {
            $payload['address'] = $address;
        }

        $payload['tags'] = $tags;
        $payload['language'] = $language;

        if ($optinCustomField !== null) {
            $payload['custom_fields'] = [$optinCustomField => $this->formatOptinValue($optin, $salesChannelId)];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts';
            $this->log("createOrUpdateRegistrationContact() → POST {$url} | payload=" . json_encode($payload));

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
            ]);

            $this->logToDatabase($url, $payload, $response, Context::createDefaultContext(), $salesChannelId);

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->log("createOrUpdateRegistrationContact() ← HTTP {$statusCode} | response=" . json_encode($responseData));

            if ($statusCode === 201) {
                return [
                    'success' => true,
                    'id' => isset($responseData['id']) ? (int) $responseData['id'] : null,
                    'created' => true,
                    'message' => 'Contact created successfully',
                ];
            }

            $duplicateContactId = null;
            $isDuplicate = ($statusCode === 409);

            if (is_array($responseData['errors'] ?? null)) {
                foreach ($responseData['errors'] as $err) {
                    $code = strtolower((string) ($err['code'] ?? ''));
                    $msg = strtolower((string) ($err['message'] ?? ''));
                    if ($code === 'duplicate_value' || str_contains($msg, 'unique value') || str_contains($msg, 'already exists')) {
                        $isDuplicate = true;
                        if (isset($err['additional_info']['user_id']) && (int) $err['additional_info']['user_id'] > 0) {
                            $duplicateContactId = (int) $err['additional_info']['user_id'];
                            break;
                        }
                    }
                }
            }

            if ($isDuplicate && ($duplicateContactId === null || $duplicateContactId <= 0)) {
                $existingContact = $this->findContactByEmail($email, $salesChannelId);
                if ($existingContact !== null && !empty($existingContact['id'])) {
                    $duplicateContactId = (int) $existingContact['id'];
                }
            }

            if ($duplicateContactId !== null && $duplicateContactId > 0) {
                $this->log("createOrUpdateRegistrationContact() duplicate contact detected | id={$duplicateContactId} | retrying update");
                $updateData = [];

                if (!empty($name)) {
                    $updateData['name'] = $name;
                }

                if (!empty($phone)) {
                    $updateData['phone'] = $phone;
                }

                if (!empty($address)) {
                    $updateData['address'] = $address;
                }

                $updateData['language'] = $language;

                if ($optinCustomField !== null) {
                    $updateData['custom_fields'] = [$optinCustomField => $this->formatOptinValue($optin, $salesChannelId)];
                }

                $updateData['tags'] = $tags;

                $updateResult = $this->updateFreshdeskContact($duplicateContactId, $updateData, $salesChannelId);

                return [
                    'success' => $updateResult['success'],
                    'id' => $duplicateContactId,
                    'created' => false,
                    'message' => $updateResult['message'] ?? 'Existing contact updated after duplicate response',
                ];
            }

            $errMsg = 'Failed to create contact: ' . json_encode($responseData);
            $this->sendErrorEmailNotification('Create Contact Failed (HTTP ' . $statusCode . ')', "Email: {$email}\nURL: {$url}\nHTTP Status: {$statusCode}\nResponse: " . json_encode($responseData, JSON_PRETTY_PRINT), $salesChannelId);

            return [
                'success' => false,
                'message' => $errMsg,
            ];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($e instanceof HttpExceptionInterface) {
                try {
                    $message .= ' | Response: ' . $e->getResponse()->getContent(false);
                } catch (\Exception $e) {}
            }
            $this->log('createOrUpdateRegistrationContact() EXCEPTION | ' . $message);
            $this->sendErrorEmailNotification('Create Contact Exception', "Email: {$email}\nMessage: {$message}\nTrace:\n" . $e->getTraceAsString(), $salesChannelId);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function sendErrorEmailNotification(string $subject, string $details, ?string $salesChannelId = null): void
    {
        try {
            if (!$this->systemConfigService->getBool('CodeComFreshdeskSyncCustomer.config.enableErrorEmail', $salesChannelId)) {
                return;
            }

            $recipientString = trim((string) $this->systemConfigService->get('CodeComFreshdeskSyncCustomer.config.errorEmailAddress', $salesChannelId));
            if ($recipientString === '') {
                return;
            }

            $recipients = array_filter(array_map('trim', explode(',', $recipientString)));
            if (empty($recipients)) {
                return;
            }

            $formattedSubject = '[Freshdesk Sync Error] ' . $subject;
            $htmlContent = '
                <div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 650px; margin: 0 auto;">
                    <h2 style="color: #d9534f; margin-top: 0;">Freshdesk Customer Sync Error Alert</h2>
                    <p>An unexpected error occurred during Freshdesk customer data synchronization.</p>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
                    <h4 style="margin-bottom: 5px;">Error Details:</h4>
                    <pre style="background: #f8f9fa; padding: 15px; border-left: 4px solid #d9534f; font-family: monospace; white-space: pre-wrap; word-break: break-word;">' . htmlspecialchars($details) . '</pre>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
                    <p style="font-size: 12px; color: #777;">Timestamp: ' . date('Y-m-d H:i:s T') . '</p>
                </div>
            ';

            foreach ($recipients as $toEmail) {
                if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                if ($this->mailer !== null) {
                    $emailObj = (new Email())
                        ->to($toEmail)
                        ->subject($formattedSubject)
                        ->html($htmlContent);

                    $this->mailer->send($emailObj);
                } else {
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    @mail($toEmail, $formattedSubject, $htmlContent, $headers);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed sending error notification email: ' . $e->getMessage());
        }
    }

    private function formatOptinValue(bool $optin, ?string $salesChannelId): bool|string
    {
        $type = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.freshdeskOptinCustomFieldType', $salesChannelId);

        if ($type === 'string') {
            return $optin ? 'true' : 'false';
        }

        return $optin;
    }

    /**
     * @return list<string>
     */
    private function normalizeContactTags(array|string|null $contactTags): array
    {
        if (is_string($contactTags)) {
            $contactTags = [$contactTags];
        }

        if (!is_array($contactTags)) {
            return [];
        }

        $tags = [];
        foreach ($contactTags as $tag) {
            if (!is_scalar($tag)) {
                continue;
            }

            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    private function getOptinCustomField(?string $salesChannelId): ?string
    {
        $field = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.freshdeskOptinCustomField', $salesChannelId);
        $field = trim($field);

        return $field !== '' ? $field : null;
    }
}
