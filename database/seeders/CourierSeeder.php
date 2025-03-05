<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;
use Database\Seeders\Traits\SeederHelper;

class CourierSeeder extends Seeder
{
    use SeederHelper;

    /**
     *  Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //  Foreach courier
        foreach($this->getCouriers() as $key => $courier) {

            //  Create courier
            Courier::create(array_merge(
                $courier,
                [
                    'position' => $key + 1
                ]
            ));

        }
    }

    /**
     *  Return the couriers
     *
     *  @return array
     */
    public function getCouriers() {
        return [
            ["name" => "DHL Express", "tracking_page" => "https://www.dhl.com/global-en/home/tracking.html"],
            ["name" => "FedEx", "tracking_page" => "https://www.fedex.com/en-us/tracking.html"],
            ["name" => "UPS", "tracking_page" => "https://www.ups.com/track"],
            ["name" => "USPS", "tracking_page" => "https://tools.usps.com/go/TrackConfirmAction_input"],
            ["name" => "Aramex", "tracking_page" => "https://www.aramex.com/track/"],
            ["name" => "TNT", "tracking_page" => "https://www.tnt.com/express/en_us/site/tracking.html"],
            ["name" => "DPD", "tracking_page" => "https://www.dpd.com/tracking"],
            ["name" => "GLS", "tracking_page" => "https://gls-group.eu/DE/en/parcel-tracking"],
            ["name" => "Royal Mail", "tracking_page" => "https://www.royalmail.com/track-your-item"],
            ["name" => "Canada Post", "tracking_page" => "https://www.canadapost.ca/trackweb/en"],
            ["name" => "China Post", "tracking_page" => "http://track-chinapost.com/"],
            ["name" => "EMS", "tracking_page" => "https://www.ems.post/en/global-network/tracking"],
            ["name" => "Blue Dart", "tracking_page" => "https://www.bluedart.com/trackdart"],
            ["name" => "Yodel", "tracking_page" => "https://www.yodel.co.uk/track"],
            ["name" => "Fastway Couriers", "tracking_page" => "https://www.fastway.com.au/tools/track/"],
            ["name" => "ParcelForce", "tracking_page" => "https://www.parcelforce.com/track-trace"],
            ["name" => "SF Express", "tracking_page" => "http://www.sf-express.com/us/en/dynamic_function/waybill/"],
            ["name" => "Cainiao", "tracking_page" => "https://global.cainiao.com/"],
            ["name" => "Amazon Logistics", "tracking_page" => "https://www.amazon.com/help/customer/display.html?nodeId=201117970"],
            ["name" => "ePacket", "tracking_page" => "https://www.17track.net/en"],
        ];
    }
}
