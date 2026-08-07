<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer\Core\Api;

use CodeCom\FreshdeskSyncCustomer\Service\CustomerSyncService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class FreshdeskCustomerSyncController
{
    public function __construct(
        private readonly CustomerSyncService $customerSyncService
    ) {
    }

    #[Route(
        path: '/api/_action/codecom-freshdesk-sync-customer-reset-status',
        name: 'api.action.codecom_freshdesk_sync_customer.reset_status',
        methods: ['POST']
    )]
    public function resetStatus(Context $context): JsonResponse
    {
        $result = $this->customerSyncService->resetSyncStatus();

        return new JsonResponse([
            'success' => true,
            'message' => sprintf(
                'Reset sync status for %d customer(s). %s',
                $result['affectedCustomers'],
                $result['logCleared'] ? 'Cleared public/freshdesk.log file.' : ''
            ),
            'data' => $result,
        ]);
    }

    #[Route(
        path: '/api/_action/codecom-freshdesk-sync-customer/{customerId}',
        name: 'api.action.codecom_freshdesk_sync_customer.sync',
        methods: ['POST']
    )]
    public function syncCustomer(string $customerId, Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $optin = null;

        if (is_array($payload) && array_key_exists('optin', $payload)) {
            $optin = (bool) $payload['optin'];
        }

        $result = $this->customerSyncService->syncCustomerById($customerId, $context, $optin, true, true);

        return new JsonResponse($result, ($result['success'] ?? false) ? 200 : 400);
    }

    #[Route(
        path: '/api/_action/codecom-freshdesk-sync-customer/{customerId}/optin',
        name: 'api.action.codecom_freshdesk_sync_customer.optin',
        methods: ['POST']
    )]
    public function updateOptin(string $customerId, Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $optin = is_array($payload) && array_key_exists('optin', $payload)
            ? (bool) $payload['optin']
            : false;

        $result = $this->customerSyncService->updateCustomerOptin($customerId, $optin, $context);

        return new JsonResponse($result, ($result['success'] ?? false) ? 200 : 400);
    }
}
