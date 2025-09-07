<?php

namespace Amplify\ErpApi\Adapters;

use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\CampaignDetailCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CreateQuotationCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\OrderDetailCollection;
use Amplify\ErpApi\Collections\OrderNoteCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\QuotationCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Interfaces\ErpApiInterface;
use Amplify\ErpApi\Wrappers\Campaign;
use Amplify\ErpApi\Wrappers\CampaignDetail;
use Amplify\ErpApi\Wrappers\Contact;
use Amplify\ErpApi\Wrappers\ContactValidation;
use Amplify\ErpApi\Wrappers\CreateCustomer;
use Amplify\ErpApi\Wrappers\CreateOrUpdateNote;
use Amplify\ErpApi\Wrappers\CreatePayment;
use Amplify\ErpApi\Wrappers\CreateQuotation;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\ErpApi\Wrappers\CustomerAR;
use Amplify\ErpApi\Wrappers\Document;
use Amplify\ErpApi\Wrappers\Invoice;
use Amplify\ErpApi\Wrappers\Order;
use Amplify\ErpApi\Wrappers\OrderDetail;
use Amplify\ErpApi\Wrappers\OrderNote;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\ProductPriceAvailability;
use Amplify\ErpApi\Wrappers\ProductSync;
use Amplify\ErpApi\Wrappers\Quotation;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\ErpApi\Wrappers\ShippingOption;
use Amplify\ErpApi\Wrappers\TrackShipment;
use Amplify\ErpApi\Wrappers\Warehouse;
use App\Models\ProductAvailability;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DefaultErpAdapter implements ErpApiInterface
{
    use \Amplify\ErpApi\Traits\ManageDocumentTrait;

    /*
    |--------------------------------------------------------------------------
    | UTILITY FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This function will check if the ERP can be enabled
     */
    public function enabled(): bool
    {
        return true;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function allowMultiWarehouse(): bool
    {
        return true;
    }

    /**
     * This function will return the ERP Carrier code options
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $collection = new ShippingOptionCollection;

        foreach ($data as $datum) {
            $model = new ShippingOption($datum);
            $model->InternalId = $datum['id'];
            $model->CarrierCode = $datum['code'];
            $model->CarrierDescription = $datum['name'];
            $model->Driver = $datum['driver'];

            $collection->push($model);
        }

        return $collection;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     */
    public function createCustomer(array $attributes = []): CreateCustomer
    {
        $model = new CreateCustomer($attributes);

        if (! empty($attributes)) {
            $attributes = $attributes['CashCustomer'] ?? [];
            $model->NewAccountNumber = $attributes['NewAccountNumber'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerList(array $customers = []): CustomerCollection
    {
        $customerList = new CustomerCollection;

        if (! empty($customers)) {
            foreach (($customers ?? []) as $customer) {
                $customerList->push($this->renderSingleCustomer($customer));
            }
        }

        return $customerList;
    }

    /**
     * This API is to get customer entity information from the FACTS ERP
     */
    public function getCustomerDetail(array $customer = []): Customer
    {
        return $this->renderSingleCustomer($customer);
    }

    /**
     * This API is to get customer ship to locations entity information from the FACTS ERP
     */
    public function getCustomerShippingLocationList(array $locations = []): ShippingLocationCollection
    {
        $customerShippingLocations = new ShippingLocationCollection;

        if (! empty($locations)) {
            foreach ($locations as $location) {
                $customerShippingLocations->push($this->renderSingleCustomerShippingLocation($location));
            }
        }

        return $customerShippingLocations;
    }

    private function renderSingleCustomer(array $attributes): Customer
    {
        $model = new Customer($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['customer_code'] ?? null;
            $model->ArCustomerNumber = $attributes['ar_number'] ?? null;
            $model->CustomerName = $attributes['customer_name'] ?? null;

            $model->CustomerCountry = $attributes['CustomerCountry'] ?? null;
            $model->CustomerAddress1 = $attributes['CustomerAddress1'] ?? null;
            $model->CustomerAddress2 = $attributes['CustomerAddress2'] ?? null;
            $model->CustomerAddress3 = $attributes['CustomerAddress3'] ?? null;
            $model->CustomerCity = $attributes['CustomerCity'] ?? null;
            $model->CustomerState = $attributes['CustomerState'] ?? null;
            $model->CustomerZipCode = $attributes['CustomerZipCode'] ?? null;

            $model->CustomerEmail = $attributes['email'] ?? null;
            $model->CustomerPhone = $attributes['phone'] ?? null;

            $model->CustomerContact = $attributes['business_contact'] ?? null;
            $model->DefaultShipTo = $attributes['shipto_address_code'] ?? null;

            $model->DefaultWarehouse = isset($attributes['warehouse']) ? $attributes['warehouse']['code'] : $attributes['warehouse_seq_code'];

            $model->CarrierCode = $attributes['carrier_code'] ?? null;
            $model->PriceList = $attributes['PriceList'] ?? null;
            $model->BackorderCode = $attributes['allow_backorder'] ? 'Y' : 'N';
            $model->CustomerClass = $attributes['class'] ?? null;

            $model->SuspendCode = $attributes['is_suspended'] ? 'Y' : 'N';

            $model->AllowArPayments = $attributes['AllowArPayments'] ?? null;
            $model->CreditCardOnly = $attributes['credit_card_only'] ? 'Y' : 'N';

            $model->FreightOptionAmount = $attributes['free_shipment_amount'] ?? null;

            $model->PoRequired = $attributes['customer_po_required'] ? 'Y' : 'N';

            $model->SalesPersonCode = $attributes['SalesPersonCode'] ?? null;
            $model->SalesPersonName = $attributes['SalesPersonName'] ?? null;
            $model->SalesPersonEmail = $attributes['SalesPersonEmail'] ?? null;
            $model->WrittenIndustry = $attributes['WrittenIndustry'] ?? null;
            $model->OTShipPrice = $attributes['OTShipPrice'] ?? null;
        }

        return $model;
    }

    public function renderSingleCustomerShippingLocation($attributes): ShippingLocation
    {
        $model = new ShippingLocation($attributes);

        if (! empty($attributes)) {
            $model->ShipToNumber = $attributes['address_code'] ?? null;
            $model->ShipToName = $attributes['address_name'] ?? null;
            $model->ShipToCountryCode = $attributes['country_code'] ?? null;
            $model->ShipToAddress1 = $attributes['address_1'] ?? null;
            $model->ShipToAddress2 = $attributes['address_2'] ?? null;
            $model->ShipToAddress3 = $attributes['address_3'] ?? null;
            $model->ShipToCity = $attributes['city'] ?? null;
            $model->ShipToState = $attributes['state'] ?? null;
            $model->ShipToZipCode = $attributes['zip_code'] ?? null;
            $model->ShipToPhoneNumber = $attributes['ShipToPhoneNumber'] ?? null;
            $model->ShipToContact = $attributes['ShipToContact'] ?? null;
            $model->ShipToWarehouse = $attributes['ShipToWarehouse'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->PoRequired = $attributes['PoRequired'] ?? null;
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get item details with pricing and availability for the given warehouse location ID
     */
    public function getProductPriceAvailability(array $items = []): ProductPriceAvailabilityCollection
    {
        $collection = new ProductPriceAvailabilityCollection;

        if (! empty($items)) {
            foreach (($items ?? []) as $item) {
                $collection->push($this->renderSingleProductPriceAvailability($item));
            }
        }

        return $collection;
    }

    private function renderSingleProductPriceAvailability($attributes): ProductPriceAvailability
    {
        $attributes = $attributes->toArray();
        $model = new ProductPriceAvailability($attributes);

        if (! empty($attributes)) {
            $model->ItemNumber = $attributes['item_number'] ?? null;
            $model->WarehouseID = $attributes['warehouse_id'] ?? null;
            $model->Price = $attributes['price'];
            // $model->QtyBreak_1 = 1;
            // $model->QtyPrice_1 = $attributes['price'];
            // $model->QtyBreak_2 = $attributes['quantity_available'] ?? null;
            // $model->QtyPrice_2 = $model->QtyPrice_1 - 5;
            $model->ListPrice = $attributes['list_price'] ?? null;
            $model->StandardPrice = $attributes['list_price'] ?? null;
            $model->ExtendedPrice = $attributes['list_price'] ?? null;
            $model->OrderPrice = $attributes['price'];
            $model->UnitOfMeasure = $attributes['unit_of_measure'] ?? 'EA';
            $model->PricingUnitOfMeasure = ucwords(strtolower($attributes['pricing_unit_of_measure'] ?? 'EA'));
            $model->DefaultSellingUnitOfMeasure = $attributes['default_selling_unit_of_measure'] ?? 'EA';
            $model->AverageLeadTime = $attributes['average_lead_time'] ?? null;
            $model->QuantityAvailable = $attributes['quantity_available'] ?? null;
            $model->QuantityOnOrder = $attributes['quantity_on_order'] ?? null;
            $model->Warehouses = ErpApi::getWarehouses()->first(fn ($warehouse) => $warehouse->WarehouseNumber == $model->WarehouseID);
        }

        return $model;
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        $model = new ProductSyncCollection;

        // Mapping
        if (! empty($filters)) {
            foreach (($filters ?? []) as $item) {
                $model->push($this->renderProductSync($item));
            }
        }

        return $model;
    }

    private function renderProductSync($attributes): ProductSync
    {
        $model = new ProductSync($attributes);

        $model->ItemNumber = $attributes['item_number'] ?? null;
        $model->UpdateAction = $attributes['update_action'] ?? null;
        $model->Description1 = $attributes['description_1'] ?? null;
        $model->Description2 = $attributes['description_2'] ?? null;
        $model->ItemClass = $attributes['item_class'] ?? null;
        $model->PriceClass = $attributes['price_class'] ?? null;
        $model->ListPrice = $attributes['list_price'] ?? null;
        $model->UnitOfMeasure = $attributes['unit_of_measure'] ?? null;
        $model->PricingUnitOfMeasure = $attributes['pricing_unit_of_measure'] ?? null;
        $model->Manufacturer = $attributes['manufacturer'] ?? null;
        $model->PrimaryVendor = $attributes['primary_vendor'] ?? null;
        $model->payload = $attributes['payload'] ?? null;
        $model->is_processed = $attributes['is_processed'] ?? null;

        return $model;
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        $warehouseCollection = new WarehouseCollection;

        foreach ($filters as $warehouse) {
            $warehouseCollection->push($this->renderSingleWarehouse($warehouse));
        }

        return $warehouseCollection;
    }

    private function renderSingleWarehouse($warehouse): Warehouse
    {
        $model = new Warehouse($warehouse);

        $model->InternalId = $warehouse['id'] ?? null;
        $model->WarehouseNumber = $warehouse['code'] ?? null;
        $model->WarehouseName = $warehouse['name'] ?? null;
        $model->WarehousePhone = $warehouse['telephone'] ?? null;
        $model->WarehouseZip = $warehouse['zip_code'] ?? null;
        $model->WarehouseAddress = $warehouse['address'] ?? null;
        $model->WarehouseEmail = $warehouse['email'] ?? null;

        $model->WhsSeqCode = null;
        $model->CompanyNumber = null;
        $model->WhPricingLevel = $warehouse['WhPricingLevel'] ?? null;

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create an order in the FACTS ERP
     */
    public function createOrder(array $orderInfo = []): Order
    {
        return $this->renderSingleCreateOrder($orderInfo);
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $customerOrders = []): OrderCollection
    {
        $customerOrders = array_shift($customerOrders);

        $orders = new OrderCollection;
        if (! empty($customerOrders)) {
            foreach ($customerOrders as $order) {
                $orders->push($this->renderSingleOrder($order));
            }
        }

        return $orders;
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        return $this->renderSingleOrder($orderInfo[0]);
    }

    public function getQuotationDetail(array $orderInfo = []): Quotation
    {
        return $this->renderSingleQuotation($orderInfo);
    }

    private function renderSingleCreateOrder($attributes): Order
    {
        $model = new Order($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['customer_number'] ?? null;
            $model->OrderNumber = $attributes['OrderNumber'] ?? null;
            $model->OrderSuffix = $attributes['OrderSuffix'] ?? null;
            $model->OrderType = $attributes['order_type'] ?? null;

            $model->OrderStatus = 'Accepted';
            $model->CustomerName = $attributes['customer_name'] ?? null;
            $model->BillToCountry = $attributes['ship_to_country_code'] ?? null;
            $model->CustomerAddress1 = $attributes['ship_to_address1'] ?? null;

            $model->CustomerAddress2 = $attributes['ship_to_address2'] ?? null;
            $model->CustomerAddress3 = $attributes['ship_to_address3'] ?? null;
            $model->BillToCity = $attributes['ship_to_city'] ?? null;
            $model->BillToState = $attributes['ship_to_state'] ?? null;
            $model->BillToZipCode = $attributes['ship_to_zip_code'] ?? null;

            $model->BillToContact = customer(true)->name ?? null;
            $model->ShipToNumber = $attributes['ship_to_number'] ?? null;
            $model->ShipToName = $attributes['ship_to_name'] ?? null;
            $model->ShipToCountry = $attributes['ship_to_country_code'] ?? null;
            $model->ShipToAddress1 = $attributes['ship_to_address1'] ?? null;
            $model->ShipToAddress2 = $attributes['ship_to_address2'] ?? null;
            $model->ShipToAddress3 = $attributes['ship_to_address3'] ?? null;
            $model->ShipToCity = $attributes['ship_to_city'] ?? null;

            $model->ShipToState = $attributes['ship_to_state'] ?? null;
            $model->ShipToZipCode = $attributes['ship_to_zip_code'] ?? null;
            $model->ShipToContact = customer(true)->name ?? null;
            $model->EntryDate = Carbon::now() ?? null;
            $model->RequestedShipDate = Carbon::now()->addDays(7) ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['CustomerPurchaseOrdernumber'] ?? null;
            $model->ItemSalesAmount = $attributes['ItemSalesAmount'] ?? null;
            $model->DiscountAmountTrading = $attributes['DiscountAmountTrading'] ?? null;
            $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
            $model->InvoiceAmount = $attributes['InvoiceAmount'] ?? null;
            $model->FreightAmount = $attributes['FreightAmount'] ?? null;
            $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
            $model->TotalSpecialCharges = $attributes['TotalSpecialCharges'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->WarehouseID = $attributes['WarehouseID'] ?? null;
            $model->InvoiceNumber = $attributes['InvoiceNumber'] ?? null;
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->BillToCountryName = $attributes['BillToCountryName'] ?? null;
            $model->ShipToCountryName = $attributes['ShipToCountryName'] ?? null;
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? null;
            $model->OrderDetail = new OrderDetailCollection;

            if (! empty($attributes['OrderDetail'])) {
                foreach (($attributes['OrderDetail'] ?? []) as $orderDetail) {
                    $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }
        }

        return $model;
    }

    public function createQuotation(array $orderInfo = []): CreateQuotationCollection
    {
        $quoteCollection = new CreateQuotationCollection;

        $items = $orderInfo['items'] ?? [];
        $total_price = 0;
        foreach ($items as $item) {
            $product = ProductAvailability::where('item_number', $item['ItemNumber'])
                ->when($item['WarehouseID'], fn ($q) => $q->where('warehouse_id', $item['WarehouseID']))
                ->first();

            $product_price = customer_check() ? $product->price : ($product->list_price ?? $product->price);
            $total_price += ($product_price * $item['OrderQty']) ?? 0;
        }

        $price_list = [
            'OrderNumber' => 0,
            'TotalOrderValue' => $total_price,
            'SalesTaxAmount' => 0,
            'FreightAmount' => 0,
        ];
        $quoteCollection->push($this->renderSingleCreateQuotation($price_list));

        return $quoteCollection;

    }

    private function renderSingleCreateQuotation($attributes): CreateQuotation
    {
        $model = new CreateQuotation($attributes);
        if (! empty($attributes['TotalOrderValue'])) {
            $model->OrderNumber = floatval($attributes['OrderNumber'] ?? '');
            $model->SalesTaxAmount = floatval($attributes['SalesTaxAmount'] ?? '');
            $model->FreightAmount = floatval($attributes['FreightAmount'] ?? '');
            $model->TotalOrderValue = floatval($attributes['TotalOrderValue'] ?? '');
        }

        return $model;
    }

    private function renderSingleOrder($order): Order
    {
        $model = new Order($order->toArray());

        if (! empty($order) && isset($order->customer)) {
            $model->ContactId = null;
            $model->OrderNumber = $order->id ?? null;
            $model->OrderType = $order->order_type === 0 ? 'ORDER' : 'QUOTE';
            $model->OrderStatus = $order->order_status ?? null;
            $model->OrderSuffix = config('amplify.basic.web_order_prefix');
            $model->CustomerNumber = $order->customer->customer_code ?? null;
            $model->CustomerName = $order->customer->customer_name ?? null;
            $model->CustomerAddress1 = $order->customer->address ? $order->customer->addresses[0]->address_1 : null;
            $model->CustomerAddress2 = $order->customer->address ? $order->customer->addresses[0]->address_2 : null;
            $model->CustomerAddress3 = $order->customer->address ? $order->customer->addresses[0]->address_3 : null;
            $model->BillToCountry = $order->customer->address ? $order->customer->addresses[0]->country_code : null;
            $model->BillToCity = $order->customer->address ? $order->customer->addresses[0]->city : null;
            $model->BillToState = $order->customer->address ? $order->customer->addresses[0]->state : null;
            $model->BillToZipCode = $order->customer->address ? $order->customer->addresses[0]->zip_code : null;
            $model->BillToContact = $order->customer->business_contact ?? null;
            $model->ShipToNumber = $order->customer->shipto_address_code ?? null;
            $model->ShipToName = $order->customer->address ? $order->customer->addresses[0]->address_name : null;
            $model->ShipToCountry = $order->customer->address ? $order->customer->addresses[0]->country_code : null;
            $model->ShipToAddress1 = $order->customer->address ? $order->customer->addresses[0]->address_1 : null;
            $model->ShipToAddress2 = $order->customer->address ? $order->customer->addresses[0]->address_2 : null;
            $model->ShipToAddress3 = $order->customer->address ? $order->customer->addresses[0]->address_3 : null;
            $model->ShipToCity = $order->customer->address ? $order->customer->addresses[0]->city : null;
            $model->ShipToState = $order->customer->address ? $order->customer->addresses[0]->state : null;
            $model->ShipToZipCode = $order->customer->address ? $order->customer->addresses[0]->zip_code : null;
            $model->ShipToContact = $order->customer->business_contact ?? null;
            $model->EntryDate = $order->invoice->entry_date ?? null;
            $model->RequestedShipDate = $order->submitted_at ?? null;
            $model->CustomerPurchaseOrdernumber = $order->invoice->customer_po_number ?? null;
            $model->ItemSalesAmount = $order->total_net_price ?? $order->orderLines()->sum('line_total');
            $model->SalesTaxAmount = $order->total_tax_amount ?? null;
            $model->FreightAmount = $order->total_shipping_cost ?? $order->orderLines()->sum('shipping_cost');
            $model->InvoiceNumber = $order->invoice->invoice_number ?? null;
            $model->InvoiceAmount = $order->invoice->invoice_amount ?? null;
            $model->TotalOrderValue = $order->total_amount ?? $order->orderLines()->sum('line_total');
            $model->CarrierCode = $order->customer->carrier_code ?? null;
            $model->WarehouseID = $order->customer->warehouse_id ?? null;
            $model->EmailAddress = $order->email ?? null;
            $model->BillToCountryName = $order->customer->address ? $order->customer->address[0]->country_code : null;
            $model->ShipToCountryName = $order->customer->address ? $order->customer->address[0]->country_code : null;

            $model->PdfAvailable = 'Yes';
            $model->SignedDoc = 'No';
            $model->SignedType = 'I';
            $model->DiscountAmountTrading = null;
            $model->TotalSpecialCharges = null;
            $model->OrderDetail = new OrderDetailCollection;
            $model->OrderNotes = new OrderNoteCollection;

            foreach ($order->orderLines as $orderDetail) {
                $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
            }

            foreach (($order->orderNotes ?? []) as $orderNote) {
                $model->OrderNotes->push($this->renderSingleOrderNote($orderNote));
            }
        }

        return $model;
    }

    private function renderSingleQuotation($attributes): Quotation
    {
        $model = new Quotation($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->ContactId = $attributes['ContactId'] ?? null;
            $model->QuoteNumber = $attributes['QuoteNumber'] ?? null;
            $model->QuoteType = $attributes['QuoteType'] ?? null;
            $model->CustomerName = $attributes['CustomerName'] ?? null;
            $model->BillToCountry = $attributes['BillToCountry'] ?? null;
            $model->CustomerAddress1 = $attributes['CustomerAddress1'] ?? null;
            $model->CustomerAddress2 = $attributes['CustomerAddress2'] ?? null;
            $model->CustomerAddress3 = $attributes['CustomerAddress3'] ?? null;
            $model->BillToCity = $attributes['BillToCity'] ?? null;
            $model->BillToState = $attributes['BillToState'] ?? null;
            $model->BillToZipCode = $attributes['BillToZipCode'] ?? null;
            $model->BillToContact = $attributes['BillToContact'] ?? null;
            $model->ShipToNumber = $attributes['ShipToNumber'] ?? null;
            $model->ShipToName = $attributes['ShipToName'] ?? null;
            $model->ShipToCountry = $attributes['ShipToCountry'] ?? null;
            $model->ShipToAddress1 = $attributes['ShipToAddress1'] ?? null;
            $model->ShipToAddress2 = $attributes['ShipToAddress2'] ?? null;
            $model->ShipToAddress3 = $attributes['ShipToAddress3'] ?? null;
            $model->ShipToCity = $attributes['ShipToCity'] ?? null;
            $model->ShipToState = $attributes['ShipToState'] ?? null;
            $model->ShipToZipCode = $attributes['ShipToZipCode'] ?? null;
            $model->ShipToContact = $attributes['ShipToContact'] ?? null;
            $model->EntryDate = $attributes['EntryDate'] ?? null;
            $model->EffectiveDate = $attributes['EffectiveDate'] ?? null;
            $model->ExpirationDate = $attributes['ExpirationDate'] ?? null;
            $model->RequestedShipDate = $attributes['RequestedShipDate'] ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['CustomerPurchaseOrdernumber'] ?? null;
            $model->ItemSalesAmount = $attributes['ItemSalesAmount'] ?? null;
            $model->DiscountAmountTrading = $attributes['DiscountAmountTrading'] ?? null;
            $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
            $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
            $model->TotalSpecialCharges = $attributes['TotalSpecialCharges'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->WarehouseID = $attributes['WarehouseID'] ?? null;
            $model->QuoteAmount = $attributes['QuoteAmount'] ?? null;
            $model->EmailAddress = $attributes['EmailAddress'] ?? null;
            $model->BillToCountryName = $attributes['BillToCountryName'] ?? null;
            $model->ShipToCountryName = $attributes['ShipToCountryName'] ?? null;
            $model->PdfAvailable = $attributes['PdfAvailable'] ?? null;
            $model->QuoteDetail = new OrderDetailCollection;
            $model->QuotedTo = $attributes['QuotedTo'] ?? null;

            if (! empty($attributes['QuoteDetail'])) {
                foreach (($attributes['QuoteDetail'] ?? []) as $orderDetail) {
                    $model->QuoteDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }
        }

        return $model;
    }

    private function renderSingleOrderDetail($orderItem): OrderDetail
    {
        $model = new OrderDetail($orderItem?->toArray() ?? []);

        if (! empty($orderItem)) {
            $orderItem->backpackProduct;
            $model->LineNumber = $orderItem->product_id ?? null;
            $model->ItemNumber = $orderItem->product_code ?? null;
            $model->ItemType = $orderItem->ItemType ?? null;
            $model->ItemDescription1 = $orderItem->backpackProduct?->product_name ?? null;
            $model->ItemDescription2 = $orderItem->backpackProduct?->description ?? null;
            $model->QuantityOrdered = $orderItem->qty ?? null;
            $model->QuantityShipped = $orderItem->qty ?? null;
            $model->QuantityBackordered = null;
            $model->UnitOfMeasure = $orderItem->unit_code ?? null;
            $model->PricingUM = $orderItem->unit_code ?? null;
            $model->ActualSellPrice = $orderItem->customer_price ?? null;
            $model->TotalLineAmount = $orderItem->line_total ?? null;
            $model->ShipWhse = $orderItem->warehouse->code ?? null;
            $model->ConvertedToOrder = 'Yes';
        }

        return $model;
    }

    private function renderSingleOrderNote($attributes): OrderNote
    {
        $attributes = $attributes->toArray();
        $model = new OrderNote($attributes);

        if (! empty($attributes)) {
            $model->Subject = $attributes['subject'] ?? null;
            $model->Date = isset($attributes['date']) ? CarbonImmutable::parse($attributes['date']) : null;
            $model->NoteNum = $attributes['id'] ?? null;
            $model->Type = $attributes['type'] ?? null;
            $model->Editable = $attributes['Editable'] ?? null;
            $model->Note = $attributes['note'] ?? null;
        }

        return $model;

    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get customer Accounts Receivables information from the FACTS ERP
     */
    public function getCustomerARSummary(array $attributes = []): CustomerAR
    {
        $model = new CustomerAR($attributes);

        if (! empty($attributes)) {
            $attributes = $attributes['ARSummary'] ?? [];

            $model->CustomerNum = $attributes['CustomerNum'] ?? null;
            $model->CustomerName = $attributes['CustomerName'] ?? null;
            $model->Address1 = $attributes['Address1'] ?? null;
            $model->Address2 = $attributes['Address2'] ?? null;
            $model->City = $attributes['City'] ?? null;
            $model->ZipCode = $attributes['ZipCode'] ?? null;
            $model->State = $attributes['State'] ?? null;
            $model->AgeDaysPeriod1 = $attributes['AgeDaysPeriod1'] ?? null;
            $model->AgeDaysPeriod2 = $attributes['AgeDaysPeriod2'] ?? null;
            $model->AgeDaysPeriod3 = $attributes['AgeDaysPeriod3'] ?? null;
            $model->AgeDaysPeriod4 = $attributes['AgeDaysPeriod4'] ?? null;
            $model->AmountDue = $attributes['AmountDue'] ?? null;
            $model->BillingPeriodAmount = $attributes['BillingPeriodAmount'] ?? null;
            $model->DateOfFirstSale = $attributes['DateOfFirstSale'] ?? null;
            $model->DateOfLastPayment = $attributes['DateOfLastPayment'] ?? null;
            $model->DateOfLastSale = $attributes['DateOfLastSale'] ?? null;
            $model->FutureAmount = $attributes['FutureAmount'] ?? null;
            $model->OpenOrderAmount = $attributes['OpenOrderAmount'] ?? null;
            $model->SalesLastYearToDate = $attributes['SalesLastYearToDate'] ?? null;
            $model->SalesMonthToDate = $attributes['SalesMonthToDate'] ?? null;
            $model->SalesYearToDate = $attributes['SalesYearToDate'] ?? null;
            $model->TermsCode = $attributes['TermsCode'] ?? null;
            $model->TermsDescription = $attributes['TermsDescription'] ?? null;
            $model->TradeAgePeriod1Amount = $attributes['TradeAgePeriod1Amount'] ?? null;
            $model->TradeAgePeriod2Amount = $attributes['TradeAgePeriod2Amount'] ?? null;
            $model->TradeAgePeriod3Amount = $attributes['TradeAgePeriod3Amount'] ?? null;
            $model->TradeAgePeriod4Amount = $attributes['TradeAgePeriod4Amount'] ?? null;
            $model->TradeAmountDue = $attributes['TradeAmountDue'] ?? null;
            $model->TradeBillingPeriodAmount = $attributes['TradeBillingPeriodAmount'] ?? null;
            $model->AvgDaysToPay1 = $attributes['AvgDaysToPay1'] ?? null;
            $model->AvgDaysToPay1Wgt = $attributes['AvgDaysToPay1Wgt'] ?? null;
            $model->AvgDaysToPay2 = $attributes['AvgDaysToPay2'] ?? null;
            $model->AvgDaysToPay2Wgt = $attributes['AvgDaysToPay2Wgt'] ?? null;
            $model->AvgDaysToPay3 = $attributes['AvgDaysToPay3'] ?? null;
            $model->AvgDaysToPay3Wgt = $attributes['AvgDaysToPay3Wgt'] ?? null;
            $model->AvgDaysToPayDesc1 = $attributes['AvgDaysToPayDesc1'] ?? null;
            $model->AvgDaysToPayDesc2 = $attributes['AvgDaysToPayDesc2'] ?? null;
            $model->AvgDaysToPayDesc3 = $attributes['AvgDaysToPayDesc3'] ?? null;
            $model->CreditCheckType = $attributes['CreditCheckType'] ?? null;
            $model->CreditLimit = $attributes['CreditLimit'] ?? null;
            $model->HighBalance = $attributes['HighBalance'] ?? null;
            $model->LastPayAmount = $attributes['LastPayAmount'] ?? null;
            $model->NumInvPastDue = $attributes['NumInvPastDue'] ?? null;
            $model->NumOpenInv = $attributes['NumOpenInv'] ?? null;
            $model->NumPayments1 = $attributes['NumPayments1'] ?? null;
            $model->NumPayments2 = $attributes['NumPayments2'] ?? null;
            $model->NumPayments3 = $attributes['NumPayments3'] ?? null;
            $model->TradeAgePeriod1Text = $attributes['TradeAgePeriod1Text'] ?? null;
            $model->TradeAgePeriod2Text = $attributes['TradeAgePeriod2Text'] ?? null;
            $model->TradeAgePeriod3Text = $attributes['TradeAgePeriod3Text'] ?? null;
            $model->TradeAgePeriod4Text = $attributes['TradeAgePeriod4Text'] ?? null;
            $model->TradeBillingPeriodText = $attributes['TradeBillingPeriodText'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer Accounts Receivables Open Invoices data from the FACTS ERP
     */
    public function getInvoiceList(array $attributes = []): InvoiceCollection
    {
        $invoiceList = new InvoiceCollection;
        if (! empty($attributes)) {
            foreach ($attributes as $invoice) {
                $invoiceList->push($this->renderSingleInvoice($invoice));
            }
        }

        return $invoiceList;
    }

    /**
     * This API is to get customer AR Open Invoice data from the FACTS ERP.
     */
    public function getInvoiceDetail(array $filters = []): Invoice
    {
        return $this->renderSingleInvoice($filters);
    }

    private function renderSingleInvoice($attributes): Invoice
    {
        $model = new Invoice($attributes);
        if (! empty($attributes)) {
            $model->AllowArPayments = $attributes['allow_ar_payments'] ?? 'No';
            $model->InvoiceNumber = $attributes['invoice_number'] ?? null;
            $model->InvoiceStatus = $attributes['invoice_status'] ?? null;
            $model->InvoiceType = $attributes['invoice_type'] ?? 'OA';
            $model->InvoiceDisputeCode = $attributes['invoice_dispute_code'] ?? 'N';
            $model->FinanceChargeFlag = $attributes['finance_charge_flag'] ?? 'N';
            $model->InvoiceDate = $attributes['invoice_date'] ?? null;
            $model->AgeDate = $attributes['age_date'] ?? null;
            $model->EntryDate = $attributes['entry_date'] ?? null;
            $model->InvoiceAmount = $attributes['invoice_amount'] ?? null;
            $model->InvoiceBalance = $attributes['invoice_balance'] ?? null;
            $model->PendingPayment = $attributes['pending_payment'] ?? null;
            $model->DiscountAmount = $attributes['discount_amount'] ?? null;
            $model->DiscountDueDate = $attributes['discount_due_date'] ?? null;
            $model->LastTransactionDate = $attributes['last_transaction_date'] ?? null;
            $model->PayDays = $attributes['pay_days'] ?? null;
            $model->HasInvoiceDetail = $attributes['has_invoice_detail'] ?? 'No';
            $model->OrderNumber = $attributes['order_number'] ?? null;
            $model->CustomerPONumber = $attributes['customer_po_number'] ?? null;
            $model->InvoiceDetail = new OrderCollection;

            if (isset($attributes['order'])) {
                $model->InvoiceDetail = $this->getOrderList([[$attributes['order']]]);
            }
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment
    {
        $model = new CreatePayment($paymentInfo);

        if (! empty($paymentInfo['ArPayment'])) {
            $attributes = $paymentInfo['ArPayment'] ?? [];

            $model->AuthorizationNumber = $attributes['AuthorizationNumber'] ?? null;
            $model->OnAccountDocument = $attributes['OnAccountDocument'] ?? null;
            $model->OnAccountAmount = $attributes['OnAccountAmount'] ?? null;
            $model->Receipt = $attributes['Receipt'] ?? null;
            $model->DistributedAmount = $attributes['DistributedAmount'] ?? null;
            $model->Message = $attributes['Message'] ?? null;
            $model->Token = $attributes['Token'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to create a order note.
     */
    public function createOrUpdateNote(array $noteInfo = []): CreateOrUpdateNote
    {
        $model = new CreateOrUpdateNote($noteInfo);

        $model->Status = 'Complete';
        $model->NoteNum = $noteInfo['id'];

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGN FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getCampaignList(array $attributes = []): CampaignCollection
    {
        $campaignList = new CampaignCollection;

        if (! empty($attributes)) {
            foreach (($attributes['ItemPromoHeader'] ?? []) as $campaign) {
                $campaignList->push($this->renderSingleCampaign($campaign));
            }
        }

        return $campaignList;
    }

    public function getCampaignDetail(array $data = []): Campaign
    {
        $data = isset($data['ItemPromoHeader'])
            ? array_shift($data['ItemPromoHeader'])
            : [];

        return $this->renderSingleCampaign($data);
    }

    private function renderSingleCampaign($attributes): Campaign
    {
        $model = new Campaign($attributes);

        if (! empty($attributes)) {
            $model->Promoid = $attributes['Promoid'] ?? null;
            $model->BegDate = $attributes['BegDate'] ?? null;
            $model->EndDate = $attributes['EndDate'] ?? null;
            $model->ShortDesc = $attributes['ShortDesc'] ?? null;
            $model->Hashtag = $attributes['Hashtag'] ?? null;
            $model->LongDesc = $attributes['LongDesc'] ?? null;
            $model->ImagePath = $attributes['ImagePath'] ?? null;
            $model->Clearance = $attributes['Clearance'] ?? null;
            $model->Online = $attributes['Online'] ?? null;
            $model->Inside = $attributes['Inside'] ?? null;
            $model->Print = $attributes['Print'] ?? null;
            $model->Sort = $attributes['Sort'] ?? null;
            $model->Private = $attributes['Private'] ?? null;
            $model->CampaignDetail = new CampaignDetailCollection;

            if (! empty($attributes['ItemPromoDetails'])) {
                foreach (($attributes['ItemPromoDetails'] ?? []) as $campaignDetail) {
                    $model->CampaignDetail->push($this->renderSingleCampaignDetail($campaignDetail));
                }
            }
        }

        return $model;
    }

    private function renderSingleCampaignDetail($attributes): CampaignDetail
    {
        $model = new CampaignDetail($attributes);

        if (! empty($attributes)) {
            $model->Promoid = $attributes['Promoid'] ?? null;
            $model->Item = $attributes['Item'] ?? null;
            $model->ItemDescription = $attributes['ItemDescription'] ?? null;
            $model->ExtDescription = $attributes['ExtDescription'] ?? null;
            $model->Attributes = $attributes['Attributes'] ?? null;
            $model->ItemID = $attributes['ItemID'] ?? null;
            $model->ItemImagePath = $attributes['ItemImagePath'] ?? null;
            $model->Price = $attributes['Price'] ?? null;
            $model->PriceUM = $attributes['PriceUM'] ?? null;
            $model->Sort = $attributes['Sort'] ?? null;
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT VALIDATION FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function contactValidation(array $inputs = []): ContactValidation
    {
        $model = new ContactValidation($inputs);

        $model->ValidCombination = $inputs['ValidCombination'] ?? 'N';
        $model->CustomerNumber = $inputs['CustomerNumber'] ?? 'N';
        $model->ContactNumber = $inputs['ContactNumber'] ?? 'N';
        $model->EmailAddress = $inputs['EmailAddress'] ?? 'N';
        $model->DefaultWarehouse = $inputs['DefaultWarehouse'] ?? 'N';
        $model->DefaultShipTo = $inputs['DefaultShipTo'] ?? 'N';

        return $model;
    }

    public function getDocument(array $inputs = []): Document
    {
        return new Document($inputs);
    }

    public function getPastItemList(array $attributes = []): PastItemCollection
    {
        return new PastItemCollection;
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderTotal(array $orderInfo = []): OrderTotal
    {

        $attributes = isset($orderInfo['Order'])
            ? array_shift($orderInfo['Order'])
            : [];

        $model = new OrderTotal($attributes);

        $model->OrderNumber = $attributes['OrderNumber'] ?? null;
        $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
        $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
        $model->FreightAmount = $attributes['FreightAmount'] ?? null;
        $model->FreightRate = $attributes['FreightRate'] ?? null;

        return $model;
    }

    public function getTrackShipment(array $inputs = []): TrackShipmentCollection
    {
        $model = new TrackShipmentCollection;

        foreach (($attributes ?? []) as $trackingInfo) {
            $model->push($this->renderSingleTrackShipment($trackingInfo));
        }

        return $model;
    }

    /**
     * This API is to get details of a quotation, or list of orders from a date range
     */
    public function getQuotationList(array $filters = []): QuotationCollection
    {
        return new QuotationCollection;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create a new cash customer account
     *
     * @done untested
     *
     * @throws Exception
     *
     * @since 2024.12.8354871
     */
    public function createUpdateContact(array $attributes = []): Contact
    {
        return new Contact($attributes);
    }

    /**
     * This API is to get customer entity information from the CSD ERP
     *
     * @throws Exception
     *
     * @done
     *
     * @todo Adapter mapping pending
     *
     * @since 2024.12.8354871
     */
    public function getContactList(array $filters = []): ContactCollection
    {
        return new ContactCollection;
    }

    /**
     * This API is to get customer entity information from the CSD ERP
     *
     * @done
     *
     * @throws Exception
     *
     * @since 2024.12.8354871
     */
    public function getContactDetail(array $filters = []): Contact
    {
        return new Contact($filters);
    }

    public function renderSingleTrackShipment($attributes = []): TrackShipment
    {
        $model = new TrackShipment($attributes);

        $model->OrderNumber = $attributes['orderno'] ?? null;
        $model->TrackerNo = $attributes['trackerno'] ?? null;
        $model->ShipViaType = $attributes['shipviaty'] ?? null;

        return $model;
    }
}
