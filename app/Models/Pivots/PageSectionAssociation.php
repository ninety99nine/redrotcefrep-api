<?php

namespace App\Models\Pivots;

use App\Models\Base\BasePivot;

class PageSectionAssociation extends BasePivot
{
    const VISIBLE_COLUMNS = ['id', 'visible', 'position', 'created_at', 'updated_at'];
}
