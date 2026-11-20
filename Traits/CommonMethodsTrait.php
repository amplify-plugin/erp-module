<?php

namespace Amplify\ErpApi\Traits;

use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Exceptions\ErpApiException;
use Amplify\System\Backend\Models\Shipping;
use Illuminate\Support\Facades\Cache;

trait CommonMethodsTrait
{
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

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function allowMultiWarehouse(): bool
    {
        return $this->config['multiple_warehouse'] ?? false;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function useSingleWarehouseInCart(): bool
    {
        return $this->config['use_single_warehouse_cart'] ?? false;
    }

    /**
     * This function will check if the ERP can be enabled
     */
    public function enabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    public function adapter(): mixed
    {
        return $this->adapter;
    }
}