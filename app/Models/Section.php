<?php

namespace App\Models;

use App\Enums\SectionDivider;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    public static function DIVIDERS(): array
    {
        return array_map(fn($topDivider) => $topDivider->value, SectionDivider::cases());
    }

    protected $casts = [
        'visible' => 'boolean'
    ];

    protected $fillable = [
        'name', 'visible', 'background_color', 'top_divider_type', 'top_divider_color', 'top_divider_height',
        'bottom_divider_type', 'bottom_divider_color', 'bottom_divider_height',
        'store_id'
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

    public function pages()
    {
        return $this->belongsToMany(Page::class, 'page_section');
    }

    public function rows()
    {
        return $this->belongsToMany(Row::class, 'section_row')->orderByPivot('position');
    }
}
