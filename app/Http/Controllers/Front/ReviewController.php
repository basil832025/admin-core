<?php
// app/Http/Controllers/Front/ReviewController.php
namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\EstablishmentReview;
use App\Models\Pages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function index()
    {
        $locationId = 1;
        $slug='feedbacks';
        $page = Pages::query()
            ->where('slug', $slug)
            ->firstOrFail();
        // список для страницы (с пагинацией, чтобы работал твой блок пагинации)
        $reviews = EstablishmentReview::query()
            ->active()->forLocation($locationId)->newest()
            ->paginate(10);

        // агрегаты для шапки (средняя, проценты по звёздам)
        $stats = EstablishmentReview::query()
            ->active()->forLocation($locationId)
            ->selectRaw('
                COUNT(*) as total,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) as r5,
                SUM(CASE WHEN rating=4 THEN 1 ELSE 0 END) as r4,
                SUM(CASE WHEN rating=3 THEN 1 ELSE 0 END) as r3,
                SUM(CASE WHEN rating=2 THEN 1 ELSE 0 END) as r2,
                SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) as r1
            ')
            ->first();

        return view('pages.reviews', compact('reviews', 'stats','page'));
    }

    public function store(Request $request)
    {
        // honeypot
        if ($request->filled('hp')) {
            return response()->json(['ok' => true]);
        }

        $v = Validator::make($request->all(), [
            'name'    => ['required','string','max:100'],
            'email'   => ['nullable','email','max:150'], // в модель не пишем, просто валидируем
            'content' => ['required','string','min:10','max:3000'],
            'rating'  => ['required','integer','min:1','max:5'],
            'location_id' => ['nullable','integer'],
        ], [], [
            'name' => __('Ім’я'),
            'content' => __('Відгук'),
            'rating' => __('Оцінка'),
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $locationId = (int)($request->input('location_id') ?: 1);

        EstablishmentReview::create([
            'author_name' => $request->string('name'),
            'text'        => $request->string('content'),
            'rating'      => (int) $request->input('rating', 5),
            'location_id' => $locationId,
            'is_active'   => false,   // на модерацию
            'posted_at'   => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
