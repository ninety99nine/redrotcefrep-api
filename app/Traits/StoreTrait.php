<?php

namespace App\Traits;

use App\Models\User;
use App\Traits\Base\BaseTrait;

trait StoreTrait
{
    use BaseTrait;

    /**
     *  Craft the store created successfully sms messsage to send to the user
     *
     *  @param User $user
     *
     *  @return string
     */
    public function craftStoreCreatedSmsMessage($user)
    {
        return 'Hi '.$user->first_name.', your store '.$this->name_with_emoji.' was created successfully. Subscribe to list your store on Bw Stores and for customers to place orders on '.$user->mobile_number_shortcode.' 😉';
    }
}
