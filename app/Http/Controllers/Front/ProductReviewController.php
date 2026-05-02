<?php
// app/Http/Controllers/Front/ProductReviewController.php
namespace App\Http\Controllers\Front;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Mail\ReviewNotificationMail;
use App\Models\Shop\Product;
use App\Models\Shop\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
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

        $review = ProductReview::create([
            'product_id' => $product->getKey(),
            'name'       => $data['name'],
            'email'      => $data['email'] ?? null,
            'content'    => $data['content'],
            'rating'     => (int) $data['rating'],
            'status'     => ReviewStatus::Pending,          // на модерацию
            'ip'         => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
        ]);

        // Notify admins (same recipients as order notifications)
        try {
            $notificationEmails = config('notifications.order_notification_email', []);
            if (is_string($notificationEmails)) {
                $notificationEmails = array_filter(array_map('trim', explode(',', $notificationEmails)));
            }

            if (! empty($notificationEmails)) {
                $moderationUrl = \App\Filament\Clusters\Products\Resources\ProductReviewResource::getUrl('edit', [
                    'record' => $review,
                ]);

                $review->loadMissing('product.parent');

                Mail::to($notificationEmails)->send(new ReviewNotificationMail(
                    type: 'product',
                    review: $review,
                    moderationUrl: $moderationUrl,
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send review notification email: '.$e->getMessage(), [
                'type' => 'product',
                'review_id' => $review->id ?? null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
