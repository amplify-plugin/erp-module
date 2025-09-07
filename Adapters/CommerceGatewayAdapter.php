<?php

namespace Amplify\ErpApi\Adapters;

use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\CampaignDetailCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\OrderDetailCollection;
use Amplify\ErpApi\Collections\PastItemCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\TrackShipmentCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Interfaces\ErpApiInterface;
use Amplify\ErpApi\Wrappers\Campaign;
use Amplify\ErpApi\Wrappers\CampaignDetail;
use Amplify\ErpApi\Wrappers\Contact;
use Amplify\ErpApi\Wrappers\ContactValidation;
use Amplify\ErpApi\Wrappers\CreateCustomer;
use Amplify\ErpApi\Wrappers\CreateOrder;
use Amplify\ErpApi\Wrappers\CreateOrUpdateNote;
use Amplify\ErpApi\Wrappers\CreatePayment;
use Amplify\ErpApi\Wrappers\Customer;
use Amplify\ErpApi\Wrappers\CustomerAR;
use Amplify\ErpApi\Wrappers\Document;
use Amplify\ErpApi\Wrappers\Invoice;
use Amplify\ErpApi\Wrappers\Order;
use Amplify\ErpApi\Wrappers\OrderDetail;
use Amplify\ErpApi\Wrappers\OrderTotal;
use Amplify\ErpApi\Wrappers\ProductPriceAvailability;
use Amplify\ErpApi\Wrappers\ProductSync;
use Amplify\ErpApi\Wrappers\ShippingLocation;
use Amplify\ErpApi\Wrappers\ShippingOption;
use Amplify\ErpApi\Wrappers\TrackShipment;
use Amplify\ErpApi\Wrappers\Warehouse;

class CommerceGatewayAdapter implements ErpApiInterface
{
    use \Amplify\ErpApi\Traits\ManageDocumentTrait;

    public function createQuotation(array $orderInfo = []): \Amplify\ErpApi\Collections\CreateQuotationCollection
    {
        return new \Amplify\ErpApi\Collections\CreateQuotationCollection();
    }

    public function getQuotationDetail(array $orderInfo = []): \Amplify\ErpApi\Wrappers\Quotation
    {
        return new \Amplify\ErpApi\Wrappers\Quotation([]);
    }

    public function getQuotationList(array $filters = []): \Amplify\ErpApi\Collections\QuotationCollection
    {
        return new \Amplify\ErpApi\Collections\QuotationCollection();
    }

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
     * This API is to get customer entity information from the Commerce Gateway
     */
    public function getCustomerList(array $customers = []): CustomerCollection
    {
        $customerList = new CustomerCollection;

        if (! empty($customers)) {
            foreach (($customers['Customers'] ?? []) as $customer) {
                $customerList->push($this->renderSingleCustomer($customer));
            }
        }

        return $customerList;
    }

