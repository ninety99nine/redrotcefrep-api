<?php

namespace App\Models\Pivots;

use App\Models\Base\BasePivot;

class SmsMessageStoreAssociation extends BasePivot
{
    const VISIBLE_COLUMNS = ['id', 'created_at', 'updated_at'];
}
