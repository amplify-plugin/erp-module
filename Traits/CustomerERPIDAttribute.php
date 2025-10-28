<?php

namespace Amplify\ErpApi\Traits;

/**
 * Trait CustomerERPIDAttribute
 *
 * @property mixed $customer_erp_id
 */
trait CustomerERPIDAttribute
{
    /**
     * get current logged in contact customer ID field value
     *
     * @return null|mixed
     */
    public function getCustomerErpIdAttribute()
    {
        $activeConfig = config('amplify.erp.default');

        $erp_customer_id_field = config('amplify.erp.configurations.' . $activeConfig . '.customer_id_field');

        return $this->{$erp_customer_id_field} ?? config('amplify.frontend.guest_default', null);
    }
}
