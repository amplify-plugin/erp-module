<?php

namespace Amplify\ErpApi\Traits;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\ErpApi\Wrappers\ShippingOption;
use Amplify\ErpApi\Wrappers\Warehouse;
use Amplify\System\Backend\Models\Shipping;
use Amplify\System\Backend\Models\ThresholdRange;
use Illuminate\Support\Str;

trait BackendShippingCostTrait
{
    private mixed $cartItems;

    protected array $shippingInfo = [];

    protected string $clientCode;

    public function setShippingInfo(array $shippingInfo): void
    {
        $this->shippingInfo = $shippingInfo;
    }

    private function hasPickUpItems(): bool
    {
        if ($cartFirstItem = $this->cartItems->first()) {
            if ($warehouse = $cartFirstItem->warehouse) {
                return $warehouse->pickup_location == true;
            }
        }

        return false;
    }

    private function hasOwnTruckItems(): bool
    {
        foreach ($this->cartItems as $cartItem) {
            $data = $cartItem->additional_info;
            if (isset($data['own_truck_only']) && $data['own_truck_only'] === true) {
                return true;
            }
        }

        return false;
    }

    public function getOrderTotalUsingBackend(): array
    {
        $shippingOptions = $this->getShippingOption();
        $cart = getCart();
        $this->cartItems = $cart->cartItems->isNotEmpty() ? $cart->cartItems : collect();

        $orderTotal = [
            'OrderNumber' => '',
            'TotalOrderValue' => $cart->total ?? 0,
            'SalesTaxAmount' => '',
            'FreightAmount' => '',
            'FreightRate' => [],
        ];

        $this->clientCode = strtoupper(config('amplify.client_code'));
        $freightMeta = [
            'frttermscd' => '',
            'carrierid' => '',
            'accountnumber' => '',
        ];

        if ($this->clientCode === 'STV') {
            $freightDetailsList = $this->getFreightDetails();

            if (! empty($freightDetailsList)) {
                $freightMeta['frttermscd'] = strtoupper($freightDetailsList[0]['frttermscd'] ?? '');

                $shipVia = strtoupper($this->shippingInfo['ship_via'] ?? '');
                $firstChar = strtoupper(substr($shipVia, 0, 1));
                $matchCarrier = $firstChar === 'U' ? 'UPS' : ($firstChar === 'F' ? 'FEDEX' : null);

                if ($matchCarrier) {
                    foreach ($freightDetailsList as $freight) {
                        if (strtoupper($freight['carrierid']) === $matchCarrier) {
                            $freightMeta['carrierid'] = $matchCarrier;
                            $freightMeta['accountnumber'] = $freight['accountnumber'] ?? '';
                            break;
                        }
                    }
                }
            }

        }

        $countryCode = strtoupper($this->shippingInfo['country_code'] ?? '');
        $isInternational = $this->isInternationalCustomer($countryCode);

        foreach ($shippingOptions as $shippingOption) {
            if ($this->clientCode === 'STV' && $isInternational && $shippingOption->Driver !== 'FREIGHT_COLLECT') {
                continue;
            }

            match ($shippingOption->Driver) {
                'OWN_TRUCK' => $this->getOwnTruckShippingOption($orderTotal, $shippingOption),
                'PICKUP' => $this->getPickUpShipOption($orderTotal, $shippingOption),
                'UPS' => $this->getUpsShipOption($orderTotal, $shippingOption),
                'FREIGHT_COLLECT' => $this->getFreightCollectShipOption($orderTotal, $shippingOption, $freightMeta),
                default => $this->getDefaultShipOption($orderTotal, $shippingOption),
            };
        }

        // Determine priority key from first freightDetails item if exists
        $priorityKey = match ($freightDetailsList[0]['frttermscd'] ?? null) {
            'C' => 'Freight Collect',
            'PPA' => 'UPS Prepaid and Collect',
            default => null,
        };

        if (config('amplify.erp.add_ship_will_call_option')) {
            $orderTotal['FreightRate']['WILL CALL'][] = $this->getWillCallShipOptions();
        }

        // Move priority shipping tab to top
        if ($priorityKey && isset($orderTotal['FreightRate'][$priorityKey])) {
            $orderTotal['FreightRate'] = [
                $priorityKey => $orderTotal['FreightRate'][$priorityKey],
            ] + array_diff_key($orderTotal['FreightRate'], [$priorityKey => null]);
        }

        return ['Order' => [$orderTotal]];
    }

