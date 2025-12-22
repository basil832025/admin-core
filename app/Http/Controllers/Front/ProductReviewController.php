<?php
// app/Http/Controllers/Front/ProductReviewController.php
namespace App\Http\Controllers\Front;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop\Product;
use App\Models\Shop\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductReviewController extends Controller
{
    public function store(Product $product, Request $request)
    {
        // простейший honeypot
        if ($request->filled('hp')) {
            return response()->json(['status' => 'ok']); // молча игнорим ботов
        }

        $data = $request->validate([
            'name'    => ['required','string','max:100'],
            'email'   => ['nullable','email','max:150'],
            'content' => ['required','string','max:3000'],
            'rating'  => ['required','integer','min:1','max:5'],
        ]);

        ProductReview::create([
            'product_id' => $product->getKey(),
            'name'       => $data['name'],
            'email'      => $data['email'] ?? null,
            'content'    => $data['content'],
            'rating'     => (int) $data['rating'],
            'status'     => ReviewStatus::Pending,          // на модерацию
            'ip'         => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
