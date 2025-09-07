<?php

use Amplify\ErpApi\ErpApiService;
use Amplify\ErpApi\Facades\ErpApi;

if (! function_exists('erp')) {
    /**
     * Return current instance of ERP API service class
     *
     * @throws InvalidArgumentException
     */
    function erp(?string $adapter = null): ErpApiService
    {
        return ErpApi::init($adapter);
    }
}

if (! function_exists('allow_guest_pricing')) {
    function allow_guest_pricing(): bool
    {
        $erp = config('amplify.erp.default', 'default');

        return (bool) config('amplify.frontend.guest_default', null);
    }
}

if (! function_exists('split_full_name')) {
    function split_full_name($name): array
    {
        // Trim and remove extra spaces
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // Split into parts
        $parts = explode(' ', $name);

        return [
            'last' => array_pop($parts),
            'first' => implode(' ', $parts),
        ];
    }
}
