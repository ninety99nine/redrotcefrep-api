<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Enums\DeliveryMethodScheduleType;

trait DeliveryMethodTrait
{
    /**
     * Determine if a given date qualifies based on various constraints.
     *
     * @param string $date
     * @return bool|null
     */
    public function isValidDate($date): bool|null
    {
        if(!$this->set_schedule) return null;

        $selectedDate = Carbon::parse($date);

        if($this->same_day_delivery && !$selectedDate->isToday()) {
            return false;
        }

        // Check if the date is within the allowable range (minDate to maxDate)
        $minDate = $this->minDate();
        $maxDate = $this->maxDate();

        if ($selectedDate->isBefore($minDate) || ($maxDate && $selectedDate->isAfter($maxDate))) {
            return false;
        }

        // Check if the date is a disabled date based on operational hours
        $disabledDates = $this->datesDisabled();

        if (in_array($this->formattedDate($selectedDate), $disabledDates)) {
            return false;
        }

        // Check if the day of the week is disabled based on operational hours
        $dayOfWeekDisabled = $this->daysOfWeekDisabled();

        if (in_array($selectedDate->dayOfWeek, $dayOfWeekDisabled)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a given timeslot qualifies based on various constraints.
     *
     * @param string $date
     * @param string $timeSlot
     * @return bool|null
     */
    public function isValidTimeSlot(string $date, string $timeSlot): bool|null
    {
        if(!$this->set_schedule) return null;
        if($this->schedule_type != DeliveryMethodScheduleType::DATE_AND_TIME->value) return null;

        $availableTimeSlots = $this->availableTimeSlots($date);
        return in_array($timeSlot, $availableTimeSlots);
    }

    /**
     * Get the minimum date considering earliest delivery time.
     *
     * @return string
     */
    public function minDate(): string
    {
        $today = Carbon::today();

        if($this->same_day_delivery){
            return $this->formattedDate($today);
        }

        if ($this->require_minimum_notice_for_orders && $this->earliest_delivery_time_value > 0) {
            $minDate = $today->copy()->add(
                $this->earliest_delivery_time_value,
                $this->schedule_type == DeliveryMethodScheduleType::DATE->value ? 'days' : $this->earliest_delivery_time_unit
            );

            if ($minDate->isAfter($today)) {
                return $this->formattedDate($minDate);
            }
        }

        return $this->formattedDate($today);
    }

    /**
     * Get the maximum date considering latest delivery time.
     *
     * @return string|null
     */
    public function maxDate(): string|null
    {
        $today = Carbon::today();

        if($this->same_day_delivery){
            return $this->formattedDate($today);
        }

        if (!$this->restrict_maximum_notice_for_orders || $this->latest_delivery_time_value == 0) return null;

        $maxDate = $today->copy()->add($this->latest_delivery_time_value, 'days');

        return $this->formattedDate($maxDate);
    }

    /**
     * Get dates that are disabled for selection based on operational hours.
     *
     * @return array
     */
    public function datesDisabled(): array
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek;

        // Get operational hours for today
        $operationalHours = $this->operational_hours[$dayOfWeek];

        // If today is marked unavailable, disable it
        if (!$operationalHours['available']) {
            return [$this->formattedDate($today)];
        }

        // Check if the current time falls within the supported time slots
        $currentTime = $now->format('H:i');
        $isWithinTimeSlot = false;

        foreach ($operationalHours['hours'] as $range) {
            list($startTime, $endTime) = $range;

            if ($currentTime >= $startTime && $currentTime <= $endTime) {
                $isWithinTimeSlot = true;
                break;
            }
        }

        // If no valid time slots, disable today
        if (!$isWithinTimeSlot) {
            return [$this->formattedDate($today)];
        }

        // If today is supported, return an empty array
        return [];
    }

    /**
     * Get days of the week that are disabled based on operational hours.
     *
     * @return array
     */
    public function daysOfWeekDisabled(): array
    {
        return collect($this->operational_hours)
            ->map(function ($day, $index) {
                return !$day['available'] ? $index : null;
            })
            ->filter(function ($value) {
                return $value !== null;
            })
            ->values()
            ->toArray();
    }

    /**
     * Get available time slots for a selected date.
     *
     * @param string $date
     * @return array
     */
    public function availableTimeSlots(string $date): array
    {
        $now = Carbon::now();
        $selectedDate = Carbon::parse($date);
        $dayOfWeek = $selectedDate->dayOfWeek;
        $operationalHours = $this->operational_hours[$dayOfWeek];

        // Return an empty array if there are no operational hours
        if (!$operationalHours['available'] || count($operationalHours['hours']) === 0) {
            return [];
        }

        // Create a Set to store unique timeslots
        $uniqueTimeSlots = [];

        // Iterate over the operational hours for the chosen day
        foreach ($operationalHours['hours'] as $range) {
            list($startTime, $endTime) = $range;
            $startTimeInMinutes = $this->timeToMinutesFormat($startTime);
            $endTimeInMinutes = $this->timeToMinutesFormat($endTime);

            // Generate timeslots based on the configuration
            if ($this->auto_generate_time_slots) {
                $interval = $this->time_slot_interval_unit == 'hour'
                    ? $this->time_slot_interval_value * 60
                    : $this->time_slot_interval_value;

                for ($currentStartTimeInMinutes = $startTimeInMinutes; $currentStartTimeInMinutes + $interval <= $endTimeInMinutes; $currentStartTimeInMinutes += $interval) {
                    $currentEndTimeInMinutes = $currentStartTimeInMinutes + $interval;
                    $currentStartTime = $this->minutesToTimeFormat($currentStartTimeInMinutes);

                    $isLastItem = $currentStartTimeInMinutes + $interval >= $endTimeInMinutes;

                    $currentEndTime = $isLastItem
                        ? $this->minutesToTimeFormat($currentEndTimeInMinutes)
                        : $this->minutesToTimeFormat($currentEndTimeInMinutes - 1);

                    $endAt = $selectedDate->copy()->setTimeFromTimeString($currentEndTime);

                    // Do not consider the timeslot if it's in the past
                    if ($endAt->isBefore($now)) {
                        continue;
                    }

                    // Do not consider the timeslot if it is before the minimum notice for orders
                    if ($this->require_minimum_notice_for_orders && $this->earliest_delivery_time_value > 0) {
                        $minDate = $now->copy()->add($this->earliest_delivery_time_value, $this->schedule_type == DeliveryMethodScheduleType::DATE->value ? 'days' : $this->earliest_delivery_time_unit);
                        if ($endAt->isBefore($minDate)) {
                            continue;
                        }
                    }

                    // Add the timeslot to the Set (duplicates will be ignored)
                    $uniqueTimeSlots[] = "$currentStartTime - $currentEndTime";
                }
            } else {
                $endAt = $selectedDate->copy()->setTimeFromTimeString($endTime);

                // Ensure the timeslot is not in the past
                if ($endAt->isAfter($now)) {
                    $uniqueTimeSlots[] = "$startTime - $endTime";
                }
            }
        }

        // Sort the timeslots from earliest to latest
        usort($uniqueTimeSlots, function ($a, $b) {

            [$startA] = explode(" - ", $a);
            [$startB] = explode(" - ", $b);

            $timeA = strtotime($startA);
            $timeB = strtotime($startB);

            return $timeA <=> $timeB;

        });

        return $uniqueTimeSlots;
    }

    /**
     * Get all time slots for a selected date, ignoring restrictions.
     *
     * @param string $date
     * @return array
     */
    public function allTimeSlots(string $date): array
    {
        $selectedDate = Carbon::parse($date);
        $dayOfWeek = $selectedDate->dayOfWeek;
        $operationalHours = $this->operational_hours[$dayOfWeek];

        // Return an empty array if there are no operational hours
        if (empty($operationalHours['hours'])) {
            return [];
        }

        // Store all possible time slots
        $allTimeSlots = [];

        foreach ($operationalHours['hours'] as $range) {
            list($startTime, $endTime) = $range;
            $startTimeInMinutes = $this->timeToMinutesFormat($startTime);
            $endTimeInMinutes = $this->timeToMinutesFormat($endTime);

            if ($this->auto_generate_time_slots) {
                $interval = $this->time_slot_interval_unit == 'hour'
                    ? $this->time_slot_interval_value * 60
                    : $this->time_slot_interval_value;

                for ($currentStartTimeInMinutes = $startTimeInMinutes; $currentStartTimeInMinutes + $interval <= $endTimeInMinutes; $currentStartTimeInMinutes += $interval) {
                    $currentEndTimeInMinutes = $currentStartTimeInMinutes + $interval;
                    $currentStartTime = $this->minutesToTimeFormat($currentStartTimeInMinutes);
                    $currentEndTime = $this->minutesToTimeFormat($currentEndTimeInMinutes);

                    $allTimeSlots[] = "$currentStartTime - $currentEndTime";
                }
            } else {
                $allTimeSlots[] = "$startTime - $endTime";
            }
        }

        // Sort time slots from earliest to latest
        usort($allTimeSlots, function ($a, $b) {
            [$startA] = explode(" - ", $a);
            [$startB] = explode(" - ", $b);

            return strtotime($startA) <=> strtotime($startB);
        });

        return $allTimeSlots;
    }

    /**
     * Format date to a specific format.
     *
     * @param Carbon $date
     * @return string
     */
    private function formattedDate(Carbon $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * Convert time to a minutes format
     *
     * @param string $time
     * @return string
     */
    private function timeToMinutesFormat(string $time): string
    {
        list($hours, $minutes) = explode(':', $time);
        return $hours * 60 + $minutes;
    }

    /**
     * Convert minutes to a readable time format
     *
     * @param string $minutes
     * @return string
     */
    private function minutesToTimeFormat(string $minutes): string
    {
        $hours = str_pad(floor($minutes / 60), 2, '0', STR_PAD_LEFT);
        $mins = str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
        return "$hours:$mins";
    }
}
