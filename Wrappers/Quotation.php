<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Collections\OrderDetailCollection;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * class Quotation
 *
 * @property string $CustomerNumber
 * @property string $ContactId
 * @property string $QuoteNumber
 * @property string $QuoteType
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
 * @property string $ExpirationDate
 * @property string $RequestedShipDate
 * @property string $CustomerPurchaseOrdernumber
 * @property string $ItemSalesAmount
 * @property string $DiscountAmountTrading
 * @property string $SalesTaxAmount
 * @property string $QuoteAmount
 * @property string $TotalOrderValue
 * @property string $TotalSpecialCharges
 * @property string $CarrierCode
 * @property string $WarehouseID
 * @property string $EmailAddress
 * @property string $BillToCountryName
 * @property string $ShipToCountryName
 * @property string $PdfAvailable
 * @property OrderDetailCollection $QuoteDetail
 * @property string $QuotedTo
 * @property string $EffectiveDate
 * @property string $Title
 * @property string $FreightAmount
 * @property string $OrderNotes
 * @property mixed|null $QuotedBy
 * @property mixed|null $QuotedByEmail
 * @property mixed|null $shippingList
 * @property string|null $Suffix
 * @property array|null $NoteList
 */
class Quotation extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = [
        'CustomerNumber', 'ContactId', 'QuoteNumber', 'QuoteType', 'OrderStatus', 'CustomerName', 'BillToCountry',
        'CustomerAddress1', 'CustomerAddress2', 'CustomerAddress3', 'BillToCity', 'BillToState', 'BillToZipCode',
        'BillToContact', 'ShipToNumber', 'ShipToName', 'ShipToCountry', 'ShipToAddress1', 'ShipToAddress2',
        'ShipToAddress3', 'ShipToCity', 'ShipToState', 'ShipToZipCode', 'ShipToContact', 'EntryDate', 'shippingList',
        'ExpirationDate', 'RequestedShipDate', 'CustomerPurchaseOrdernumber', 'ItemSalesAmount', 'QuotedBy',
        'DiscountAmountTrading', 'SalesTaxAmount', 'QuoteAmount', 'TotalOrderValue', 'TotalSpecialCharges',
        'CarrierCode', 'WarehouseID', 'EmailAddress', 'BillToCountryName', 'ShipToCountryName', 'PdfAvailable',
        'QuoteDetail', 'QuotedTo', 'EffectiveDate', 'Title', 'FreightAmount', 'OrderNotes', 'QuotedByEmail', 'Suffix', 'NoteList',
    ];
}
