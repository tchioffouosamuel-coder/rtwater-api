<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    protected static function booted(): void
    {
        \App\Support\CacheVersion::bustOnWrite(static::class, 'categories');
    }

    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'type',
    ];
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
