<?php

namespace App\Models;

use App\Enums\RowLayout;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Row extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    public static function LAYOUTS(): array
    {
        return array_map(fn($layout) => $layout->value, RowLayout::cases());
    }

    protected $casts = [
        'visible' => 'boolean'
    ];

    protected $fillable = [
        'name', 'visible', 'background_color', 'layout', 'store_id'
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
        return $this->belongsToMany(Section::class, 'section_row');
    }

    public function columns()
    {
        return $this->belongsToMany(Column::class, 'row_column');
    }
}
