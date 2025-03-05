<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition()
    {
        $hoursOfDay = collect([
            '00:00', '01:00', '02:00', '03:00', '04:00', '05:00',
            '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
            '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
            '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'
        ])->shuffle()->toArray();

        $daysOfTheWeek = collect([
            'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
        ])->shuffle()->toArray();

        $daysOfTheMonth = collect([
            '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
            '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
            '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
            '31'
            ])->shuffle()->toArray();

        $monthsOfTheYear = collect([
            'January', 'February', 'March', 'April', 'May', 'June', 'July',
            'August', 'September', 'October', 'November', 'December'
        ])->shuffle()->toArray();

        $code = $this->faker->word();

        while ($code <= (strlen($code) > Promotion::CODE_MAX_CHARACTERS)) {
            $code = $this->faker->word();
        }

        $description = $this->faker->sentence(10);

        while ($description <= (strlen($description) > Promotion::DESCRIPTION_MAX_CHARACTERS)) {
            $description = $this->faker->sentence(10);
        }

        $offerDisount = $this->faker->boolean(70);
        $activateUsingCode = $this->faker->boolean(20);
        $code = $activateUsingCode ? $code : null;
        $activateUsingHoursOfDay = $this->faker->boolean(5);
        $activateUsingUsageLimit = $this->faker->boolean(5);
        $activateUsingDaysOfTheWeek = $this->faker->boolean(5);
        $activateUsingStartDatetime = $this->faker->boolean(5);
        $activateUsingDaysOfTheMonth = $this->faker->boolean(5);
        $activateUsingMonthsOfTheYear = $this->faker->boolean(5);
        $discountFixedRate = $this->faker->numberBetween(5, 100);
        $minimumTotalProducts = $this->faker->numberBetween(2, 5);
        $activateUsingMinimumGrandTotal = $this->faker->boolean(5);
        $startDatetime = $activateUsingStartDatetime ? now() : null;
        $activateUsingMinimumTotalProducts = $this->faker->boolean(5);
        $remainingQuantity = $activateUsingUsageLimit ? rand(10, 100) : 0;
        $minimumTotalProductQuantities = $this->faker->numberBetween(2, 10);
        $offerFreeDelivery = $offerDisount ? $this->faker->boolean(20): true;
        $activateUsingMinimumTotalProductQuantities = $this->faker->boolean(5);
        $discountType = $this->faker->randomElement(Promotion::DISCOUNT_TYPES());
        $activateUsingEndDatetime = $activateUsingStartDatetime ? true : $this->faker->boolean(5);
        $endDatetime = $activateUsingEndDatetime ? now()->addWeek(rand(1, 4)) : null;
        $discountPercentageRate = $this->faker->randomElement(['5', '10', '20', '30', '40', '50']);
        $minimumGrandTotal = $activateUsingMinimumGrandTotal ? $this->faker->numberBetween(100, 1000) : 0;
        $hoursOfDay = $activateUsingHoursOfDay ? $this->faker->randomElements($hoursOfDay, rand(1, 12)) : [];
        $daysOfTheWeek = $activateUsingDaysOfTheWeek ? $this->faker->randomElements($daysOfTheWeek, rand(1, 7)) : [];
        $daysOfTheMonth = $activateUsingDaysOfTheMonth ? $this->faker->randomElements($daysOfTheMonth, rand(1, 10)) : [];
        $monthsOfTheYear = $activateUsingMonthsOfTheYear ? $this->faker->randomElements($monthsOfTheYear, rand(1, 6)) : [];

        return [
            'code' => $code,
            'currency' => 'BWP',
            'description' => $description,
            'hours_of_day' => $hoursOfDay,
            'end_datetime' => $endDatetime,
            'discount_type' => $discountType,
            'offer_discount' => $offerDisount,
            'start_datetime' => $startDatetime,
            'days_of_the_week' => $daysOfTheWeek,
            'active' => $this->faker->boolean(5),
            'days_of_the_month' => $daysOfTheMonth,
            'months_of_the_year' => $monthsOfTheYear,
            'remaining_quantity' => $remainingQuantity,
            'discount_fixed_rate' => $discountFixedRate,
            'offer_free_delivery' => $offerFreeDelivery,
            'activate_using_code' => $activateUsingCode,
            'minimum_grand_total' => $minimumGrandTotal,
            'name' => $this->faker->words(rand(1, 3), true),
            'minimum_total_products' => $minimumTotalProducts,
            'discount_percentage_rate' => $discountPercentageRate,
            'activate_for_new_customer' => $this->faker->boolean(5),
            'activate_using_usage_limit' => $activateUsingUsageLimit,
            'activate_using_hours_of_day' => $activateUsingHoursOfDay,
            'activate_using_end_datetime' => $activateUsingEndDatetime,
            'activate_for_existing_customer' => $this->faker->boolean(5),
            'activate_using_start_datetime' => $activateUsingStartDatetime,
            'activate_using_days_of_the_week' => $activateUsingDaysOfTheWeek,
            'activate_using_days_of_the_month' => $activateUsingDaysOfTheMonth,
            'activate_using_months_of_the_year' => $activateUsingMonthsOfTheYear,
            'minimum_total_product_quantities' => $minimumTotalProductQuantities,
            'activate_using_minimum_grand_total' => $activateUsingMinimumGrandTotal,
            'activate_using_minimum_total_products' => $activateUsingMinimumTotalProducts,
            'activate_using_minimum_total_product_quantities' => $activateUsingMinimumTotalProductQuantities,
        ];
    }
}
