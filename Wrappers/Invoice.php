<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Collections\InvoiceCollection;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class Invoice
 *
 * @property string $AllowArPayments
 * @property string $InvoiceNumber
 * @property string $InvoiceType
 * @property string $InvoiceDisputeCode
 * @property string $FinanceChargeFlag
 * @property string $InvoiceDate
 * @property string $EntryDate
 * @property string $AgeDate
 * @property string $InvoiceAmount
 * @property string $InvoiceBalance
 * @property string $PendingPayment
 * @property string $DiscountAmount
 * @property string $DiscountDueDate
 * @property string $LastTransactionDate
 * @property string $PayDays
 * @property string $CustomerPONumber
 * @property string $OrderNumber
 * @property string $InvoiceStatus
 * @property string $InvoiceSuffix
 * @property InvoiceCollection $InvoiceDetail
 * @property string $HasInvoiceDetail
 * @property string $ShipToName
 * @property string $ShipToAddress1
 * @property string $ShipToAddress2
 * @property string $ShipToAddress3
 * @property string $ShipToCity
 * @property string $ShipToState
 * @property string $ShipToZipCode
 * @property string $ShipToCountry
 * @property string $OrderDisposition
 * @property string $CarrierCode
 * @property string $WarehouseID
 * @property string $DiscountAmountTrading
 * @property string $TotalSpecialCharges
 * @property string $FreightAmount
 * @property string $SalesTaxAmount
 * @property string $TotalOrderValue
 * @property string $NoteList
 * @property array $ExtraCharges
 * @property string $InvoiceDueDate
 * @property string $ItemSalesAmount
 * @property string $DaysOpen
 * @property string $FreightAccountNumber
 * @property string $RestockFee
 */
class Invoice extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['AllowArPayments', 'InvoiceNumber', 'InvoiceType', 'InvoiceDisputeCode', 'FinanceChargeFlag', 'InvoiceDate',
        'EntryDate', 'AgeDate', 'InvoiceAmount', 'InvoiceBalance', 'PendingPayment', 'DiscountAmount', 'DiscountDueDate', 'LastTransactionDate',
        'PayDays', 'CustomerPONumber', 'InvoiceDetail', 'HasInvoiceDetail', 'OrderNumber', 'InvoiceStatus', 'InvoiceSuffix', 'ShipToName', 'ShipToAddress1',
        'ShipToAddress2', 'ShipToAddress3', 'ShipToCity', 'ShipToState', 'ShipToZipCode', 'ShipToCountry', 'WarehouseID', 'OrderDisposition', 'CarrierCode',
        'DiscountAmountTrading', 'TotalSpecialCharges', 'FreightAmount', 'SalesTaxAmount', 'TotalOrderValue', 'NoteList', 'ExtraCharges', 'InvoiceDueDate',
        'ItemSalesAmount', 'DaysOpen', 'FreightAccountNumber ','RestockFee',
    ];
}
