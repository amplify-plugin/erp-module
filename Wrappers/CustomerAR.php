<?php

namespace Amplify\ErpApi\Wrappers;

use Amplify\ErpApi\Abstracts\Wrapper;
use Amplify\ErpApi\Interfaces\ErpApiWrapperInterface;
use Amplify\ErpApi\Traits\ApiWrapperTrait;

/**
 * @class CustomerAR
 *
 * @property string $CustomerNum
 * @property string $CustomerName
 * @property string $Address1
 * @property string $Address2
 * @property string $City
 * @property string $ZipCode
 * @property string $State
 * @property string $AgeDaysPeriod1
 * @property string $AgeDaysPeriod2
 * @property string $AgeDaysPeriod3
 * @property string $AgeDaysPeriod4
 * @property string $AgeDaysPeriod5
 * @property string $AmountDue
 * @property string $BillingPeriodAmount
 * @property string $DateOfFirstSale
 * @property string $DateOfLastPayment
 * @property string $DateOfLastSale
 * @property string $FutureAmount
 * @property string $OpenOrderAmount
 * @property string $SalesLastYearToDate
 * @property string $SalesMonthToDate
 * @property string $SalesYearToDate
 * @property string $TermsCode
 * @property string $TermsDescription
 * @property string $TradeAgePeriod1Amount
 * @property string $TradeAgePeriod2Amount
 * @property string $TradeAgePeriod3Amount
 * @property string $TradeAgePeriod4Amount
 * @property string $TradeAgePeriod5Amount
 * @property string $TradeAmountDue
 * @property string $TradeBillingPeriodAmount
 * @property string $AvgDaysToPay1
 * @property string $AvgDaysToPay1Wgt
 * @property string $AvgDaysToPay2
 * @property string $AvgDaysToPay2Wgt
 * @property string $AvgDaysToPay3
 * @property string $AvgDaysToPay3Wgt
 * @property string $AvgDaysToPayDesc1
 * @property string $AvgDaysToPayDesc2
 * @property string $AvgDaysToPayDesc3
 * @property string $CreditCheckType
 * @property string $CreditLimit
 * @property string $HighBalance
 * @property string $LastPayAmount
 * @property string $NumInvPastDue
 * @property string $NumOpenInv
 * @property string $NumPayments1
 * @property string $NumPayments2
 * @property string $NumPayments3
 * @property string $TradeAgePeriod1Text
 * @property string $TradeAgePeriod2Text
 * @property string $TradeAgePeriod3Text
 * @property string $TradeAgePeriod4Text
 * @property string $TradeAgePeriod5Text
 * @property string $TradeBillingPeriodText
 */
class CustomerAR extends Wrapper implements ErpApiWrapperInterface
{
    use ApiWrapperTrait;

    protected array $fillable = ['CustomerNum', 'CustomerName', 'Address1', 'Address2', 'City', 'ZipCode', 'State',
        'AgeDaysPeriod1', 'AgeDaysPeriod2', 'AgeDaysPeriod3', 'AgeDaysPeriod4', 'AgeDaysPeriod5',
        'AmountDue', 'BillingPeriodAmount', 'DateOfFirstSale', 'DateOfLastPayment', 'DateOfLastSale',
        'FutureAmount', 'OpenOrderAmount', 'SalesLastYearToDate', 'SalesMonthToDate',
        'SalesYearToDate', 'TermsCode', 'TermsDescription', 'TradeAgePeriod1Amount',
        'TradeAgePeriod2Amount', 'TradeAgePeriod3Amount', 'TradeAgePeriod4Amount', 'TradeAgePeriod5Amount', 'TradeAmountDue', 'TradeBillingPeriodAmount',
        'AvgDaysToPay1', 'AvgDaysToPay1Wgt', 'AvgDaysToPay2', 'AvgDaysToPay2Wgt', 'AvgDaysToPay3', 'AvgDaysToPay3Wgt',
        'AvgDaysToPayDesc1', 'AvgDaysToPayDesc2', 'AvgDaysToPayDesc3', 'CreditCheckType', 'CreditLimit', 'HighBalance', 'LastPayAmount',
        'NumInvPastDue', 'NumOpenInv', 'NumPayments1', 'NumPayments2', 'NumPayments3',
        'TradeAgePeriod1Text', 'TradeAgePeriod2Text', 'TradeAgePeriod3Text', 'TradeAgePeriod4Text', 'TradeAgePeriod5Text', 'TradeBillingPeriodText',
    ];
}
