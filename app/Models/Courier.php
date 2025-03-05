<?php

namespace App\Models;

use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Courier extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 40;
    const TRACKING_PAGE_MIN_CHARACTERS = 3;
    const TRACKING_PAGE_MAX_CHARACTERS = 255;

    protected $fillable = [
        'name', 'tracking_page', 'position'
    ];

    /****************************
     *  SCOPES                  *
     ***************************/

    public function scopeSearch($query, $searchWord)
    {
        return $query->where('name', 'like', "%$searchWord%");
    }

    /********************
     *  RELATIONSHIPS   *
     *******************/

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
