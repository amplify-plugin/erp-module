<?php

namespace Amplify\ErpApi\Services;

use Amplify\ErpApi\Adapters\CommerceGatewayAdapter;
use Amplify\ErpApi\Collections\CampaignCollection;
use Amplify\ErpApi\Collections\ContactCollection;
use Amplify\ErpApi\Collections\CustomerCollection;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Collections\OrderCollection;
use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Collections\ProductSyncCollection;
use Amplify\ErpApi\Collections\ShippingLocationCollection;
use Amplify\ErpApi\Collections\ShippingOptionCollection;
use Amplify\ErpApi\Collections\WarehouseCollection;
use Amplify\ErpApi\Exceptions\CommerceGatewayException;
use Amplify\ErpApi\Interfaces\ErpApiInterface;
use Amplify\ErpApi\Traits\BackendShippingCostTrait;
use Amplify\ErpApi\Traits\ErpApiConfigTrait;
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
use Amplify\ErpApi\Wrappers\TrackShipment;
use Amplify\ErpApi\Wrappers\Warehouse;
use Amplify\System\Backend\Models\Shipping;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * @property array $config
 *
 * @method post(string $string, array[] $query)
 */
class CommerceGatewayService implements ErpApiInterface
{
    use BackendShippingCostTrait;
    use ErpApiConfigTrait;

    public function createQuotation(array $orderInfo = []): \Amplify\ErpApi\Collections\CreateQuotationCollection
    {
        // TODO: Implement createQuotation() method
        return $this->adapter->createQuotation();
    }

    public function getPastItemList(array $filters = []): \Amplify\ErpApi\Collections\PastItemCollection
    {
        // TODO: Implement getPastItemList() method
        return $this->adapter->getPastItemList();
    }

    public function getQuotationDetail(array $orderInfo = []): \Amplify\ErpApi\Wrappers\Quotation
    {
        // TODO: Implement getQuotationDetail() method
        return $this->adapter->getQuotationDetail();
    }

    public function getQuotationList(array $filters = []): \Amplify\ErpApi\Collections\QuotationCollection
    {
        // TODO: Implement getQuotationList() method
        return $this->adapter->getQuotationList();
    }

    public const DEFAULT_COMPANY_NUMBER = '01';

    private array $commonParams;

    public function __construct()
    {
        $this->adapter = new CommerceGatewayAdapter;

        $this->config = config('amplify.erp.configurations.ecommerce-erp');

        $this->commonParams = [
            'SubscriberID' => $this->config['username'],
            'SubscriberPassword' => $this->config['password'],
        ];
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
        return $this->config['enabled'] ?? false;
    }

    /**
     * This function will check if the ERP has Multiple warehouse capabilities
     */
    public function allowMultiWarehouse(): bool
    {
        return $this->config['multiple_warehouse'] ?? false;
    }

    /**
     * This function will return the ERP Carrier code options
     */
    public function getShippingOption(array $data = []): ShippingOptionCollection
    {
        $options = Shipping::enabled()->get()->toArray();

        return $this->adapter->getShippingOption($options);

    }

    public function getWarehouses(array $filters = []): WarehouseCollection
    {
        $warehouseCollection = new WarehouseCollection;

        try {
            $limit = $filters['limit'] ?? null;

            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;

            $payload = "<Limit>{$limit}</Limit>
                        <LimitField1>{$company_number}</LimitField1>";

            $response = $this->get('GetWarehouses', $payload);

            $warehouseCollection = new WarehouseCollection;
            if (isset($response['Warehouse'])) {
                foreach (($response['Warehouse'] ?? []) as $warehouse) {
                    $wareHouseWrapper = new Warehouse($warehouse);
                    $wareHouseWrapper->WarehouseNumber = $warehouse['WarehouseNumber'] ?? null;
                    $wareHouseWrapper->WarehouseName = $warehouse['WarehouseName'] ?? null;
                    $wareHouseWrapper->WhsSeqCode = $warehouse['WhsSeqCode'] ?? null;
                    $wareHouseWrapper->CompanyNumber = $warehouse['CompanyNumber'] ?? null;
                    $wareHouseWrapper->WhPricingLevel = $warehouse['WhPricingLevel'] ?? null;
                    $warehouseCollection->push($wareHouseWrapper);
                }
            }

            return $warehouseCollection;
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $warehouseCollection;
        }
    }

    /**
     * @return mixed|null
     *
     * @throws Exception
     */
    private function get(string $transactionName, $payload = null)
    {
        $base_url = $this->config['url'].'?';

        $payload = http_build_query($this->commonParams + [
            'Data' => '<request name="'.$transactionName.'">'.$payload.'</request>',
            'TransactionName' => $transactionName,
        ]);

        $response = Http::timeout(10)
            ->withoutVerifying()
            ->get($base_url.$payload)
            ->getBody()
            ->getContents();

        // Item Master API Response RAW Logging enable when product sync implemented
        // if($url == '') {
        //     $this->logProductSyncResponse($response->body());
        // }

        return $this->validate($response);
    }

