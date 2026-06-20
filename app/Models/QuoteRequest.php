<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class QuoteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'location',
        'solution_type',
        'volume_m3',
        'description',
        'status',
        'assigned_to',
        'invoice_id',
        'internal_notes',
    ];

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'in_progress']);
    }
}
