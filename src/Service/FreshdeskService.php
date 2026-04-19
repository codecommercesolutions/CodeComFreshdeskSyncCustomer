<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FreshdeskService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Logging helper
    // Writes to var/log/codecom-freshdesk-sync-YYYY-MM-DD.log, one line per call.
    // ─────────────────────────────────────────────────────────────────────────
    private function log(string $message): void
    {
        $date      = (new \DateTime())->format('Y-m-d');
        $logFile   = getcwd() . '/../var/log/codecom-freshdesk-sync-' . $date . '.log';
        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        @file_put_contents(
            $logFile,
            '[' . $timestamp . '] ' . $message . "\n",
            FILE_APPEND
        );
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
            $data     = $response->toArray(false);

            $this->log("findContactByEmail() ← HTTP " . $response->getStatusCode());

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
            $this->log('findContactByEmail() EXCEPTION | ' . $e->getMessage());
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
            $statusCode = $response->getStatusCode();

            $responseBody = $response->toArray(false);
            $this->log("updateFreshdeskContact() ← HTTP {$statusCode} | body=" . json_encode($responseBody));

            if ($statusCode === 200) {
                $this->log("updateFreshdeskContact() SUCCESS | contact_id={$contactId}");
                return ['success' => true];
            }

            $this->log("updateFreshdeskContact() FAILED | HTTP {$statusCode} | body=" . json_encode($responseBody));
            return ['success' => false, 'message' => 'Update contact failed: HTTP ' . $statusCode . ' | ' . json_encode($responseBody)];
        } catch (\Exception $e) {
            $this->log('updateFreshdeskContact() EXCEPTION | ' . $e->getMessage());
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
        ?string $shopwareLanguageCode = null
    ): array {
        $this->log("createOrUpdateRegistrationContact() called | email={$email} | name={$name} | address={$address} | language={$shopwareLanguageCode}");

        $email = trim($email);
        if ($email === '') {
            $this->log('createOrUpdateRegistrationContact() aborted: empty email');
            return ['success' => false, 'message' => 'Email is required'];
        }

        $tag = $this->systemConfigService->getString('CodeComFreshdeskSyncCustomer.config.contactTag', $salesChannelId);
        if ($tag === '') {
            $tag = 'Webshop';
        }

        $language = 'en';
        if ($shopwareLanguageCode !== null) {
            $language = $this->mapShopwareLocaleToFreshdeskLanguage($shopwareLanguageCode);
        }

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

            $existingTags = $existingContact['tags'] ?? [];
            if (!is_array($existingTags)) {
                $existingTags = [];
            }
            if (!in_array($tag, $existingTags, true)) {
                $existingTags[] = $tag;
                $updateData['tags'] = $existingTags;
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

        $payload['tags'] = [$tag];
        $payload['language'] = $language;

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts';
            $this->log("createOrUpdateRegistrationContact() → POST {$url} | payload=" . json_encode($payload));

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
            ]);
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

            if ($statusCode === 409) {
                $this->log("createOrUpdateRegistrationContact() HTTP 409 | retrying as update for email={$email}");
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

                    $existingTags = $existingContact['tags'] ?? [];
                    if (!is_array($existingTags)) {
                        $existingTags = [];
                    }
                    if (!in_array($tag, $existingTags, true)) {
                        $existingTags[] = $tag;
                        $updateData['tags'] = $existingTags;
                    }

                    if ($updateData !== []) {
                        $updateResult = $this->updateFreshdeskContact((int) $existingContact['id'], $updateData, $salesChannelId);

                        return [
                            'success' => $updateResult['success'],
                            'id' => (int) $existingContact['id'],
                            'created' => false,
                            'message' => $updateResult['message'] ?? 'Existing contact updated after duplicate response',
                        ];
                    }

                    return [
                        'success' => true,
                        'id' => (int) $existingContact['id'],
                        'created' => false,
                        'message' => 'Contact already exists',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to create contact: ' . json_encode($responseData),
            ];
        } catch (\Exception $e) {
            $this->log('createOrUpdateRegistrationContact() EXCEPTION | ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
