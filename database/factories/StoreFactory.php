<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Store::class;
    /**
     *  When Faker generates a null value, it is converted to a string representation of "null,"
     *  which is not equivalent to an actual null value in the database. We must change this to
     *  the proper null value. However if this value is any other value then return as is.
     */
    protected function convertNullValue($value)
    {
        return $value === 'null' ? null : $value;
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $deliveryNotes = [
            "We cannot deliver to PO boxes",
            "Delivery is free for orders over P50",
            "We offer contactless delivery upon request",
            "Please allow up to 2 business days for delivery",
            "Delivery times may be affected during peak periods",
            "We only deliver on Mondays, Wednesdays and Fridays",
            "Delivery times may vary depending on your location",
            "Orders must be placed before 2pm for same day delivery",
            "We do not offer delivery on weekends or public holidays",
            "Delivery may be delayed due to unforeseen circumstances",
            "Delivery is only available between 9am and 5pm on weekdays",
            "If your delivery is late, please contact us for assistance",
            "Our delivery driver will require a signature upon delivery",
            "Payment on delivery must be done using cash or credit card",
            "Please ensure someone is available to receive your delivery",
            "Delivery is only available within a 10km radius of our store",
            "Our delivery driver will contact you when they're on their way",
            "Delivery fees may apply for orders outside of our delivery area",
            "Delivery may be subject to customs fees for international orders",
            "Please inspect your delivery for any damages before accepting it",
            "Please double-check your delivery address before placing your order",
            "If you're not home, our driver will leave the package in a safe location",
            "If you need to change your delivery address, please contact us as soon as possible",
        ];

        $pickupNotes = [
            "Pickup is only available during business hours",
            "Please allow up to 24 hours for order processing",
            "Orders must be picked up within 3 business days",
            "Please bring a valid ID when picking up your order",
            "Please double-check your pickup location before arriving",
            "If you're running late, please contact us to let us know",
            "Please inspect your order before leaving the pickup location",
            "If you have any questions, please don't hesitate to contact us",
            "We are not responsible for any damages that occur during pickup",
            "If you can't make it, please contact us to reschedule your pickup",
            "Please ensure that your vehicle is suitable for carrying your order",
            "Please bring your order confirmation number with you when picking up",
            "If you need to cancel your pickup, please contact us as soon as possible",
            "If someone else will be picking up your order, please let us know in advance",
            "We reserve the right to refuse pickup to anyone who is intoxicated or disruptive",
        ];

        $deliveryDestinations = [
            ['name' => 'Maun', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Kanye', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Serowe', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Kasane', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Lobatse', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Mochudi', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Jwaneng', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Gaborone', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Mahalapye', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Molepolole', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Francistown', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Mogoditshane', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
            ['name' => 'Selibe-Phikwe', 'cost' => $this->faker->randomFloat(2, 40, 100), 'allow_free_delivery' => $this->faker->boolean()],
        ];

        // Select a random number of delivery destinations between 1 and 5
        $numDestinations = rand(1, 5);

        // Shuffle the delivery destinations array
        shuffle($deliveryDestinations);

        // Select the first $numDestinations destinations from the shuffled array
        $selectedDeliveryDestinations = array_slice($deliveryDestinations, 0, $numDestinations);

        $pickupDestinations = [
            ['name' => 'Maun', 'address' => $this->faker->address()],
            ['name' => 'Kanye', 'address' => $this->faker->address()],
            ['name' => 'Kasane', 'address' => $this->faker->address()],
            ['name' => 'Serowe', 'address' => $this->faker->address()],
            ['name' => 'Lobatse', 'address' => $this->faker->address()],
            ['name' => 'Mochudi', 'address' => $this->faker->address()],
            ['name' => 'Jwaneng', 'address' => $this->faker->address()],
            ['name' => 'Palapye', 'address' => $this->faker->address()],
            ['name' => 'Gaborone', 'address' => $this->faker->address()],
            ['name' => 'Mahalapye', 'address' => $this->faker->address()],
            ['name' => 'Molepolole', 'address' => $this->faker->address()],
            ['name' => 'Francistown', 'address' => $this->faker->address()],
            ['name' => 'Mogoditshane', 'address' => $this->faker->address()],
        ];

        // Select a random number of pickup destinations between 1 and 5
        $numDestinations = rand(1, 5);

        // Shuffle the pickup destinations array
        shuffle($pickupDestinations);

        // Select the first $numDestinations destinations from the shuffled array
        $selectedPickupDestinations = array_slice($pickupDestinations, 0, $numDestinations);

        $name = $this->faker->company();

        while ($name <= (strlen($name) > Store::NAME_MAX_CHARACTERS)) {
            $name = $this->faker->company();
        }

        $description = $this->faker->catchPhrase;

        while ($description <= (strlen($description) > Store::DESCRIPTION_MAX_CHARACTERS)) {
            $description = $this->faker->catchPhrase;
        }

        return [
            'name' => $name,
            'currency' => config('app.DEFAULT_CURRENCY'),
            'description' => $description,
            'online' => $this->faker->boolean(90),
            'verified' => $this->faker->boolean(20),
            'allow_pickup' => $this->faker->boolean(30),
            'allow_delivery' => $this->faker->boolean(30),
            'offline_message' => $this->faker->sentence(),
            'allow_free_delivery' => $this->faker->boolean(30),
            'pickup_destinations' => $selectedPickupDestinations,
            'delivery_destinations' => $selectedDeliveryDestinations,
            'pickup_note' => $this->faker->randomElement($pickupNotes),
            'delivery_flat_fee' => $this->faker->randomFloat(2, 0, 100),
            'delivery_note' => $this->faker->randomElement($deliveryNotes),
            'call_to_action' => 'Buy',
            'number_of_employees' => $this->faker->randomElement([$this->faker->numberBetween(1, 100), null])
        ];
    }
}
