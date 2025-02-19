<?php

namespace App\Models;

use App\Casts\JsonToArray;
use App\Enums\RequestFileName;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Column extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    protected $casts = [
        'settings' => JsonToArray::class
    ];

    protected $fillable = [
        'name', 'settings', 'store_id'
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

    public function rows()
    {
        return $this->belongsToMany(Row::class, 'row_column');
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'column_module')->orderByPivot('position');
    }
}
