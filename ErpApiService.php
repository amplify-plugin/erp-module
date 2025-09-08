<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Traits\ErpApiConfigTrait;
use Amplify\System\Backend\Models\ProductSync;

class ErpApiService
{
    use ErpApiConfigTrait;
    use \Illuminate\Support\Traits\ForwardsCalls;
    use \Illuminate\Support\Traits\Macroable;

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

    /**
     * Any Class that's enable the ERP API interface
     *
     * @var mixed
     */
    protected $serviceInstance;

    /**
     * ErpApiService Constructor
     */
    public function __construct()
    {
        $adapter = config('amplify.erp.default', 'default');

        $this->init($adapter);
    }

    public function init(string $adapter = null): self
    {
        $adapter = $adapter ?? config('amplify.erp.default');

        if ($config = config("amplify.erp.configurations.{$adapter}")) {
            $this->serviceInstance = new $config['adapter'];

            return $this;

        } else {
            throw new \InvalidArgumentException("{$adapter} is a invalid adapter name");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

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

    /**
     * @throws \ErrorException
     */
    private function checkErpIsEnabled()
    {
        if (!$this->enabled()) {
            throw new \ErrorException(class_basename($this->serviceInstance) . ' is disabled.', 500);
        }
    }

    public function adapter(): mixed
    {
        return $this->serviceInstance->adapter;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT SYNCHRONIZATION SERVICE
    |--------------------------------------------------------------------------
    */
    private function productSyncInstance(): ProductSyncService
    {
        return new ProductSyncService;
    }

    /**
     * @throws \Exception
     */
    public function storeProductSyncOnModel(array $filters): array
    {
        return $this->productSyncInstance()->storeProductSyncOnModel($filters);
    }

    /**
     * @return void
     */
    public function dispatchProductSyncJob($id, $approveId = null)
    {
        $this->productSyncInstance()->dispatchProductSyncJob($id, $approveId);
    }

    /**
     * @return void
     */
    public function updateProductWithSyncData(ProductSync $productSync, ?int $approveId = null)
    {
        $this->productSyncInstance()->updateProductWithSyncData($productSync, $approveId);
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
     */
    public function __call($method, $parameters)
    {
        $this->checkErpIsEnabled();

        if (method_exists($this->serviceInstance, $method)) {
            return $this->forwardCallTo($this->serviceInstance, $method, $parameters);
        }

        if (!static::hasMacro($method)) {
            throw new \BadMethodCallException(sprintf(
                'Macro method %s::%s does not exist.', static::class, $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof \Closure) {
            $macro = $macro->bindTo($this->serviceInstance, static::class);
        }

        return $macro(...$parameters);
    }
}
