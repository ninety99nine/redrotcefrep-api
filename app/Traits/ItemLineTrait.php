<?php

namespace App\Traits;

use App\Traits\Base\BaseTrait;

trait ItemLineTrait
{
    use BaseTrait;

    /**
     *  Record the detected change on this item line
     *
     *  @param string $changeType
     *  @param string|null $message
     */
    public function recordDetectedChange($changeType, $message = null)
    {
        $this->detected_changes = collect($this->detected_changes)->push([
            'type' => $changeType,
            'message' => $message
        ])->all();

        return $this;
    }

    /**
     *  Return true / false whether the given change type
     *  exists on the item line detected changes
     *
     *  @param string $changeType
     */
    public function hasDetectedChange($changeType)
    {
        return collect($this->detected_changes)->contains(function($detectedChange) use ($changeType){
            return ($detectedChange['type'] == $changeType);
        });
    }

    /**
     *  Empty the detected changes
     */
    public function clearDetectedChanges()
    {
        $this->detected_changes = [];
        return $this;
    }

    /**
     *  Empty the cancellation reasons
     */
    public function clearCancellationReasons()
    {
        $this->cancellation_reasons = [];
        return $this;
    }

    /**
     *  Set the item line as cancelled
     *
     *  @param string|null $cancellationReason
     */
    public function cancelItemLine($cancellationReasons = null)
    {
        $this->is_cancelled = true;

        if( is_string($cancellationReasons) ) {

            $cancellationReasons = [ $cancellationReasons ];

        }elseif( is_null($cancellationReasons) ) {

            $cancellationReasons = [];

        }

        //  Set the message and datetime of each cancellation message
        collect($cancellationReasons)->map(function($cancellationReason) {
            return [
                'date' => now(),
                'message' => $cancellationReason
            ];
        });

        $this->cancellation_reasons = collect($this->cancellation_reasons)->push(...$cancellationReasons)->all();

        return $this;
    }

}
