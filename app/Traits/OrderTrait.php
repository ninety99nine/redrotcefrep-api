<?php

namespace App\Traits;

use App\Models\Order;
use App\Traits\Base\BaseTrait;

trait OrderTrait
{
    use BaseTrait;

    /**
     *  Check if this order is paid
     *
     *  @return bool
     */
    public function isPaid()
    {
        return strtolower($this->getRawOriginal('payment_status')) === 'paid';
    }

    /**
     *  Check if this order is unpaid
     *
     *  @return bool
     */
    public function isUnpaid()
    {
        return strtolower($this->getRawOriginal('payment_status')) === 'unpaid';
    }

    /**
     *  Check if this order is partially paid
     *
     *  @return bool
     */
    public function isPartiallyPaid()
    {
        return strtolower($this->getRawOriginal('payment_status')) === 'partially paid';
    }

    /**
     *  Check if this order is pending payment
     *
     *  @return bool
     */
    public function isPendingPayment()
    {
        return strtolower($this->getRawOriginal('payment_status')) === 'pending payment';
    }

    /**
     *  Check if this order is waiting response from team members
     *
     *  @return bool
     */
    public function statusRawOriginalLowercase()
    {
        return strtolower($this->getRawOriginal('status'));
    }

    /**
     *  Check if this order is waiting response from team members
     *
     *  @return bool
     */
    public function isWaiting()
    {
        return $this->statusRawOriginalLowercase() === 'waiting';
    }

    /**
     *  Check if this order is waiting for delivery to be confirmed
     *
     *  @return bool
     */
    public function isOnItsWay()
    {
        return $this->statusRawOriginalLowercase() === 'on its way';
    }

    /**
     *  Check if this order is waiting for pickup to be confirmed
     *
     *  @param String $status (optional)
     *  @return bool
     */
    public function isReadyForPickup()
    {
        return $this->statusRawOriginalLowercase() === 'ready for pickup';
    }

    /**
     *  Check if this order is cancelled
     *
     *  @return bool
     */
    public function isCancelled()
    {
        return $this->statusRawOriginalLowercase() === 'cancelled';
    }

    /**
     *  Check if this order is completed
     *
     *  @return bool
     */
    public function isCompleted()
    {
        return $this->statusRawOriginalLowercase() === 'completed';
    }

    /**
     *  Make this order anonymous by overiding information
     *  that would expose the identity of the customer
     *
     *  @return Order
     */
    public function makeAnonymous() {

        $this->customer_display_name = null;
        $this->customer_first_name = null;
        $this->customer_last_name = null;
        $this->customer_name = null;
        $this->customer_id = null;
        $this->number = null;

        return $this;
    }
}
