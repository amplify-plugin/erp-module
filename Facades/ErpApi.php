<?php

namespace Amplify\ErpApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Amplify\ErpApi\ErpApiService
 *
 * @method static \Amplify\ErpApi\ErpApiService init(string $adapter = null)
 * @method static bool allowMultiWarehouse()
 * @method static bool useSingleWarehouseInCart()
 * @method static bool enabled()
 * @method static \Amplify\ErpApi\Interfaces\ErpApiInterface adapter()
 * @method static \Amplify\ErpApi\Wrappers\CreateCustomer createCustomer(array $attributes = [])
 * @method static \Amplify\ErpApi\Collections\CustomerCollection getCustomerList(array $filters = ['customer_start' => null, 'customer_end' => null])
 * @method static \Amplify\ErpApi\Wrappers\Customer getCustomerDetail(array $filters = ['customer_number' => null])
 * @method static \Amplify\ErpApi\Wrappers\ShippingLocationValidation validateCustomerShippingLocation(array $attributes = [])
 * @method static \Amplify\ErpApi\Wrappers\ShippingLocation createCustomerShippingLocation(array $filters = ['customer_number' => null])
 * @method static \Amplify\ErpApi\Collections\ShippingLocationCollection getCustomerShippingLocationList(array $filters = ['customer_number' => null])
 * @method static \Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection getProductPriceAvailability(array $filters = ['items' => [], 'warehouse' => null, 'customer_number' => null])
 * @method static \Amplify\ErpApi\Wrappers\Order createOrder(array $orderInfo = [])
 * @method static \Amplify\ErpApi\Collections\OrderCollection getOrderList(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\Order getOrderDetail(array $orderInfo = [])
 * @method static \Amplify\ErpApi\Wrappers\OrderTotal getOrderTotal(array $orderInfo = [])
 * @method static \Amplify\ErpApi\Collections\CreateQuotationCollection createQuotation(array $orderInfo = [])
 * @method static \Amplify\ErpApi\Collections\QuotationCollection getQuotationList(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\Quotation getQuotationDetail(array $orderInfo = [])
 * @method static \Amplify\ErpApi\Wrappers\CustomerAR getCustomerARSummary(array $filters = [])
 * @method static \Amplify\ErpApi\Collections\InvoiceCollection getInvoiceList(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\Invoice getInvoiceDetail(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\CreatePayment createPayment(array $paymentInfo = [])
 * @method static \Amplify\ErpApi\Wrappers\CreateOrUpdateNote createOrUpdateNote(array $noteInfo = [])
 * @method static \Amplify\ErpApi\Collections\ProductSyncCollection getProductSync(array $filters = ['item_start' => '', 'item_end' => '', 'updates_only' => 'N', 'process_updates' => 'N', 'limit' => '', 'restart_point' => ''])
 * @method static \Amplify\ErpApi\Collections\WarehouseCollection getWarehouses(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\ContactValidation contactValidation(array $inputs = [])
 * @method static \Amplify\ErpApi\Collections\ShippingOptionCollection getShippingOption(array $data = [])
 * @method static \Amplify\ErpApi\Collections\CampaignCollection getCampaignList(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\Campaign getCampaignDetail(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\Document getDocument(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\TermsType getTermsType(array $filters = [])
 * @method static \Amplify\ErpApi\Collections\PastItemCollection getPastItemList(array $filters = [])
 * @method static \Amplify\ErpApi\Wrappers\Contact createUpdateContact(array $attributes = [])
 * @method static \Amplify\ErpApi\Collections\ContactCollection getContactList(array $filters = ['customer_start' => null, 'customer_end' => null])
 * @method static \Amplify\ErpApi\Wrappers\Contact getContactDetail(array $filters = ['customer_number' => null])
 * @method static \Amplify\ErpApi\Wrappers\Contact createUpdateCustomerPartNumber(array $filters = [])
 *
 * @see \Amplify\ErpApi\ProductSyncService
 *
 * @method static array storeProductSyncOnModel(array $filters)
 * @method static void dispatchProductSyncJob($id, $approveId = null)
 * @method static void updateProductWithSyncData(\Amplify\System\Backend\Models\ProductSync $productSync, int $approveId = null)
 *
 * @method static void macro(string $name, object|callable $function)
 */
class ErpApi extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ErpApi';
    }
}
