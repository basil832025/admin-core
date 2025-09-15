<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Shop\Product;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->active()
            ->select(['id','slug','title','short_name','main_image','price','category_id','description'])
            ->latest('id')
            ->paginate(12);

        return view('home', compact('products'));
    }
}
