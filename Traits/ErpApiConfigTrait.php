<?php

namespace Amplify\ErpApi\Traits;

use Amplify\ErpApi\Adapters\CommerceGatewayAdapter;
use Amplify\ErpApi\Adapters\CsdErpAdapter;
use Amplify\ErpApi\Adapters\DefaultErpAdapter;
use Amplify\ErpApi\Adapters\FactsErp77Adapter;
use Amplify\ErpApi\Adapters\FactsErpAdapter;
use Amplify\System\Backend\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait ErpApiConfigTrait
{
    public array $config;

    /**
     * @var FactsErpAdapter|FactsErp77Adapter|DefaultErpAdapter|CommerceGatewayAdapter|CsdErpAdapter
     */
    public $adapter;

    /**
     * get current logged in contact customer ID field value
     *
     * @return null|mixed
     */
    private function customerId(array $data = []): mixed
    {
        if (!empty($data['customer_number'])) {
            return $data['customer_number'];
        }

        if (customer_check()) {
            return customer()?->erp_id ?? null;
        }

        return (config('amplify.frontend.guest_default') != null && strlen(config('amplify.frontend.guest_default')) > 0)
            ? config('amplify.frontend.guest_default')
            : null;
    }

    /**
     * get current logged in contact customer Email field value
     *
     * @return null|mixed
     *
     * @throws \ErrorException
     */
    private function customerEmail(): mixed
    {
        if (customer_check()) {
            return customer(true)->email;
        }

        try {
            return store()->customerEmail;
        } catch (\Throwable $th) {
            store()->customerEmail = Customer::where('customer_code', $this->customerId())->first()?->email;

            return store()->customerEmail;
        }
    }

    protected function logProductSyncResponse($response): void
    {
        Log::channel('product-sync')
            ->debug(
                "\n                    :: Product Sync API RAW Response ::                   "
                . "\n--------------------------------------------------------------------------"
                . "\n" . (is_string($response) ? $response : json_encode($response, JSON_PRETTY_PRINT))
                . "\n--------------------------------------------------------------------------\n");
    }

    /**
     * ERP Call Exception Handler
     *
     * @throws \Exception
     */
    protected function exceptionHandler(\Exception $exception): void
    {
        $trace = $exception->getTrace();
        $firstTrace = array_shift($trace);
        $class = class_basename($firstTrace['class'] ?? '');
        $method = $firstTrace['function'] ?? '';
        $args = json_encode($firstTrace['args'] ?? []);

        Log::debug("Error: {$exception->getMessage()} | Class ErpApi::{$method}({$args}) | Driver: {$class}");

        if (!suppress_exception()) {
            throw $exception;
        }
    }
}
