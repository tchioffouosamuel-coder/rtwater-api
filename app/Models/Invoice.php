<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'number',
        'type',
        'status',
        'subtotal',
        'tax',
        'total',
        'pdf_path',
        'sent_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax'      => 'decimal:2',
            'total'    => 'decimal:2',
            'sent_at'  => 'datetime',
            'due_at'   => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateNumber(string $type = 'invoice'): string
    {
        $prefix = $type === 'quote' ? 'DEV' : 'FAC';
        $year   = now()->format('Y');
        $last   = static::whereYear('created_at', $year)->where('type', $type)->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $last);
    }
}
