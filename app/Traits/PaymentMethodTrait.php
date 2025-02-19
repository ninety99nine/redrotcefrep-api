<?php

namespace App\Traits;

use App\Traits\Base\BaseTrait;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentMethodCategory;

trait PaymentMethodTrait
{
    use BaseTrait;

    /**
     *  Check if DPO payment method
     *
     *  @return bool
     */
    public function isDpo()
    {
        return $this->getRawOriginal('type') === PaymentMethodType::DPO->value;
    }

    /**
     *  Check if Orange Money payment method
     *
     *  @return bool
     */
    public function isOrangeMoney()
    {
        return $this->getRawOriginal('type') === PaymentMethodType::ORANGE_MONEY->value;
    }

    /**
     *  Check if Orange Airtime payment method
     *
     *  @return bool
     */
    public function isOrangeAirtime()
    {
        return $this->getRawOriginal('type') === PaymentMethodType::ORANGE_AIRTIME->value;
    }
}
