<?php

namespace Amplify\ErpApi\Traits;

use Amplify\ErpApi\Adapters\CsdErpAdapter;
use Amplify\ErpApi\Adapters\DefaultErpAdapter;
use Amplify\ErpApi\Adapters\FactsErpAdapter;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Exceptions\ErpApiException;
use Amplify\System\Backend\Models\Country;
use Amplify\System\Backend\Models\Customer;
use Amplify\System\Backend\Models\Shipping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait ErpApiConfigTrait
{
    public array $config;

    public ?int $orderId = null;

    /**
     * @var FactsErpAdapter|DefaultErpAdapter|CsdErpAdapter
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

            Log::error($th->getMessage());

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

        report($exception);
    }

    protected function countryByIso2(string $iso): ?Country
    {
        return Country::whereIso2($iso)->first();
    }

    protected function countryByIso3(string $iso): ?Country
    {
        return Country::whereIso2($iso)->first();
    }

    /**
     * This function will return the ERP Carrier code options
     *
     * @done
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $options = Shipping::enabled()->get()->toArray();

        return $this->adapter->getShippingOption($options);
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     *
     * ** Note this function does not make andy api call **
     *
     * @throws ErpApiException|\Exception
     *
     * @since 2024.12.8354871
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        try {

            $warehouses = Cache::remember('site-erp-warehouses', WEEK, function () {
                return \Amplify\System\Backend\Models\Warehouse::all();
            });

            foreach ($filters as $filter) {
                if (in_array(count($filter), [2, 3])) {
                    $warehouses = $warehouses->where(...$filter);
                }
            }

            return $this->adapter->getWarehouses($warehouses->toArray());

        } catch (\Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getWarehouses();
        }
    }
}
