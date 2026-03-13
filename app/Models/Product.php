<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model {
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'category',
        'description',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected function image(): Attribute {
        return Attribute::make(
            get: fn($value) => $value ? config('app.url') . '/storage/products/' . $value : null,
        );
    }

    public function scopeFilterByName($query, $name) {
        if (!$name) {
            return $query;
        }
        return $query->where('name', 'like', '%' . $name . '%');
    }

    public function scopeFilterByCategories($query, $categories) {
        if (!$categories) {
            return $query;
        }
        $categoryArray = is_string($categories) ? explode(',', $categories) : $categories;
        return $query->whereIn('category', $categoryArray);
    }
}
