<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trace d'un import massif de produits : qui, quand, combien de lignes,
 * et quelles lignes ont été rejetées. Sert de journal dans le backoffice.
 */
class ProductImport extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'rows_total',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'images_count',
        'options',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'errors'  => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
