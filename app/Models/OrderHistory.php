<?php

namespace App\Models;

use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderHistory extends BaseModel
{
    use HasFactory;

    protected $table = 'order_history';

    protected $fillable = [
        'comment', 'user_id', 'order_id', 'store_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
