<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'sebpay_transaction_id',
        'operator',
        'phone',
        'amount',
        'currency',
        'status',
        'sebpay_response',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'sebpay_response'  => 'array',
            'confirmed_at'     => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
