<?php

namespace Amplify\ErpApi\Traits;

use Amplify\ErpApi\Exceptions\InvalidBase64Data;
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
}
