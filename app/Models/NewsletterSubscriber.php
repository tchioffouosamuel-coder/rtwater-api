<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Inscrit à la lettre d'information. */
class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'source',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
        ];
    }
}
