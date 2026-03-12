<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
