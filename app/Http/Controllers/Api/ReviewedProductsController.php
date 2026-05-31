<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ReviewedProductsController extends Controller
{
    public function index()
    {
        $products = Product::select('name', 'asin')
            ->orderBy('name')
            ->get();

        return response()->json([
            'products' => $products,
            'count'    => $products->count(),
        ]);
    }
}