    private function getPickUpShipOption(array &$orderTotal, ShippingOption $option): void
    {
        $driverKey = $option->Driver ?? 'UNKNOWN';
        $driverLabel = Shipping::SHIP_OPTIONS[$driverKey] ?? $driverKey;

        // Case 1: Config enables showing all pickup-enabled warehouses
        if (config('amplify.order.use_pickup_enable_warehouses_as_shipping_methods')) {
            foreach ($this->getWarehouses() as $warehouse) {
                if (! $warehouse->IsPickUpLocation) {
                    continue;
                }

                $customerDefaultWarehouse = $this->shippingInfo['default_warehouse'] ?? null;

                if (
                    $customerDefaultWarehouse
                    && strtolower($warehouse->WarehouseNumber) === strtolower($customerDefaultWarehouse)
                ) {
                    $orderTotal['FreightRate'][$driverLabel][] = [
                        $warehouse->InternalId => $this->buildPickupRateData($warehouse),
                    ];
                    $orderTotal['FreightAmount'] = currency_format('0.00');
                }

                return;
            }
        }

        // Case 2: At least one item in cart is from a pickup location
        if ($this->hasPickUpItems()) {
            $warehouse = $this->cartItems->first()->warehouse;

            $orderTotal['FreightRate'][$driverLabel][] = [
                $warehouse->id => [
                    'name' => Str::upper($warehouse->name),
                    'shipvia' => $warehouse->code,
                    'code' => '',
                    'fullday' => '',
                    'date' => '',
                    'nrates' => '',
                    'amount' => currency_format('0.00'),
                    'address1' => '',
                    'address2' => '',
                    'city' => '',
                    'state' => '',
                    'zip' => '',
                    'email' => '',
                    'telephone' => '',
                ],
            ];

            $orderTotal['FreightAmount'] = currency_format('0.00');
        }
    }

    private function getOwnTruckShippingOption(array &$orderTotal, ShippingOption $option): void
    {
        if ($this->hasOwnTruckItems()) {
            $customer = customer();
            if (ErpApi::getCustomerDetail()->CarrierCode == 'OT' && ! empty($customer->own_truck_ship_charge)) {

                $method = $option->CarrierCode;

                $orderTotal['FreightRate'][Str::upper(config('app.name'))][] = [
                    $method => [
                        'name' => Str::upper($option->Name),
                        'shipvia' => $method,
                        'fullday' => '',
                        'date' => '',
                        'nrates' => '',
                        'amount' => currency_format($customer->own_truck_ship_charge),
                        'address1' => '',
                        'address2' => '',
                        'city' => '',
                        'state' => '',
                        'zip' => '',
                        'email' => '',
                        'telephone' => '',
                    ],
                ];

                $orderTotal['FreightAmount'] = currency_format($customer->own_truck_ship_charge);
            }
        }
    }

    private function getDefaultShipOption(array &$orderTotal, ShippingOption $option): void
    {

        if (! $this->hasOwnTruckItems()) {

            $thresholdSlot = ThresholdRange::query()
                ->where('from', '<=', $orderTotal['TotalOrderValue'])
                ->where('to', '>=', $orderTotal['TotalOrderValue'])
                ->where('shipping_id', $option->InternalId)
                ->first();

            $frightAmount = ! empty($thresholdSlot) ? currency_format($thresholdSlot->amount) : 0;

            $method = $option->CarrierCode;

            $orderTotal['FreightRate'][Str::upper(config('app.name'))][] = [
                $method => [
                    'name' => Str::upper($option->Name),
                    'shipvia' => $method,
                    'fullday' => '',
                    'date' => '',
                    'nrates' => '',
                    'amount' => $frightAmount,
                    'address1' => '',
                    'address2' => '',
                    'city' => '',
                    'state' => '',
                    'zip' => '',
                    'email' => '',
                    'telephone' => '',
                ],
            ];

            $orderTotal['FreightAmount'] = $frightAmount;
        }
    }

    private function getWillCallShipOptions(string $shipVia = 'WILL CALL'): array
    {
        $warehouses = [];
        /**
         * @var \Amplify\ErpApi\Wrappers\Warehouse $warehouse
         */
        foreach ($this->getWarehouses() as $warehouse) {
            $warehouses[Str::upper($warehouse->WarehouseName)] = [
                'name' => $shipVia,
                'shipvia' => $shipVia,
                'fullday' => '',
                'date' => '',
                'nrates' => '',
                'amount' => '0.00',
                'address1' => Str::upper($warehouse->WarehouseAddress),
                'address2' => '',
                'city' => '',
                'state' => '',
                'zip' => $warehouse->WarehouseZip,
                'email' => $warehouse->WarehouseEmail,
                'telephone' => $warehouse->WarehousePhone,
            ];
        }

        return $warehouses;

        //        $warehouses = Warehouse::whereIn('code', $carts->pluck('WarehouseID')->unique()->toArray())->wherePickupLocation(true)
        //            ->get()
        //            ->groupBy('name')
        //            ->mapWithKeys(function ($items, $key): array {
        //                $item = $items->first();
        //
        //                return [
        //                    strtoupper($key) => [
        //                        'shipvia' => 'WILL CALL',
        //                        'fullday' => '',
        //                        'date' => '',
        //                        'nrates' => '',
        //                        'amount' => '0.00',
        //                        'address1' => $item['address'],
        //                        'address2' => '',
        //                        'city' => '',
        //                        'state' => '',
        //                        'zip' => $item['zip_code'],
        //                        'email' => $item['email'],
        //                        'telephone' => $item['telephone'],
        //                    ],
        //                ];
        //            })
        //            ->toArray();
        //
        //        $quantityAvailable = $products->pluck('QuantityAvailable')->toArray();
        //
        //        $isBackOrder = $carts
        //            ->zip($quantityAvailable)
        //            ->contains(function ($pair): bool {
        //                [$cart, $quantity] = $pair;
        //
        //                return $quantity < $cart['OrderQty'];
        //            });
        //
        //        if (count($warehouses) > 0 && !$isBackOrder) {
        //            $shippingMethods['FreightRate']['WILL CALL'][0] = $warehouses;
        //        } else {
        //            unset($shippingMethods['FreightRate']['WILL CALL']);
        //        }
        //
        //        return $shippingMethods;

    }

