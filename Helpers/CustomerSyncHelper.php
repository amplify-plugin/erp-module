<?php

namespace Amplify\ErpApi\Helpers;

use Amplify\System\Backend\Models\Country;

class CustomerSyncHelper
{
    /**
     * Default currency code from global config, falling back to USD.
     */
    public static function defaultCurrencyCode(): string
    {
        $currency = config('amplify.basic.global_currency', 'USD');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : 'USD';
    }

    /**
     * Default ISO2 from Basic → Countries config, else US.
     */
    public static function defaultCountryIso2(): string
    {
        $configured = config('amplify.basic.countries', []);

        if (is_array($configured) && $configured !== []) {
            $first = $configured[0];
            $id = is_array($first) ? ($first['id'] ?? null) : $first;

            if ($id !== null) {
                $iso2 = Country::query()->where('id', $id)->value('iso2');
                if (is_string($iso2) && $iso2 !== '') {
                    return strtoupper($iso2);
                }
            }
        }

        return 'US';
    }

    /**
     * Normalize ERP country values to a valid ISO2 code.
     * Accepts ISO2, ISO3, or exact country name; null when unresolvable.
     */
    public static function normalizeCountryIso2(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $upper = strtoupper($value);

        if (strlen($upper) === 2) {
            return Country::query()->where('iso2', $upper)->exists() ? $upper : null;
        }

        if (strlen($upper) === 3) {
            $iso2 = Country::query()->where('iso3', $upper)->value('iso2');

            return is_string($iso2) && $iso2 !== '' ? strtoupper($iso2) : null;
        }

        $iso2 = Country::query()->where('name', $value)->value('iso2');

        return is_string($iso2) && $iso2 !== '' ? strtoupper($iso2) : null;
    }
}
