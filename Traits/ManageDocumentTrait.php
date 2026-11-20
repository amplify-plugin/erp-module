<?php

namespace Amplify\ErpApi\Traits;

use Amplify\ErpApi\Collections\ProductPriceAvailabilityCollection;
use Amplify\ErpApi\Exceptions\InvalidBase64Data;
use Amplify\ErpApi\Wrappers\ProductPriceAvailability;
use Amplify\ErpApi\Wrappers\Warehouse;
use Illuminate\Http\File;

trait ManageDocumentTrait
{
    /**
     * @throws InvalidBase64Data
     */
    private function convertBase64ToFile(string $content = ''): File
    {
        $binaryData = base64_decode($content, true);

        if ($binaryData === false) {
            throw InvalidBase64Data::create();
        }

        // decoding and then re-encoding should not change the data
        if (base64_encode($binaryData) !== $content) {
            throw InvalidBase64Data::create();
        }

        // temporarily store the decoded data on the filesystem to be able to pass it to the fileAdder
        $tmpFile = tempnam(sys_get_temp_dir(), 'media-library');
        file_put_contents($tmpFile, $binaryData);

        return new File($tmpFile);
    }

    private function convertDataToFile(array $data = []): File
    {

        //                $pdf = \Mccarlosen\LaravelMpdf\Facades\LaravelMpdf::loadView('pdf.invoice-details', ['invoice' => $invoice]);
        //
        //                return $pdf->download("invoice-details-{$id}.pdf");

        return new File('');
    }

    private function processWarehouseInventory(ProductPriceAvailabilityCollection &$collection): void
    {
        $temp = [];

        foreach ($collection as $item) {
            if (empty($item->ItemNumber)) {
                continue;
            }
            $temp[$item->ItemNumber][$item->WarehouseID] = [
                'qty' => floatval($item->QuantityAvailable),
                'price' => $item->Price,
                'uom' => $item->UnitOfMeasure,
            ];
        }

        $collection = $collection->map(function (ProductPriceAvailability $product) use (&$temp) {
            $product->Warehouses = $product->Warehouses->map(function (Warehouse $entry) use ($product, &$temp) {
                $data = $temp[$product->ItemNumber][$entry->WarehouseNumber] ?? [];
                if (!empty($data)) {
                    $entry->QuantityAvailable = $data['qty'] ?? null;
                    $entry->Price = $data['price'] ?? null;
                    $entry->UnitOfMeasure = $data['uom'] ?? null;
                }
                return $entry;
            });
            return $product;
        });
    }
}
