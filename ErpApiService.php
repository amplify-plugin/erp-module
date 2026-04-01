<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Jobs\ProductSyncJob;
use Amplify\ErpApi\Services\AppriseErpService;
use Amplify\ErpApi\Services\CsdErpService;
use Amplify\ErpApi\Services\FactsErpService;
use Amplify\ErpApi\Traits\ErpApiConfigTrait;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\System\Backend\Models\ProductSync;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Cache;

class ErpApiService
{
    use ErpApiConfigTrait;
    use \Illuminate\Support\Traits\ForwardsCalls;
    use \Illuminate\Support\Traits\Macroable;
    use \Amplify\System\Traits\Overwritable;

    const LOOKUP_OPEN_ORDER = 'O';
    const LOOKUP_HISTORICAL_ORDER = 'H';
    const LOOKUP_DATE_RANGE = 'D';
    const LOOKUP_EMPTY = 'E';
    const DOC_TYPE_INVOICE = 'I';
    const DOC_TYPE_RENTAL_INVOICE = 'R';
    const DOC_TYPE_SHIP_SIGN = 'P';
    const INVOICE_STATUS_PAST = 'PAST';
    const INVOICE_STATUS_OPEN = 'OPEN';
    const TRANSACTION_TYPES_ORDER = 'SO';
    const TRANSACTION_TYPES_QUOTE = 'QU';

    public string $erpAdapterName = 'default';

    /**
     * Any Class that's enable the ERP API interface
     *
     * @var CsdErpService|FactsErpService|AppriseErpService
     */
    protected $serviceInstance;


    /**
     * The registered string macros.
     *
     * @var array
     */
    protected static $prepends = [];

    /**
     * Register a custom macro.
     *
     * @param string $method
     * @param object|callable $closure
     * @return void
     */
    public static function before($method, $closure)
    {
        static::$prepends[$method] = $closure;
    }

    private function processBeforeCall($method, $parameters, $instance)
    {
        $prepend = static::$prepends[$method];

        if ($prepend instanceof \Closure) {
            $prepend = $prepend->bindTo($instance, static::class);
        }

        return [$prepend(...$parameters)];
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT SYNCHRONIZATION SERVICE
    |--------------------------------------------------------------------------
    */
    public function init(string $adapter = null): self
    {
        $adapter = $adapter ?? config('amplify.erp.default');

        if ($this->serviceInstance && $this->erpAdapterName === $adapter) {
            return $this;
        }

        // Prevent switching to 'default' if already initialized with another adapter
        if ($this->serviceInstance && $adapter === 'default') {
            \Log::warning("Attempted to switch ERP adapter to 'default', but already initialized with '{$this->erpAdapterName}'. Ignoring.", [
                'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))->map(function ($frame) {
                    return ($frame['file'] ?? 'unknown') . ':' . ($frame['line'] ?? 'unknown') . ' ' . ($frame['function'] ?? 'unknown');
                })->toArray()
            ]);
            return $this;
        }

        if ($config = config("amplify.erp.configurations.{$adapter}")) {
            $this->erpAdapterName = $adapter;
            $this->serviceInstance = new $config['adapter'];

            \Log::info("ERP Adapter loaded: {$adapter} -> " . get_class($this->serviceInstance));

            return $this;

        } else {
            throw new \InvalidArgumentException("{$adapter} is a invalid adapter name");
        }
    }

    /**
     * ErpApiService Constructor
     */
    public function __construct()
    {
        $adapter = config('amplify.erp.default', 'default');

        $this->init($adapter);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     * @throws \ErrorException
     * @throws BindingResolutionException
     */
    public function __call($method, $parameters)
    {
        if (!$this->enabled()) {
            throw new \ErrorException(class_basename($this->serviceInstance) . ' is disabled.', 500);
        }

        $instance = in_array($method, ['storeProductSyncOnModel', 'updateProductWithSyncData'])
            ? app()->make(ProductSyncService::class)
            : $this->serviceInstance;

        if (isset(static::$prepends[$method])) {
            $parameters = $this->processBeforeCall($method, $parameters, $instance);
        }

        if (isset(static::$overwrites[$method])) {
            return $this->processOverwriteCall($method, $parameters, $instance);
        }

        if (method_exists($instance, $method)) {
            return $this->forwardCallTo($instance, $method, $parameters);
        }

        if (!static::hasMacro($method)) {
            throw new \BadMethodCallException(sprintf(
                'Macro method %s::%s does not exist.', static::class, $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof \Closure) {
            $macro = $macro->bindTo($instance, static::class);
        }

        return $macro(...$parameters);
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function currentErp(): string
    {
        return $this->erpAdapterName;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function allowMultiWarehouse(): bool
    {
        return $this->serviceInstance->config['multiple_warehouse'] ?? false;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function useSingleWarehouseInCart(): bool
    {
        return $this->serviceInstance->config['use_single_warehouse_cart'] ?? false;
    }

    /**
     * This function will check if the ERP can be enabled
     */
    public function enabled(): bool
    {
        return $this->serviceInstance->config['enabled'] ?? false;
    }

    public function adapter(): mixed
    {
        return $this->serviceInstance->adapter;
    }

    /*
    |--------------------------------------------------------------------------
    | GENERAL FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * @throws \ErrorException
     */
    public function getCustomerDetail(array $filters = []): Customer
    {
        if (!empty($filters['customer_number'])) {
            return $this->__call(__FUNCTION__, [$filters]);
        }

        $customer_number = customer_check() ? customer()->erp_id : config('amplify.frontend.guest_default');
        $filters['customer_number'] = $customer_number;

        return Cache::remember(
            "getCustomerDetails-{$customer_number}",
            2 * HOUR,
            fn() => $this->__call('getCustomerDetail', [$filters])
        );
    }

    /**
     * @throws \ErrorException
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection
    {
        if (!empty($filters['customer_number'])) {
            return $this->__call(__FUNCTION__, [$filters]);
        }

        $customer_number = customer_check() ? customer()->erp_id : config('amplify.frontend.guest_default');
        $filters['customer_number'] = $customer_number;

        return Cache::remember(
            "getCustomerShippingLocationList-{$customer_number}",
            2 * HOUR,
            fn() => $this->__call('getCustomerShippingLocationList', [$filters])
        );
    }
}
