<?php

namespace Amplify\ErpApi\Traits;

use Amplify\System\Backend\Models\CustomerOrder;
use Exception;

trait PersistsCustomerOrderErpCall
{
    protected function recordCustomerOrderErpFailure(array $orderInfo, Exception $exception): void
    {
        if (empty($orderInfo['customer_order_id'])) {
            return;
        }

        CustomerOrder::whereKey($orderInfo['customer_order_id'])->update([
            'erp_error_message' => $exception->getMessage(),
            'erp_response_at' => now(),
        ]);
    }

    protected function sendCustomerOrderErpCall(array $orderInfo, array $httpRequestBody, callable $sendRequest): array
    {
        $customerOrder = isset($orderInfo['customer_order_id'])
            ? CustomerOrder::find($orderInfo['customer_order_id'])
            : null;

        $customerOrder?->update([
            'erp_request_body' => json_encode($httpRequestBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'erp_response_body' => null,
            'erp_error_message' => null,
            'erp_request_at' => now(),
            'erp_response_at' => null,
        ]);

        $rawResponseBody = null;

        try {
            $response = $sendRequest($rawResponseBody);
        } catch (Exception $exception) {
            $customerOrder?->update([
                'erp_response_body' => $rawResponseBody,
                'erp_error_message' => $exception->getMessage(),
                'erp_response_at' => now(),
            ]);

            return ['error' => $exception->getMessage()];
        }

        $customerOrder?->update([
            'erp_response_body' => $rawResponseBody,
            'erp_error_message' => $response['error'] ?? null,
            'erp_response_at' => now(),
        ]);

        return $response;
    }
}
