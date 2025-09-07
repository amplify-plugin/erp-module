<?php

namespace Amplify\ErpApi\Interfaces;

use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Wrappers\Campaign;
use Amplify\ErpApi\Wrappers\Contact;
use Amplify\ErpApi\Wrappers\ContactValidation;
use Amplify\ErpApi\Wrappers\CreateCustomer;
use Amplify\ErpApi\Wrappers\CreateOrUpdateNote;
use Amplify\ErpApi\Wrappers\CreatePayment;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\ErpApi\Wrappers\CustomerAR;
use Amplify\ErpApi\Wrappers\Document;
use Amplify\ErpApi\Wrappers\Invoice;
use Amplify\ErpApi\Wrappers\Order;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\Quotation;

interface ErpApiInterface
{
    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     */
    public function createCustomer(array $attributes = []): CreateCustomer|Customer;

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getCustomerList(array $filters = []): CustomerCollection;

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getCustomerDetail(array $filters = []): Customer;

    /**
     * This API is to get customer ship to locations entity
     * information from the ERP
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection;

    /*
    |--------------------------------------------------------------------------
    | PRODUCT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get item details with pricing and
     * availability for the given warehouse location ID
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection;

    /*
    |--------------------------------------------------------------------------
    | ORDER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create an order in the ERP
     */
    public function createOrder(array $orderInfo = []): Order;

    /**
     * This API is to get details of an order/invoice,
     * or list of orders from a date range
     */
    public function getOrderList(array $filters = []): OrderCollection;

    /**
     * This API is to get details of an order/invoice,
     * or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order;

    /**
     * This API is to get cost shipping method  of a cart items
     *
     * @return mixed
     */
    public function getOrderTotal(array $orderInfo = []): OrderTotal;

    /*
    |--------------------------------------------------------------------------
    | INVOICE FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get customer Accounts Receivables information from the ERP
     */
    public function getCustomerARSummary(array $filters = []): CustomerAR;

    /**
     * This API is to get customer Accounts Receivables
     * Open Invoices data from the ERP
     */
    public function getInvoiceList(array $filters = []): InvoiceCollection;

    /**
     * This API is to get customer AR Open Invoice data from the ERP.
     */
    public function getInvoiceDetail(array $filters = []): Invoice;

    /*
    |--------------------------------------------------------------------------
    | QUOTATION FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a quotation in the FACTS ERP
     */
    public function createQuotation(array $orderInfo = []): CreateQuotationCollection;

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationList(array $filters = []): QuotationCollection;

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationDetail(array $orderInfo = []): Quotation;

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create an AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment;

    /**
     * This API is to create an AR payment on the customers account.
     */
    public function createOrUpdateNote(array $noteInfo = []): CreateOrUpdateNote;

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGN FUNCTIONS
    |--------------------------------------------------------------------------
    */
    /**
     * This API is to get all future campaigns data from the ERP.
     */
    public function getCampaignList(array $filters = []): CampaignCollection;

    /**
     * This API is to get single campaign details and items
     * info from the ERP.
     */
    public function getCampaignDetail(array $filters = []): Campaign;

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This function will return the ERP Carrier Code Option
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection;

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getProductSync(array $filters = []): ProductSyncCollection;

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getWarehouses(array $filters = []): WarehouseCollection;

    /**
     * This function will check if the ERP customer and contact assign
     * validation combination
     */
    public function contactValidation(array $inputs = []): ContactValidation;

    /**
     * This API is to get customer required document from ERP
     */
    public function getDocument(array $inputs = []): Document;

    /**
     * This API is to get shipping tracking URL
     */
    public function getTrackShipment(array $inputs = []): TrackShipmentCollection;

    /**
     * This API is to get customer past sales items from the FACTS ERP
     */
    public function getPastItemList(array $filters = []): PastItemCollection;

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     */
    public function createUpdateContact(array $attributes = []): Contact;

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getContactList(array $filters = []): ContactCollection;

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getContactDetail(array $filters = []): Contact;
}
