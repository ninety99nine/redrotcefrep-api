<?php

namespace App\Models;

use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Page extends BaseModel
{
    use HasFactory;

    const TITLE_MIN_CHARACTERS = 3;
    const TITLE_MAX_CHARACTERS = 60;

    protected $casts = [
        'visible' => 'boolean',
        'homepage' => 'boolean'
    ];

    protected $fillable = [
        'title', 'visible', 'homepage', 'background_color', 'position', 'store_id'
    ];

    // Scopes

    public function scopeSearch($query, $searchWord)
    {
        return $query->where('name', 'like', "%$searchWord%");
    }

    // Relationships

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'page_section')->orderByPivot('position');
    }
}
