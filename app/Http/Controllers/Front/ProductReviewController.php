<?php
// app/Http/Controllers/Front/ProductReviewController.php
namespace App\Http\Controllers\Front;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop\Product;
use App\Models\Shop\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductReviewController extends Controller
{
    public function store(Product $product, Request $request)
    {
        // простейший honeypot
        if ($request->filled('hp')) {
            return response()->json(['status' => 'ok']); // молча игнорим ботов
        }

        $captchaEnabled = (bool) config('services.turnstile.enabled')
            && filled((string) config('services.turnstile.site_key'))
            && filled((string) config('services.turnstile.secret_key'));

        $data = $request->validate([
            'name'    => ['required','string','max:100'],
            'email'   => ['nullable','email','max:150'],
            'content' => ['required','string','max:3000'],
            'rating'  => ['required','integer','min:1','max:5'],
            'cf-turnstile-response' => $captchaEnabled ? ['required', 'string'] : ['nullable', 'string'],
        ], [
            'cf-turnstile-response.required' => st('reviews.captcha_required', 'Підтвердіть, що ви не робот.'),
        ]);

        if ($captchaEnabled && ! $this->verifyTurnstile($request)) {
            return response()->json([
                'message' => st('reviews.captcha_failed', 'Помилка перевірки captcha. Спробуйте ще раз.'),
                'errors' => [
                    'cf-turnstile-response' => [st('reviews.captcha_failed', 'Помилка перевірки captcha. Спробуйте ще раз.')],
                ],
            ], 422);
        }

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

    private function verifyTurnstile(Request $request): bool
    {
        $token = trim((string) $request->input('cf-turnstile-response', ''));
        $secret = (string) config('services.turnstile.secret_key');

        if ($token === '' || $secret === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(8)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

        if (! $response->ok()) {
            return false;
        }

        return (bool) data_get($response->json(), 'success', false);
    }
}
