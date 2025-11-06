<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Collections\OrderDetailCollection;
use Amplify\ErpApi\Collections\OrderNoteCollection;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class Order
 *
 * @property string $CustomerNumber
 * @property string $ContactId
 * @property string $OrderNumber
 * @property string $OrderNote
 * @property string $OrderSuffix
 * @property string $OrderType
 * @property string $OrderStatus
 * @property string $CustomerName
 * @property string $BillToCountry
 * @property string $CustomerAddress1
 * @property string $CustomerAddress2
 * @property string $CustomerAddress3
 * @property string $BillToCity
 * @property string $BillToState
 * @property string $BillToZipCode
 * @property string $BillToContact
 * @property string $ShipToNumber
 * @property string $ShipToName
 * @property string $ShipToCountry
 * @property string $ShipToAddress1
 * @property string $ShipToAddress2
 * @property string $ShipToAddress3
 * @property string $ShipToCity
 * @property string $ShipToState
 * @property string $ShipToZipCode
 * @property string $ShipToContact
 * @property string $EntryDate
 * @property string $RequestedShipDate
 * @property string $PromiseDate
 * @property string $CustomerPurchaseOrdernumber
 * @property string $ItemSalesAmount
 * @property string $DiscountAmountTrading
 * @property string $SalesTaxAmount
 * @property string $InvoiceAmount
 * @property string $FreightAmount
 * @property string $TotalOrderValue
 * @property string $TotalSpecialCharges
 * @property string $CarrierCode
 * @property string $WarehouseID
 * @property string $InvoiceNumber
 * @property string $EmailAddress
 * @property string $BillToCountryName
 * @property string $ShipToCountryName
 * @property string $PdfAvailable
 * @property string $SignedDoc
 * @property string $SignedType
 * @property string $HazMatCharge
 * @property string $InHouseDeliveryDate
 * @property string $OrderDisposition
 * @property string $InvoiceDate
 * @property string|null $TrackingShipments
 * @property array $NoteList
 * @property OrderDetailCollection $OrderDetail
 * @property OrderNoteCollection $OrderNotes
 * @property array $ExtraCharges
 * @property string $FreightAccountNumber
 * @property string $RestockFee
 */
class Order extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'CustomerNumber', 'ContactId', 'OrderNumber', 'OrderSuffix', 'OrderType',
        'OrderStatus', 'CustomerName', 'BillToCountry', 'CustomerAddress1', 'CustomerAddress2', 'CustomerAddress3', 'BillToCity', 'BillToState',
        'BillToZipCode', 'BillToContact', 'ShipToNumber', 'ShipToName', 'ShipToCountry', 'ShipToAddress1', 'ShipToAddress2', 'ShipToAddress3',
        'ShipToCity', 'ShipToState', 'ShipToZipCode', 'ShipToContact', 'EntryDate', 'PromiseDate', 'RequestedShipDate', 'CustomerPurchaseOrdernumber', 'ItemSalesAmount',
        'DiscountAmountTrading', 'SalesTaxAmount', 'InvoiceAmount', 'FreightAmount', 'TotalOrderValue', 'TotalSpecialCharges', 'CarrierCode', 'WarehouseID',
        'InvoiceNumber', 'EmailAddress', 'BillToCountryName', 'ShipToCountryName', 'PdfAvailable', 'OrderDetail', 'OrderNotes', 'SignedDoc', 'SignedType',
        'HazMatCharge', 'InHouseDeliveryDate', 'OrderDisposition', 'InvoiceDate', 'TrackingShipments', 'NoteList', 'ExtraCharges', 'FreightAccountNumber', 'RestockFee'
    ];
}