    private function renderSingleCustomer(array $attributes): Customer
    {
        $model = new Customer($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->ArCustomerNumber = $attributes['ARCustomerNumber'] ?? null;
            $model->CustomerName = $attributes['CustomerName'] ?? null;
            $model->CustomerCountry = is_string($attributes['CustomerCountry']) ? $attributes['CustomerCountry'] : null;
            $model->CustomerAddress1 = is_string($attributes['CustomerAddress1']) ? $attributes['CustomerAddress1'] : null;
            $model->CustomerAddress2 = is_string($attributes['CustomerAddress2']) ? $attributes['CustomerAddress2'] : null;
            $model->CustomerAddress3 = is_string($attributes['CustomerAddress3']) ? $attributes['CustomerAddress3'] : null;
            $model->CustomerCity = $attributes['CustomerCity'] ?? null;
            $model->CustomerState = $attributes['CustomerState'] ?? null;
            $model->CustomerZipCode = $attributes['CustomerZipCode'] ?? null;
            $model->CustomerEmail = $attributes['CustomerEmail'] ?? null;
            $model->CustomerPhone = $attributes['CustomerPhone'] ?? null;
            $model->CustomerContact = $attributes['CustomerContact'] ?? null;
            $model->DefaultShipTo = is_string($attributes['DefaultShipTo']) ? $attributes['DefaultShipTo'] : null;
            $model->DefaultWarehouse = $attributes['DefaultWarehouse'] ?? null;
            $model->CarrierCode = $attributes['CarrierCode'] ?? null;
            $model->PriceList = $attributes['PriceList'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CustomerClass = $attributes['CustomerClass'] ?? null;
            $model->SuspendCode = is_string($attributes['SuspendCode']) ? $attributes['SuspendCode'] : null;
            $model->AllowArPayments = $attributes['AllowArPayments'] ?? null;
            $model->CreditCardOnly = $attributes['CreditCardOnly'] ?? null;
            $model->FreightOptionAmount = ! empty($attributes['FreightOptionAmount']) ? floatval($attributes['FreightOptionAmount']) : null;
            $model->PoRequired = $attributes['PoRequired'] ?? null;
            $model->SalesPersonCode = $attributes['SalesPersonCode'] ?? null;
            $model->SalesPersonName = $attributes['SalesPersonName'] ?? null;
            $model->SalesPersonEmail = $attributes['SalesPersonEmail'] ?? null;

            $model->AcceptBackOrd = $attributes['AcceptBackOrd'] ?? null;
            $model->ProductRestriction = $attributes['ProductRestriction'] ?? null;
            $model->WhsSeqCode = is_string($attributes['WhsSeqCode']) ? $attributes['WhsSeqCode'] : null;
            $model->OTShipPrice = $attributes['OTShipPrice'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer entity information from the Commerce Gateway
     */
    public function getCustomerDetail(array $customer = []): Customer
    {
        $customer = $customer['Customer'] ?? [];

        return $this->renderSingleCustomer($customer);
    }

    /**
     * This API is to get customer ship to locations entity information from the Commerce Gateway
     */
    public function getCustomerShippingLocationList(array $locations = []): ShippingLocationCollection
    {
        $customerShippingLocations = new ShippingLocationCollection;

        if (! empty($locations)) {
            foreach (($locations['ShipTo'] ?? []) as $location) {
                $customerShippingLocations->push($this->renderSingleCustomerShippingLocation($location));
            }
        }

        return $customerShippingLocations;
    }

    public function renderSingleCustomerShippingLocation($attributes): ShippingLocation
    {
        $model = new ShippingLocation($attributes);

        if (! empty($attributes)) {
            $model->ShipToNumber = $attributes['ShipToNumber'] ?? null;
            $model->ShipToName = $attributes['ShipToName'] ?? null;
            $model->ShipToCountryCode = is_string($attributes['ShipToCountryCode']) ? $attributes['ShipToCountryCode'] : null;
            $model->ShipToAddress1 = is_string($attributes['ShipToAddress1']) ? $attributes['ShipToAddress1'] : null;
            $model->ShipToAddress2 = is_string($attributes['ShipToAddress2']) ? $attributes['ShipToAddress2'] : null;
            $model->ShipToAddress3 = is_string($attributes['ShipToAddress3']) ? $attributes['ShipToAddress3'] : null;
            $model->ShipToCity = $attributes['ShipToCity'] ?? null;
            $model->ShipToState = $attributes['ShipToState'] ?? null;
            $model->ShipToZipCode = $attributes['ShipToZipCode'] ?? null;
            $model->ShipToPhoneNumber = $attributes['ShipToPhoneNumber'] ?? null;
            $model->ShipToContact = is_string($attributes['ShipToContact']) ? $attributes['ShipToContact'] : null;
            $model->ShipToWarehouse = $attributes['ShipToWarehouse'] ?? null;
            $model->BackorderCode = $attributes['BackorderCode'] ?? null;
            $model->CarrierCode = $attributes['ShipToCarrierCode'] ?? null;
            $model->PoRequired = $attributes['PoRequired'] ?? null;
            $model->WhsSeqCode = is_string($attributes['WhsSeqCode']) ? $attributes['WhsSeqCode'] : null;
            $model->AcceptsBackOrders = is_string($attributes['AcceptsBackOrders']) ? $attributes['AcceptsBackOrders'] : null;
            $model->ShipToSuspendCode = is_string($attributes['ShipToSuspendCode']) ? $attributes['ShipToSuspendCode'] : null;
            $model->ProductRestriction = is_string($attributes['ProductRestriction']) ? $attributes['ProductRestriction'] : null;
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
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        $model = new ProductPriceAvailabilityCollection;

        if (! empty($filters)) {
            if (isset($filters['ItemAvailability']['WarehouseInfo'])) {
                $model->push($this->renderSingleProductPriceAvailability($filters['ItemAvailability']));
            } else {
                foreach (($filters['ItemAvailability'] ?? []) as $item) {
                    $model->push($this->renderSingleProductPriceAvailability($item));
                }
            }
        }

        return $model;
    }

    private function renderSingleProductPriceAvailability($attributes): ProductPriceAvailability
    {
        $model = new ProductPriceAvailability($attributes);

        if (! empty($attributes)) {
            $model->ItemNumber = $attributes['Item']['ItemNumber'] ?? null;
            $model->WarehouseID = $attributes['WarehouseInfo']['Warehouse'] ?? null;
            $model->Price = ($attributes['WarehouseInfo']['Price']) ? (float) str_replace(',', '', $attributes['WarehouseInfo']['Price']) : 0;
            $model->ListPrice = $attributes['ListPrice'] ?? null;
            $model->StandardPrice = $attributes['StandardPrice'] ?? null;
            $model->QtyPrice_1 = $attributes['QtyPrice_1'] ?? null;
            $model->QtyBreak_1 = $attributes['QtyBreak_1'] ?? null;
            $model->QtyPrice_2 = $attributes['QtyPrice_2'] ?? null;
            $model->QtyBreak_2 = $attributes['QtyBreak_2'] ?? null;
            $model->QtyPrice_3 = $attributes['QtyPrice_3'] ?? null;
            $model->QtyBreak_3 = $attributes['QtyBreak_3'] ?? null;
            $model->QtyPrice_4 = $attributes['QtyPrice_4'] ?? null;
            $model->QtyBreak_4 = $attributes['QtyBreak_4'] ?? null;
            $model->QtyPrice_5 = $attributes['QtyPrice_5'] ?? null;
            $model->QtyBreak_5 = $attributes['QtyBreak_5'] ?? null;
            $model->QtyPrice_6 = $attributes['QtyPrice_6'] ?? null;
            $model->QtyBreak_6 = $attributes['QtyBreak_6'] ?? null;
            $model->ExtendedPrice = $attributes['WarehouseInfo']['ExtendedPrice'] ?? null;
            $model->OrderPrice = $attributes['OrderPrice'] ?? null;
            $model->UnitOfMeasure = $attributes['UnitOfMeasure'] ?? null;
            $model->PricingUnitOfMeasure = ucwords(strtolower($attributes['WarehouseInfo']['PricingUOM'] ?? null));
            $model->DefaultSellingUnitOfMeasure = $attributes['DefaultSellingUnitOfMeasure'] ?? null;
            $model->AverageLeadTime = $attributes['AverageLeadTime'] ?? null;
            $model->QuantityAvailable = $attributes['WarehouseInfo']['Qty'] ?? null;
            $model->QuantityOnOrder = $attributes['QuantityOnOrder'] ?? null;

            $model->NextPOQty = $attributes['WarehouseInfo']['NextPOQty'] ?? null;
            $model->NextPODate = $attributes['WarehouseInfo']['NextPODate'] ?? null;
            $model->ErrorMessage = $attributes['WarehouseInfo']['ErrorMessage'] ?? null;
            $model->PricingUOMPrice = $attributes['WarehouseInfo']['PricingUOMPrice'] ?? null;
            $model->NextQtyBreak = $attributes['WarehouseInfo']['NextQtyBreak'] ?? null;
            $model->NextQtyPrice = $attributes['WarehouseInfo']['NextQtyPrice'] ?? null;
            $model->NextBreakType = is_string($attributes['WarehouseInfo']['NextBreakType']) ? $attributes['WarehouseInfo']['NextBreakType'] : null;
            $model->TransactionID = $attributes['TranHeader']['TransactionID'] ?? null;
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
            foreach (($filters['ItemMaster'] ?? []) as $item) {
                $model->push($this->renderProductSync($item));
            }
        }

        return $model;
    }

    private function renderProductSync($attributes): ProductSync
    {
        $model = new ProductSync($attributes);

        $model->ItemNumber = $attributes['ItemNumber'] ?? null;
        $model->UpdateAction = $attributes['UpdateAction'] ?? null;
        $model->Description1 = $attributes['Description1'] ?? null;
        $model->Description2 = $attributes['Description2'] ?? null;
        $model->ItemClass = $attributes['ItemClass'] ?? null;
        $model->PriceClass = $attributes['PriceClass'] ?? null;
        $model->ListPrice = $attributes['ListPrice'] ?? null;
        $model->UnitOfMeasure = $attributes['UnitOfMeasure'] ?? null;
        $model->PricingUnitOfMeasure = $attributes['PricingUnitOfMeasure'] ?? null;
        $model->Manufacturer = $attributes['Manufacturer'] ?? null;
        $model->PrimaryVendor = $attributes['PrimaryVendor'] ?? null;

        return $model;
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        $warehouseCollection = new WarehouseCollection;
        if (isset($filters['Warehouse'])) {
            foreach (($filters['Warehouse'] ?? []) as $warehouse) {
                $warehouseCollection->push($this->renderSingleWarehouse($warehouse));
            }
        }

        return $warehouseCollection;
    }

    private function renderSingleWarehouse($warehouse): Warehouse
    {
        $model = new Warehouse($warehouse);

        $model->InternalId = $warehouse['InternalId'] ?? null;
        $model->WarehouseNumber = $warehouse['WarehouseNumber'] ?? null;
        $model->WarehouseName = $warehouse['WarehouseName'] ?? null;
        $model->WarehousePhone = null;
        $model->WarehouseZip = null;
        $model->WarehouseAddress = null;
        $model->WarehouseEmail = null;
        $model->WhsSeqCode = $warehouse['WhsSeqCode'] ?? null;
        $model->CompanyNumber = $warehouse['CompanyNumber'] ?? null;
        $model->WhPricingLevel = $warehouse['WhPricingLevel'] ?? null;

        return $model;
    }
    /*
    |--------------------------------------------------------------------------
    | ORDER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to create an order in the Commerce Gateway
     */
    public function createOrder(array $orderInfo = []): Order
    {
        $orderCollection = new CreateOrderCollection;

        if (! empty($orderInfo)) {
            foreach (($orderInfo['Order'] ?? []) as $order) {
                $orderCollection->push($this->renderSingleCreateOrder($order));
            }
        }

        return $orderCollection;
    }

    private function renderSingleCreateOrder($attributes): CreateOrder
    {
        $model = new CreateOrder($attributes);

        if (! empty($attributes)) {
            $model->OrderNumber = $attributes['OrderNumber'] ?? null;
            $model->TotalOrderValue = $attributes['TotalOrderValue'] ?? null;
            $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
            $model->FreightAmount = $attributes['FreightAmount'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        $orderInfo = isset($orderInfo['Orders'])
            ? array_shift($orderInfo['Orders'])
            : [];

        return $this->renderSingleOrder($orderInfo);
    }

    /**
     * This API is to get customer Accounts Receivables information from the Commerce Gateway
     */
    public function getCustomerARSummary(array $attributes = []): CustomerAR
    {
        $model = new CustomerAR($attributes);

        if (! empty($attributes)) {
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
     * This API is to get customer Accounts Receivables Open Invoices data from the Commerce Gateway
     */
    public function getInvoiceList(array $attributes = []): InvoiceCollection
    {
        $invoiceList = new InvoiceCollection;

        if (! empty($attributes)) {
            foreach (($attributes['Invoices'] ?? []) as $invoice) {
                $invoiceList->push($this->renderSingleInvoice($invoice));
            }
        }

        return $invoiceList;
    }

    private function renderSingleInvoice($attributes): Invoice
    {
        $model = new Invoice($attributes);

        if (! empty($attributes)) {
            $model->AllowArPayments = $attributes['AllowArPayments'] ?? null;
            $model->InvoiceNumber = $attributes['InvoiceNumber'] ?? null;
            $model->InvoiceType = $attributes['InvoiceType'] ?? null;
            $model->InvoiceDisputeCode = $attributes['InvoiceDisputeCode'] ?? null;
            $model->FinanceChargeFlag = $attributes['FinanceChargeFlag'] ?? null;
            $model->InvoiceDate = $attributes['InvoiceDate'] ?? null;
            $model->AgeDate = $attributes['AgeDate'] ?? null;
            $model->EntryDate = $attributes['EntryDate'] ?? null;
            $model->InvoiceAmount = $attributes['InvoiceAmount'] ?? null;
            $model->InvoiceBalance = $attributes['InvoiceBalance'] ?? null;
            $model->PendingPayment = $attributes['PendingPayment'] ?? null;
            $model->DiscountAmount = $attributes['DiscountAmount'] ?? null;
            $model->DiscountDueDate = $attributes['DiscountDueDate'] ?? null;
            $model->LastTransactionDate = $attributes['LastTransactionDate'] ?? null;
            $model->PayDays = $attributes['PayDays'] ?? null;
            $model->CustomerPONumber = $attributes['CustomerPONumber'] ?? null;
            $model->InvoiceDetail = new OrderCollection;

            if (is_array($attributes['InvoiceDetail'])) {
                $this->getOrderList($attributes['InvoiceDetail']);
            }
        }

        return $model;
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * This API is to get details of a order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $customerOrders = []): OrderCollection
    {
        $orders = new OrderCollection;

        if (! empty($customerOrders)) {
            foreach (($customerOrders['Orders'] ?? []) as $order) {
                $orders->push($this->renderSingleOrder($order));
            }
        }

        return $orders;
    }

    private function renderSingleOrder($attributes): Order
    {
        $model = new Order($attributes);

        if (! empty($attributes)) {
            $model->CustomerNumber = $attributes['CustomerNumber'] ?? null;
            $model->OrderNumber = $attributes['OrderNumber'] ?? null;
            $model->OrderSuffix = $attributes['OrderSuffix'] ?? null;
            $model->OrderType = $attributes['OrderType'] ?? null;
            $model->OrderStatus = $attributes['OrderStatus'] ?? null;
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
            $model->RequestedShipDate = $attributes['RequestedShipDate'] ?? null;
            $model->CustomerPurchaseOrdernumber = $attributes['CustomerPurchaseOrdernumber'] ?? null;
            $model->ItemSalesAmount = $attributes['ItemSalesAmount'] ?? null;
            $model->DiscountAmountTrading = $attributes['DiscountAmountTrading'] ?? null;
            $model->SalesTaxAmount = $attributes['SalesTaxAmount'] ?? null;
            $model->InvoiceAmount = $attributes['InvoiceAmount'] ?? null;
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
                foreach ($attributes['OrderDetail'] as $orderDetail) {
                    $model->OrderDetail->push($this->renderSingleOrderDetail($orderDetail));
                }
            }
        }

        return $model;
    }

    private function renderSingleOrderDetail($attributes): OrderDetail
    {
        $model = new OrderDetail($attributes);

        if (! empty($attributes)) {
            $model->LineNumber = $attributes['LineNumber'] ?? null;
            $model->ItemNumber = $attributes['ItemNumber'] ?? null;
            $model->ItemType = $attributes['ItemType'] ?? null;
            $model->ItemDescription1 = $attributes['ItemDescription1'] ?? null;
            $model->ItemDescription2 = $attributes['ItemDescription2'] ?? null;
            $model->QuantityOrdered = $attributes['QuantityOrdered'] ?? null;
            $model->QuantityShipped = $attributes['QuantityShipped'] ?? null;
            $model->QuantityBackordered = $attributes['QuantityBackordered'] ?? null;
            $model->UnitOfMeasure = $attributes['UnitOfMeasure'] ?? null;
            $model->PricingUM = $attributes['PricingUM'] ?? null;
            $model->ActualSellPrice = $attributes['ActualSellPrice'] ?? null;
            $model->TotalLineAmount = $attributes['TotalLineAmount'] ?? null;
        }

        return $model;
    }

    /**
     * This API is to get customer AR Open Invoice data from the Commerce Gateway.
     */
    public function getInvoiceDetail(array $data = []): Invoice
    {
        $data = isset($data['Invoices'])
            ? array_shift($data['Invoices'])
            : [];

        return $this->renderSingleInvoice($data);
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

        if (! empty($noteInfo['UpdateNotes'])) {
            $attributes = $noteInfo['UpdateNotes'] ?? [];

            $model->Status = $attributes['NoteNum'] ?? null;
            $model->NoteNum = $attributes['NoteNum'] ?? null;
        }

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

        $attributes = [];

        if (! empty($inputs['ContactValidation'])) {
            $attributes = array_shift($inputs['ContactValidation']);
        }

        $model->ValidCombination = $attributes['ValidCombination'] ?? 'N';
        $model->CustomerNumber = $attributes['CustomerNumber'] ?? 'N';
        $model->ContactNumber = $attributes['ContactNumber'] ?? 'N';
        $model->EmailAddress = $attributes['EmailAddress'] ?? 'N';
        $model->DefaultWarehouse = $attributes['DefaultWarehouse'] ?? 'N';
        $model->DefaultShipTo = $attributes['DefaultShipTo'] ?? 'N';

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