    /**
     * Validate the API call response
     *
     * @return mixed|null
     *
     * @throws CommerceGatewayException|Exception
     */
    private function validate($response)
    {
        try {
            // Empty Response
            if (is_null($response)) {
                throw new CommerceGatewayException("Empty Response Received ({$response})", 500);
            }

            // HTML Response
            if (strpos($response, '<body>')) {
                $error_message = [];
                preg_match('/<h2>(.+)<\/h2>/', $response, $error_message);
                throw new CommerceGatewayException(($error_message[1] ?? 'Invalid Response'), 500);
            }

            // Invalid XML
            libxml_use_internal_errors(true);
            $response = simplexml_load_string($response);
            if ($response === false) {
                $error_message = '';
                foreach (libxml_get_errors() as $error) {
                    $error_message .= (' '.$error->message);
                }

                $error_message = trim($error_message);

                throw new CommerceGatewayException($error_message, 500);
            }

            // Invalid JSON
            $response = json_decode(json_encode($response), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new CommerceGatewayException('Invalid JSON Error ('.json_last_error_msg().')', 500);
            }

            return $response;
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return [];
        }
    }

    /**
     * This API is to create a new cash customer account
     */
    public function createCustomer(array $attributes = []): CreateCustomer
    {
        // TODO:No API found.
        return $this->adapter->createCustomer();
    }

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getCustomerList(array $filters = []): CustomerCollection
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>";

            $response = $this->get('GetCustomers', $payload);

