<?php

namespace App\Observers;

use App\Models\Section;

class SectionObserver
{
    public function saving(Section $section)
    {
        if(!empty($section->top_divider_type)) {
            if(empty($section->top_divider_height)) $section->top_divider_height = 100;
            if(empty($section->top_divider_color)) $section->top_divider_color = '#3e83f8FF';
        }

        if(!empty($section->bottom_divider_type)) {
            if(empty($section->bottom_divider_height)) $section->bottom_divider_height = 100;
            if(empty($section->bottom_divider_color)) $section->bottom_divider_color = '#3e83f8FF';
        }
    }

    public function creating(Section $section)
    {
    }

    public function updating(Section $section)
    {
    }

    public function created(Section $section)
    {
    }

    public function updated(Section $section)
    {
    }

    public function deleting(Section $section)
    {
    }

    public function deleted(Section $section)
    {
    }

    public function restored(Section $section)
    {
    }

    public function forceDeleted(Section $section)
    {
    }
}
