<?php

namespace App\Models;

use App\Enums\ModuleType;
use App\Casts\JsonToArray;
use App\Enums\RequestFileName;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    public static function TYPES(): array
    {
        return array_map(fn($type) => $type->value, ModuleType::cases());
    }

    protected $casts = [
        'settings' => JsonToArray::class
    ];

    protected $fillable = [
        'name', 'type', 'settings', 'store_id'
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

    public function columns()
    {
        return $this->belongsToMany(Column::class, 'column_module');
    }

    public function mediaFiles()
    {
        return $this->morphMany(MediaFile::class, 'mediable');
    }
}