    private function getUpsShipOption(array &$orderTotal, $shippingOption): void
    {
        $driverKey = $shippingOption->Driver ?? 'UNKNOWN';
        $method = $shippingOption->CarrierCode;
        $name = Str::upper($shippingOption->Name ?? $method);
        $amount = '0.00'; // default
        $driverLabel = Shipping::SHIP_OPTIONS[$driverKey] ?? $driverKey;

        $orderTotal['FreightRate'][$driverLabel][] = [
            $name => [
                'name' => $name,
                'shipvia' => $method,
                'code' => '',
                'fullday' => '',
                'date' => '',
                'nrates' => '',
                'amount' => $amount,
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'email' => '',
                'telephone' => '',
                'description' => $shippingOption->Description,
                'frttermscd' => 'PPA', // Freight Terms Code
            ],
        ];

    }

    private function getFreightCollectShipOption(array &$orderTotal, $shippingOption, array $freightMeta = []): void
    {
        $driverKey = $shippingOption->Driver ?? 'UNKNOWN';
        $method = $shippingOption->CarrierCode;
        $name = Str::upper($shippingOption->Name ?? $method);
        $driverLabel = Shipping::SHIP_OPTIONS[$driverKey] ?? $driverKey;
        $value = $shippingOption->Value ?? null;

        $clientCode = $this->clientCode;
        $shipVia = strtoupper($this->shippingInfo['ship_via'] ?? '');
        $countryCode = strtoupper($this->shippingInfo['country_code'] ?? '');
        $valueLower = strtolower(trim($value));
        $isInternational = $this->isInternationalCustomer($countryCode);

        // Unpack the associative array
        $frtTermsCd = strtoupper($freightMeta['frttermscd'] ?? '');
        $carrierId = strtoupper($freightMeta['carrierid'] ?? '');
        $accountNumber = $freightMeta['accountnumber'] ?? '';

        // STV-only filtering logic
        if ($clientCode === 'STV') {
            if ($isInternational) {
                if ($valueLower !== 'international') {
                    return;
                }
            } elseif ($countryCode === 'CA') {
                $allowedMethodsCanada = ['canada', 'ups', 'fedex'];
                if (! in_array($valueLower, $allowedMethodsCanada)) {
                    return;
                }
            } else {
                if ($carrierId === 'UPS' && $valueLower !== 'ups') {
                    return;
                }
                if ($carrierId === 'FEDEX' && $valueLower !== 'fedex') {
                    return;
                }
                if (empty($carrierId) && ! in_array($valueLower, ['ups', 'fedex'])) {
                    return;
                }
            }
        }

        $data = [
            'name' => $name,
            'shipvia' => $method,
            'code' => '',
            'fullday' => '',
            'date' => '',
            'nrates' => '',
            'amount' => '0.00',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'email' => '',
            'telephone' => '',
            'description' => $shippingOption->Description,
            'value' => $value,
            'account_number' => $accountNumber ?? '',
            'frttermscd' => 'C', // freight terms code
        ];

        $orderTotal['FreightRate'][$driverLabel][] = [
            $name => $data,
        ];
    }

    private function buildPickupRateData(Warehouse $warehouse): array
    {
        return [
            'name' => $warehouse->WarehouseName,
            'shipvia' => $warehouse->ShipVia,
            'code' => $warehouse->WarehouseNumber,
            'fullday' => '',
            'date' => '',
            'nrates' => '',
            'amount' => currency_format('0.00'),
            'address1' => $warehouse->WarehouseAddress,
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'email' => '',
            'telephone' => '',
            'frttermscd' => 'CPU',
        ];
    }

    private function isInternationalCustomer(string $countryCode): bool
    {
        $domesticCountries = ['US', 'CA', 'MX'];

        return ! in_array(strtoupper($countryCode), $domesticCountries);
    }
}
