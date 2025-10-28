<?php

namespace Amplify\ErpApi\Traits;

/**
 * Trait CustomerERPIDAttribute
 *
 * @property mixed $erp_id
 */
trait CustomerERPIDAttribute
{
    /**
     * get current logged in contact customer ID field value
     *
     * @return null|mixed
     */
    public function getErpIdAttribute()
    {
        $activeConfig = config('amplify.erp.default');

        $erp_customer_id_field = config('amplify.erp.configurations.' . $activeConfig . '.customer_id_field');

        return $this->{$erp_customer_id_field} ?? config('amplify.frontend.guest_default', null);
    }
}