            return $this->adapter->getCustomerList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerList();
        }
    }

    /**
     * This API is to get customer ship to locations entity information from the ERP
     */
    public function getCustomerShippingLocationList(array $filters = []): ShippingLocationCollection
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($filters)->CustomerNumber;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber><CustomerNumber>{$customer_number}</CustomerNumber>";

            $response = $this->get('CustomerShipTo', $payload);

            return $this->adapter->getCustomerShippingLocationList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerShippingLocationList();
        }
    }

    /**
     * This API is to get customer entity information from the ERP
     */
    public function getCustomerDetail(array $filters = []): Customer
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->customerId();

            if ($customer_number == null) {
                throw new CommerceGatewayException('Customer Code is missing.');
            }

            if (strlen($customer_number) <= 2 || ! is_numeric($customer_number)) {
                throw new CommerceGatewayException('Invalid Customer Code.');
            }

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>";

            $response = $this->get('GetCustomerInfo', $payload);

            if (isset($response['Customer'])) {
                $response['Customer']['CustomerNumber'] = $customer_number;
            }

            return $this->adapter->getCustomerDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerDetail();
        }
    }

    /**
     * This API is to get item details with pricing and availability for the given warehouse location ID
     */
    public function getProductPriceAvailability(array $filters = []): ProductPriceAvailabilityCollection
    {
        try {
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($filters)->CustomerNumber;

            $warehouse = $filters['warehouse'] ?? self::DEFAULT_COMPANY_NUMBER;

            $payload = "<TransactionID></TransactionID>
                        <RequestID>{$customer_number}</RequestID>
                        <OrderNumber></OrderNumber>
                        <Items>
                             <WarehouseID>{$warehouse}</WarehouseID>
                             <OrderQuantity></OrderQuantity>
                             <UnitofMeasure></UnitofMeasure>
                             <VendorCost></VendorCost>
                             <VendorPrice1></VendorPrice1>
                             <VendorPrice2></VendorPrice2>
                             <VendorPrice3></VendorPrice3>
                             <VendorPrice4></VendorPrice4>
                             <VendorPrice5></VendorPrice5>
                             <NonStockFlag></NonStockFlag>
                             <CalculatePrices>Y</CalculatePrices>
                             <ShipToNumber></ShipToNumber>
                             <DftWhsOnly></DftWhsOnly>";

            foreach ($filters['items'] as $item) {
                $payload .= '<ItemNumber>'.$item['item'].'</ItemNumber>';
            }

            $payload .= '</Items>';

            $response = $this->get('GetAvail', $payload);

            return $this->adapter->getProductPriceAvailability($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getProductPriceAvailability();
        }
    }

    /**
     * This function will check if the ERP has Any changes on inventory
     * of items between filters and data range
     */
    public function getProductSync(array $filters = []): ProductSyncCollection
    {
        try {
            $itemStart = $filters['item_start'] ?? '';
            $itemEnd = $filters['item_end'] ?? '';
            $updatesOnly = $filters['updates_only'] ?? 'Y';
            $processUpdates = $filters['process_updates'] ?? 'N';
            $maxRecords = $filters['limit'] ?? 10;
            $restartPoint = $filters['restartPoint'] ?? 1;

            $payload = [
                'content' => [
                    'ItemStart' => $itemStart,
                    'ItemEnd' => $itemEnd,
                    'UpdatesOnly' => $updatesOnly,
                    'ProcessUpdates' => $processUpdates,
                    'MaxRecords' => $maxRecords,
                    'RestartPoint' => $restartPoint,
                ],
            ];

            $response = $this->get('arPayment', $payload);

            return $this->adapter->getProductSync($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getProductSync();
        }
    }

    /**
     * This API is to create an order in the ERP
     */
    public function createOrder(array $orderInfo = []): Order
    {
        try {
            $cc_customer_id = $orderInfo['cc_customer_id'] ?? null;
            $cc_credit_card_nbr = $orderInfo['cc_credit_card_nbr'] ?? null;
            $cc_payment_type = $orderInfo['payment_type'] ?? null;
            $cc_credit_card_exp = $orderInfo['cc_credit_card_exp'] ?? null;
            $cc_card_holder = $orderInfo['cc_card_holder'] ?? null;
            $cc_cvv2 = $orderInfo['cc_cvv2'] ?? null;
            $cc_addr1 = $orderInfo['cc_addr1'] ?? null;
            $cc_addr2 = $orderInfo['cc_addr2'] ?? null;
            $cc_addr3 = $orderInfo['cc_addr3'] ?? null;
            $cc_addr4 = $orderInfo['cc_addr4'] ?? null;
            $cc_city = $orderInfo['cc_city'] ?? null;
            $cc_state = $orderInfo['cc_state'] ?? null;
            $cc_zip = $orderInfo['cc_zip'] ?? null;
            $cc_country = $orderInfo['cc_country'] ?? null;
            $cc_po_number = $orderInfo['cc_po_number'] ?? null;
            $cc_ship_to_zip = $orderInfo['cc_ship_to_zip'] ?? null;
            $cc_tax_amount = $orderInfo['cc_tax_amount'] ?? null;
            $cc_authorization_amount = $orderInfo['cc_authorization_amount'] ?? null;
            $cc_merchant_id = $orderInfo['cc_merchant_id'] ?? null;
            $cc_masked_card = $orderInfo['cc_masked_card'] ?? null;
            $cc_token = $orderInfo['cc_token'] ?? null;
            $cc_authorization_number = $orderInfo['cc_authorization_number'] ?? null;
            $cc_reference_number = $orderInfo['cc_reference_number'] ?? null;
            $cc_card_type = $orderInfo['cc_card_type'] ?? null;
            $cc_end = $orderInfo['cc_end'] ?? null;

            $tax_amount = $orderInfo['tax_amount'] ?? null;
            $authorization_amount = $orderInfo['authorization_amount'] ?? null;
            $customer_id = $orderInfo['customer_id'] ?? null;
            $credit_card_nbr = $orderInfo['credit_card_nbr'] ?? null;
            $payment_type = $orderInfo['payment_type'] ?? null;
            $credit_card_exp = $orderInfo['credit_card_exp'] ?? null;
            $warehouse_id = $orderInfo['warehouse_id'] ?? null;
            $order_source = $orderInfo['order_source'] ?? null;
            $review_order_hold = $orderInfo['review_order_hold'] ?? null;
            $po_number = $orderInfo['PONumber'] ?? null;
            $ord_number = $orderInfo['ord_number'] ?? null;
            $work_station = $orderInfo['work_station'] ?? null;
            $bill_to_contact = $orderInfo['bill_to_contact'] ?? null;
            $bill_to_city = $orderInfo['bill_to_city'] ?? null;
            $bill_to_state = $orderInfo['bill_to_state'] ?? null;
            $bill_to_zip = $orderInfo['bill_to_zip'] ?? null;
            $bill_to_phone = $orderInfo['bill_to_phone'] ?? null;
            $bill_to_phone_ext = $orderInfo['bill_to_PhoneExt'] ?? null;
            $bill_to_cntry_code = $orderInfo['bill_to_cntry_code'] ?? null;
            $carrier_code = $orderInfo['shipping_method'] ?? null;
            $customer_addr1 = $orderInfo['customer_addr1'] ?? null;
            $customer_addr2 = $orderInfo['customer_addr2'] ?? null;
            $customer_addr3 = $orderInfo['customer_addr3'] ?? null;
            $customer_addr4 = $orderInfo['customer_addr4'] ?? null;

            $contract_number = $orderInfo['contract_number'] ?? null;
            $customer_name = $orderInfo['customer_name'] ?? null;
            $customer_country = $orderInfo['customer_country'] ?? null;
            $tax_exempt_century = $orderInfo['tax_exempt_century'] ?? null;
            $tax_exempt_date = $orderInfo['tax_exempt_date'] ?? null;
            $tax_ex_cert_number = $orderInfo['tax_ex_cert_number'] ?? null;
            $fob_code = $orderInfo['fob_code'] ?? null;
            $req_ship_date = $orderInfo['req_ship_date'] ?? null;
            $ship_to_addr1 = $orderInfo['ship_to_addr1'] ?? null;
            $ship_to_addr2 = $orderInfo['ship_to_addr2'] ?? null;
            $ship_to_addr3 = $orderInfo['ship_to_addr3'] ?? null;
            $ship_to_addr4 = $orderInfo['ship_to_addr4'] ?? null;
            $ship_to_contact = $orderInfo['ship_to_contact'] ?? null;
            $ship_to_city = $orderInfo['ship_to_city'] ?? null;
            $ship_to_country = $orderInfo['ship_to_country'] ?? null;
            $ship_to_name = $orderInfo['ship_to_name'] ?? null;
            $ship_to_number = $orderInfo['ship_to_number'] ?? null;
            $ship_to_state = $orderInfo['ship_to_state'] ?? null;
            $ship_to_phone = $orderInfo['ship_to_phone'] ?? null;
            $ship_to_phone_ext = $orderInfo['ship_to_phone_ext'] ?? null;
            $ship_to_cntry_code = $orderInfo['ship_to_cntry_code'] ?? null;
            $ship_to_zip = $orderInfo['ship_to_zip'] ?? null;

            $web_transaction_type = $orderInfo['web_transaction_type'] ?? null;
            $web_user_id = $orderInfo['web_user_id'] ?? null;
            $web_process_id = $orderInfo['web_process_id'] ?? null;
            $web_transaction_id = $orderInfo['web_transaction_id'] ?? null;
            $web_order_id = $orderInfo['web_order_id'] ?? null;
            $freight_method = $orderInfo['freight_method'] ?? null;
            $order_type = $orderInfo['order_type'] ?? null;
            $quote_review_date = $orderInfo['quote_review_date'] ?? null;

            $item_number = $orderInfo['item_number'] ?? null;
            $order_qty = $orderInfo['order_qty'] ?? null;
            $unit_of_measure = $orderInfo['unit_of_measure'] ?? null;
            $warehouse_id = $orderInfo['warehouse_id'] ?? null;
            $line_item_type = $orderInfo['line_item_type'] ?? null;
            $item_desc1 = $orderInfo['item_desc1'] ?? null;
            $item_desc2 = $orderInfo['item_desc2'] ?? null;
            $actual_sell_price = $orderInfo['actual_sell_price'] ?? null;
            $cost = $orderInfo['cost'] ?? null;
            $non_stock_flag = $orderInfo['non_stock_flag'] ?? null;
            $charge_type = $orderInfo['charge_type'] ?? null;
            $drop_ship = $orderInfo['drop_ship'] ?? null;
            $due_date = $orderInfo['due_date'] ?? null;
            $extended_weight = $orderInfo['extended_weight'] ?? null;
            $list_price = $orderInfo['list_price'] ?? null;
            $item_id = $orderInfo['item_id'] ?? null;
            $carrier_code = $orderInfo['carrier_code'] ?? null;
            $freight_rate = $orderInfo['freight_rate'] ?? null;
            $total_value = $orderInfo['total_value'] ?? null;

            $payload = "<Orders>
                            <Order>
                                <OrderHeader>
                                    <CCCustomerID>{{$cc_customer_id}}</CCCustomerID>
                                    <CCCreditCardNbr>{{$cc_credit_card_nbr}}</CCCreditCardNbr>
                                    <CCPaymentType>{{$cc_payment_type}}</CCPaymentType>
                                    <CCCreditCardExp>{{$cc_credit_card_exp}}</CCCreditCardExp>
                                    <CCCardHolder>{{$cc_card_holder}}</CCCardHolder>
                                    <CCCVV2>{{$cc_cvv2}}</CCCVV2>
                                    <CCAddr1>{$cc_addr1}</CCAddr1>
                                    <CCAddr2>{$cc_addr2}</CCAddr2>
                                    <CCAddr3>{$cc_addr3}</CCAddr3>
                                    <CCAddr4>{$cc_addr4}</CCAddr4>
                                    <CCCity>{$cc_city}</CCCity>
                                    <CCState>{$cc_state}</CCState>
                                    <CCZip>{$cc_zip}</CCZip>
                                    <CCCountry>{$cc_country}</CCCountry>
                                    <CCPONumber>{$cc_po_number}</CCPONumber>
                                    <CCShipToZip>{$cc_ship_to_zip}</CCShipToZip>
                                    <CCTaxAmount>{$cc_tax_amount}</CCTaxAmount>
                                    <CCAuthorizationAmount>{$cc_authorization_amount}</CCAuthorizationAmount>
                                    <CCMerchantId>{$cc_merchant_id}</CCMerchantId>
                                    <CCMaskedCard>{$cc_masked_card}</CCMaskedCard>
                                    <CCToken>{$cc_token}</CCToken>
                                    <CCAuthorizationNumber>{$cc_authorization_number}</CCAuthorizationNumber>
                                    <CCReferenceNumber>{$cc_reference_number}</CCReferenceNumber>
                                    <CCCardType>{$cc_card_type}</CCCardType>
                                    <CCEND>{$cc_end}</CCEND>

                                    <TaxAmount>{$tax_amount}</TaxAmount>
                                    <AuthorizationAmount>{$authorization_amount}</AuthorizationAmount>
                                    <CustomerID>{$customer_id}</CustomerID>
                                    <CreditCardNbr>{$credit_card_nbr}</CreditCardNbr>
                                    <PaymentType>{$payment_type}</PaymentType>
                                    <CreditCardExp>{$credit_card_exp}</CreditCardExp>
                                    <WarehouseId>{$warehouse_id}</WarehouseId>
                                    <OrderSource>{$order_source}</OrderSource>
                                    <ReviewOrderHold>{$review_order_hold}</ReviewOrderHold>
                                    <PONumber>{$po_number}</PONumber>
                                    <OrdNumber>{$ord_number}</OrdNumber>
                                    <WorkStation>{$work_station}</WorkStation>
                                    <BillToContact>{$bill_to_contact}</BillToContact>
                                    <BillToCity>{$bill_to_city}</BillToCity>
                                    <BillToState>{$bill_to_state}</BillToState>
                                    <BillToZip>{$bill_to_zip}</BillToZip>
                                    <BillToPhone>{$bill_to_phone}</BillToPhone>
                                    <BillToPhoneExt>{$bill_to_phone_ext}</BillToPhoneExt>
                                    <BillToCntryCode>{$bill_to_cntry_code}</BillToCntryCode>
                                    <CarrierCode>{$carrier_code}</CarrierCode>
                                    <CustomerAddr1>{$customer_addr1}</CustomerAddr1>
                                    <CustomerAddr2>{$customer_addr2}</CustomerAddr2>
                                    <CustomerAddr3>{$customer_addr3}</CustomerAddr3>
                                    <CustomerAddr4>{$customer_addr4}</CustomerAddr4>

                                    <ContractNumber>{$contract_number}</ContractNumber>
                                    <CustomerName>{$customer_name}</CustomerName>
                                    <CustomerCountry>{$customer_country}</CustomerCountry>
                                    <TaxExemptCentury>{$tax_exempt_century}</TaxExemptCentury>
                                    <TaxExemptDate>{$tax_exempt_date}</TaxExemptDate>
                                    <TaxExCertNumber>{$tax_ex_cert_number}</TaxExCertNumber>
                                    <FOBCode>{$fob_code}</FOBCode>
                                    <ReqShipDate>{$req_ship_date}</ReqShipDate>
                                    <ShipToAddr1>{$ship_to_addr1}</ShipToAddr1>
                                    <ShipToAddr2>{$ship_to_addr2}</ShipToAddr2>
                                    <ShipToAddr3>{$ship_to_addr3}</ShipToAddr3>
                                    <ShipToAddr4>{$ship_to_addr4}</ShipToAddr4>
                                    <ShipToContact>{$ship_to_contact}</ShipToContact>
                                    <ShipToCity>{$ship_to_city}</ShipToCity>
                                    <ShipToCountry>{$ship_to_country}</ShipToCountry>
                                    <ShipToName>{$ship_to_name}</ShipToName>
                                    <ShipToNumber>{$ship_to_number}</ShipToNumber>
                                    <ShipToState>{$ship_to_state}</ShipToState>
                                    <ShipToPhone>{$ship_to_phone}</ShipToPhone>
                                    <ShipToPhoneExt>{$ship_to_phone_ext}</ShipToPhoneExt>
                                    <ShipToCntryCode>{$ship_to_cntry_code}</ShipToCntryCode>
                                    <ShipToZip>{$ship_to_zip}</ShipToZip>

                                    <WebTransactionType>{$web_transaction_type}</WebTransactionType>
                                    <WebUserID>{$web_user_id}</WebUserID>
                                    <WebProcessID>{$web_process_id}</WebProcessID>
                                    <WebTransactionID>{$web_transaction_id}</WebTransactionID>
                                    <WebOrderID>{$web_order_id}</WebOrderID>
                                    <FreightMethod>{$freight_method}</FreightMethod>
                                    <OrderType>{$order_type}</OrderType>
                                    <QuoteReviewDate>{$quote_review_date}</QuoteReviewDate>
                                </OrderHeader>
                                <OrderDetail>
                                    <LineItemInfo>
                                        <ItemNumber>{$item_number}</ItemNumber>
                                        <OrderQty>{$order_qty}</OrderQty>
                                        <UnitOfMeasure>{$unit_of_measure}</UnitOfMeasure>
                                        <WarehouseId>{$warehouse_id}</WarehouseId>
                                        <LineItemType>{$line_item_type}</LineItemType>
                                        <ItemDesc1>{$item_desc1}</ItemDesc1>
                                        <ItemDesc2>{$item_desc2}</ItemDesc2>
                                        <ActualSellPrice>{$actual_sell_price}</ActualSellPrice>
                                        <Cost>{$cost}</Cost>
                                        <NonStockFlag>{$non_stock_flag}</NonStockFlag>
                                        <ChargeType>{$charge_type}</ChargeType>
                                        <DropShip>{$drop_ship}</DropShip>
                                        <DueDate>{$due_date}</DueDate>
                                        <ExtendedWeight>{$extended_weight}</ExtendedWeight>
                                        <ListPrice>{$list_price}</ListPrice>
                                        <ItemId>{$item_id}</ItemId>
                                    </LineItemInfo>
                                </OrderDetail>
                                <Carriers>
                                    <Carrier>
                                        <CarrierCode>{$carrier_code}</CarrierCode>
                                        <FreightRate>{$freight_rate}</FreightRate>
                                        <TotalValue>{$total_value}</TotalValue>
                                    </Carrier>
                                </Carriers>
                            </Order>
                        </Orders>";

            $response = $this->get('CreateOrder', $payload);
            dd($response);

            return $this->adapter->createOrder($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createOrder();
        }
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderList(array $filters = []): OrderCollection
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($filters)->CustomerNumber;
            $ship_to_number = $filters['ship_to_number'] ?? null;
            $from_entry_date = $filters['from_entry_date'] ?? null;
            $to_entry_date = $filters['to_entry_date'] ?? null;
            $order_type = $filters['order_type'] ?? null;
            $warehouse = $filters['warehouse'] ?? null;
            $customer_purchase_order_number = $filters['customer_purchase_order_number'] ?? null;
            $num_of_records = $filters['num_of_records'] ?? null;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>
                        <ShipToNumber>{$ship_to_number}</ShipToNumber>
                        <FromEntryDate>{$from_entry_date}</FromEntryDate>
                        <ToEntryDate>{$to_entry_date}</ToEntryDate>
                        <OrderType>{$order_type}</OrderType>
                        <Warehouse>{$warehouse}</Warehouse>
                        <CustomerPurchaseOrderNumber>{$customer_purchase_order_number}</CustomerPurchaseOrderNumber>
                        <NumOfRecords>{$num_of_records}</NumOfRecords>";

            $response = $this->get('GetCorOrders', $payload);

            return $this->adapter->getOrderList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderList();
        }
    }

    /**
     * This API is to get details of an order/invoice, or list of orders from a date range
     */
    public function getOrderDetail(array $orderInfo = []): Order
    {
        try {
            $get_order_info = $orderInfo['get_order_info'] ?? null;
            $company_number = $orderInfo['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($orderInfo)->CustomerNumber;
            $lookup_type = $orderInfo['lookup_type'] ?? null;
            $source = $orderInfo['source'] ?? null;
            $from_entry_date = $orderInfo['from_entry_date'] ?? null;
            $to_entry_date = $orderInfo['to_entry_date'] ?? null;
            $order_number = $orderInfo['order_number'] ?? null;
            $order_generation_number = $orderInfo['order_generation_number'] ?? null;
            $invoice_number = $orderInfo['invoice_number'] ?? null;
            $customer_purchase_order_number = $orderInfo['customer_purchase_order_number'] ?? null;
            $parent_order_number = $orderInfo['parent_order_number'] ?? null;
            $guest_flag = $orderInfo['guest_flag'] ?? null;
            $email_address = $orderInfo['email_address'] ?? null;
            $history_sequence_number = $orderInfo['history_sequence_number'] ?? null;
            $entry_date_century = $orderInfo['entry_date_century'] ?? null;
            $entry_date = $orderInfo['entry_date'] ?? null;
            $include_history = $orderInfo['include_history'] ?? null;
            $credit_card_key_seq = $orderInfo['credit_card_key_seq'] ?? null;

            $payload = "<GetOrderInfo>{$get_order_info}</GetOrderInfo>
                        <CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>
                        <LookupType>{$lookup_type}</LookupType>
                        <Source>{$source}</Source>
                        <FromEntryDate>{$from_entry_date}</FromEntryDate>
                        <ToEntryDate>{$to_entry_date}</ToEntryDate>
                        <OrderNumber>{$order_number}</OrderNumber>
                        <OrderGenerationNumber>{$order_generation_number}</OrderGenerationNumber>
                        <InvoiceNumber>{$invoice_number}</InvoiceNumber>
                        <CustomerPurchaseOrderNumber>{$customer_purchase_order_number}</CustomerPurchaseOrderNumber>
                        <ParentOrderNumber>{$parent_order_number}</ParentOrderNumber>
                        <GuestFlag>{$guest_flag}</GuestFlag>
                        <EmailAddress>{$email_address}</EmailAddress>
                        <HistorySequenceNumber>{$history_sequence_number}</HistorySequenceNumber>
                        <EntryDateCentury>{$entry_date_century}</EntryDateCentury>
                        <EntryDate>{$entry_date}</EntryDate>
                        <IncludeHistory>{$include_history}</IncludeHistory>
                        <CreditCardKeySeq>{$credit_card_key_seq}</CreditCardKeySeq>";

            $response = $this->get('GetOrder', $payload);

            return $this->adapter->getOrderDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getOrderDetail();
        }
    }

    /**
     * This API is to get customer Accounts Receivables information from the ERP
     */
    public function getCustomerARSummary(array $filters = []): CustomerAR
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($filters)->CustomerNumber;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>";

            $response = $this->get('ARSummary', $payload);

            return $this->adapter->getCustomerARSummary($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCustomerARSummary();
        }
    }

    /**
     * This API is to get customer Accounts Receivables Open Invoices data from the ERP
     */
    public function getInvoiceList(array $filters = []): InvoiceCollection
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($filters)->CustomerNumber;
            $invoice_number_inq = $filters['invoice_number_inq'] ?? null;
            $to_invoice_number = $filters['to_invoice_number'] ?? null;
            $max_invoices = $filters['max_invoices'] ?? null;
            $from_inv_date = $filters['from_inv_date'] ?? null;
            $to_inv_date = $filters['to_inv_date'] ?? null;
            $from_age_date = $filters['from_age_date'] ?? null;
            $to_age_date = $filters['to_age_date'] ?? null;
            $from_inv_amount = $filters['from_inv_amount'] ?? null;
            $to_inv_amount = $filters['to_inv_amount'] ?? null;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>
                        <InvoiceNumberInq>{$invoice_number_inq}</InvoiceNumberInq>
                        <ToInvoiceNumber>{$to_invoice_number}</ToInvoiceNumber>
                        <MaxInvoices>{$max_invoices}</MaxInvoices>
                        <FromInvDate>{$from_inv_date}</FromInvDate>
                        <ToInvDate>{$to_inv_date}</ToInvDate>
                        <FromAgeDate>{$from_age_date}</FromAgeDate>
                        <ToAgeDate>{$to_age_date}</ToAgeDate>
                        <FromInvAmount>{$from_inv_amount}</FromInvAmount>
                        <ToInvAmount>{$to_inv_amount}</ToInvAmount>";

            $response = $this->get('AROpenInvoices', $payload);

            return $this->adapter->getInvoiceList($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getInvoiceList();
        }
    }

    /**
     * This API is to get customer AR Open Invoice data from the ERP.
     */
    public function getInvoiceDetail(array $filters = []): Invoice
    {
        try {
            $company_number = $filters['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $filters['customer_number'] ?? $this->getCustomerDetail($filters)->CustomerNumber;
            $invoice_type = $filters['invoice_type'] ?? null;
            $invoice_number = $filters['invoice_number'] ?? null;
            $flag = $filters['customer_number'] ?? null;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>
                        <InvoiceType>{$invoice_type}</InvoiceType>
                        <InvoiceNumber>{$invoice_number}</InvoiceNumber>
                        <Flag>{$flag}</Flag>";

            $response = $this->get('ARInvoiceDetail', $payload);

            return $this->adapter->getInvoiceDetail($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getInvoiceDetail();
        }
    }

    /**
     * This API is to create an AR payment on the customers account.
     */
    public function createPayment(array $paymentInfo = []): CreatePayment
    {
        try {
            $company_number = $paymentInfo['company_number'] ?? self::DEFAULT_COMPANY_NUMBER;
            $customer_number = $paymentInfo['customer_number'] ?? $this->getCustomerDetail($paymentInfo)->CustomerNumber;

            $payload = "<CompanyNumber>{$company_number}</CompanyNumber>
                        <CustomerNumber>{$customer_number}</CustomerNumber>";

            $response = $this->get('CustomerShipTo', $payload);

            return $this->adapter->createPayment($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->createPayment();
        }
    }

    /**
     * This API is to create an order note.
     */
    public function createOrUpdateNote(array $noteInfo = []): CreateOrUpdateNote
    {
        return $this->adapter->createOrUpdateNote($noteInfo);
    }

    /**
     * This API is to get all future campaigns data from the ERP.
     *
     * @throws Exception
     */
    public function getCampaignList(array $filters = []): CampaignCollection
    {
        // @TODO INCOMPLETE ERP FEATURE
        try {
            //            $override_date = $filters['override_date'] ?? null;
            //            $promo_type = $filters['promo_type'] ?? 'O';
            //
            //            $payload = [
            //                'content' => [
            //                    'OverrideDate' => $override_date,
            //                    'PromoType' => $promo_type,
            //                    'Promo' => "",
            //                ],
            //            ];
            //
            //            $response = $this->get('/get_promotion.php', $payload);

            return $this->adapter->getCampaignList();
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCampaignList();
        }
    }

    /**
     * This API is to get single campaign details and items
     * info from the ERP.
     *
     * @throws Exception
     */
    public function getCampaignDetail(array $filters = []): Campaign
    {
        // @TODO INCOMPLETE ERP FEATURE
        try {
            //            $action = $filters['action'] ?? 'SPECIFIC';
            //            $override_date = $filters['override_date'] ?? null;
            //            $promo_type = $filters['promo_type'] ?? 'O';
            //            $promo = $filters['promo'];
            //
            //            $payload = [
            //                'content' => [
            //                    'Action' => $action,
            //                    'OverrideDate' => $override_date,
            //                    'PromoType' => $promo_type,
            //                    'Promo' => $promo,
            //                ],
            //            ];
            //
            //            $response = $this->post('/get_promotion.php', $payload);

            return $this->adapter->getCampaignDetail();
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getCampaignDetail();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT VALIDATION FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * The API is used to verify customer and contact assignable
     */
    public function contactValidation(array $inputs = []): ContactValidation
    {
        try {
            $contact_email = $orderInfo['email_address'] ?? null;
            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'EmailAddress' => $contact_email,
                ],
            ];

            $response = $this->post('/get_contact_validation.php', $payload);

            return $this->adapter->contactValidation($response);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->contactValidation();
        }
    }

    /**
     * This API is to get customer required document from ERP
     */
    public function getDocument(array $inputs = []): Document
    {
        try {

            $document = $this->getInvoiceDetail($inputs);

            return $this->adapter->getDocument($document);
        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getDocument();
        }
    }

    /**
     * This API is to get cost shipping method  of a cart items
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getOrderTotal(array $orderInfo = []): OrderTotal
    {
        try {

            $customer_number = $orderInfo['customer_number'] ?? $this->getCustomerDetail()->CustomerNumber;

            $payload = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'CustomerEmail' => $orderInfo['customer_email'] ?? $this->getCustomerDetail()->CustomerEmail,
                    'CarrierCode' => $orderInfo['shipping_method'] ?? 'UPS',
                    'PoNumber' => $order['customer_order_ref'] ?? '',
                    'ShipToNumber' => $orderInfo['ship_to_number'] ?? '',
                    'ShipToAddress1' => $orderInfo['ship_to_address1'] ?? '',
                    'ShipToAddress2' => $orderInfo['ship_to_address2'] ?? '',
                    'ShipToAddress3' => $orderInfo['ship_to_address3'] ?? '',
                    'ShipToCity' => $orderInfo['ship_to_city'] ?? '',
                    'ShipToCntryCode' => $orderInfo['ship_to_country_code'] ?? '',
                    'ShipToState' => $orderInfo['ship_to_state'] ?? '',
                    'ShipToZipCode' => $orderInfo['ship_to_zip_code'] ?? '',
                    'OrderType' => $orderInfo['order_type'] ?? 'T',
                    'ReturnType' => $orderInfo['return_type'] ?? 'D',
                    'Items' => $orderInfo['items'] ?? [],
                ],
            ];

            if (config('amplify.erp.use_amplify_shipping')) {
                $response = $this->getOrderTotalUsingBackend($payload);
            } else {

                $url = match (config('amplify.client_code')) {
                    'ACT', 'MW' => 'createOrder',
                    default => 'getOrderTotal',
                };
                $response = $this->post("/{$url}", $payload);
            }

            return $this->adapter->getOrderTotal($response);

        } catch (Exception $exception) {

            $this->exceptionHandler($exception);

            return $this->adapter->getOrderTotal();
        }
    }

    /**
     * Fetch shipment tracking URL
     *
     * @throws Exception
     */
    public function getTrackShipment(array $inputs = []): TrackShipment
    {
        try {

            $customer_number = $this->getCustomerDetail()->CustomerNumber;
            $invoice_number = $inputs['invoice_number'] ?? null;

            $query = [
                'content' => [
                    'CustomerNumber' => $customer_number,
                    'InvoiceNumber' => $invoice_number,
                ],
            ];

            $url = 'getTrackShipmentInfo';
            $response = $this->post("/{$url}", $query);

            return $this->adapter->getTrackShipment($response);

        } catch (Exception $exception) {
            $this->exceptionHandler($exception);

            return $this->adapter->getTrackShipment();
        }
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
}
