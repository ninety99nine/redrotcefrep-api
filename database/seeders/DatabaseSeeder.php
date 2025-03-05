<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Traits\SeederHelper;

class DatabaseSeeder extends Seeder
{
    use SeederHelper;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->disableForeignKeyChecks();

        $this->truncate(
            'users', 'password_reset_tokens', 'failed_jobs', 'jobs', 'personal_access_tokens', 'stores', 'addresses',
            'delivery_addresses', 'occasions', 'payment_methods', 'orders', 'mobile_verifications',
            'products', 'variables', 'order_products', 'promotions', 'order_promotions', 'transactions',
            'pricing_plans', 'subscriptions', 'reviews', 'friend_groups',
            'friend_group_store_association', 'couriers',
            'friend_group_user_association', 'user_order_view_association',
            'user_store_association', 'sms_messages', 'notifications', 'ai_message_categories',
            'ai_assistants', 'ai_messages', 'ai_lessons', 'sms_alerts', 'sms_alert_activities', 'sms_alert_activity_associations',
            'sms_alert_activity_store_associations'
        );

        $this->enableForeignKeyChecks();

        /**
         *  Note that the order of the seeders matters since
         *  some seeders depend on the existence of data
         *  generate by the other seeders
         */

        $this->call(CourierSeeder::class);
        $this->call(PricingPlanSeeder::class);
        $this->call(PaymentMethodSeeder::class);
        //$this->call(UserSeeder::class);
        //$this->call(StoreSeeder::class);
        //$this->call(FriendGroupSeeder::class);
        //$this->call(FriendSeeder::class);
        $this->call(AiLessonSeeder::class);
        $this->call(AiMessageCategorySeeder::class);
        $this->call(SmsAlertActivitySeeder::class);
        $this->call(OccasionSeeder::class);
    }
}
