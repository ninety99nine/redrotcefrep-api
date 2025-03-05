<?php

namespace App\Traits;

use App\Traits\Base\BaseTrait;

trait PromotionTrait
{
    use BaseTrait;

    /**
     *  Get the hours of the day options
     *
     *  @return array
     */
    public function getHoursOfDayOptions() {

        $hoursOfDay = [];

        /**
         *  Generating hours of day e.g 00:00 until 23:00
         *
         *  $hoursOfDay = ['00:00', '01:00', ..., '23:00'];
         */
        for ($i = 0; $i < 24; $i++) {
            $hoursOfDay[] = sprintf('%02d:00', $i);
        }

        return $hoursOfDay;
    }

    /**
     *  Get the days of the month options
     *
     *  @return array
     */
    function getDaysOfTheMonthOptions() {

        $daysOfTheMonth = [];

        /**
         *  Generating days of the month e.g 01 until 31
         *
         *  $daysOfTheMonth = ['01', '02', ..., '31'];
         */
        for ($i = 1; $i <= 31; $i++) {
            $daysOfTheMonth[] = sprintf('%02d', $i);
        }

        return $daysOfTheMonth;
    }

    /**
     *  Get the months of the year options
     *
     *  @return array
     */
    function getMonthsOfTheYearOptions() {
        return [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    }

    /**
     *  Get the days of the week options starting from Monday
     *
     *  @return array
     */
    function getDaysOfTheWeekOptions() {
        return [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    }

}
