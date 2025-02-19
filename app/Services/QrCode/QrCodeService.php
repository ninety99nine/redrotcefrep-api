<?php

namespace App\Services\QrCode;

use App\Services\AWS\AWSService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     *  Generate the QR Code PNG Image
     *
     *  Reference: https://www.simplesoftware.io/#/docs/simple-qrcode
     *
     *  @param $information The information to save on the QR Code PNG Image
     *  @return string
     */
    public static function generate($information)
    {
        //  Set the folder name
        $folderName = 'qr-codes';

        //  Create the QR Code PNG Image
        $qrCode = QrCode::format('png')->size(200)->generate($information);

        //  Save the QR Code PNG Image on AWS and return the url
        $url = AWSService::store($folderName, $qrCode);

        //  Return the QR Code PNG Image url
        return $url;
    }

    /**
     *  Regenerate the QR Code PNG Image
     *
     *  Reference: https://www.simplesoftware.io/#/docs/simple-qrcode
     *
     *  @param $url The url to the existing qr code that must be replaced
     *  @param $information The information to save on the QR Code PNG Image
     *  @return string
     */
    public static function generateAndReplace($url, $information)
    {
        if(AWSService::exists($url)) {
            AWSService::delete($url);
        }

        return self::generate($information);
    }
}
