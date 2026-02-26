<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Shop\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BinotelWebhookController extends Controller
{
    private const CACHE_PREFIX = 'binotel_bt:';
    private const INCOMING_QUEUE_KEY = 'binotel:incoming_queue';

    public function callSettings(Request $request): JsonResponse
    {
        $payload = $this->extractPayload($request);

        if (! $this->isAuthorized($request)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Request is not allowed.',
            ], 403);
        }

        $requestType = (string) ($payload['requestType'] ?? '');
        if ($requestType !== 'apiCallSettings') {
            return response()->json([
                'error' => 'invalid_request_type',
                'message' => 'Expected requestType=apiCallSettings',
            ], 422);
        }

        $phone = $this->normalizePhone((string) ($payload['externalNumber'] ?? ''));
        $client = $this->findClientByPhone($phone);

        $token = Str::random(48);
        Cache::put(self::CACHE_PREFIX . $token, [
            'client_id' => $client?->id,
            'client_name' => $client?->name,
            'phone' => $phone,
            'call_type' => (string) ($payload['callType'] ?? ''),
            'pbx_number' => (string) ($payload['pbxNumber'] ?? ''),
            'internal_number' => (string) ($payload['internalNumber'] ?? ''),
            'company_id' => (string) ($payload['companyID'] ?? ''),
            'created_at' => now()->toDateTimeString(),
        ], now()->addMinutes(20));

        $crmBaseUrl = rtrim((string) config('services.binotel.crm_base_url', config('app.url')), '/');
        $crmUrl = $crmBaseUrl . '/admin/callcenter/orders/create?bt=' . $token;

        $isIncoming = (string) ($payload['callType'] ?? '') === '0';
        $description = $isIncoming ? 'Вхідний дзвінок' : 'Вихідний дзвінок';
        if ($phone !== '') {
            $description .= ' · ' . $phone;
        }

        Log::info('Binotel API CALL SETTINGS accepted', [
            'client_id' => $client?->id,
            'client_name' => $client?->name,
            'phone' => $phone,
            'call_type' => (string) ($payload['callType'] ?? ''),
            'crm_url' => $crmUrl,
        ]);

        $this->pushIncomingCall([
            'id' => (string) Str::uuid(),
            'name' => $client?->name ?: 'Невідомий клієнт',
            'phone' => $phone,
            'description' => $description,
            'create_url' => $crmUrl,
            'created_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'customerData' => [
                'name' => $client?->name ?: 'Невідомий клієнт',
                'description' => $description,
                'linkToCrmUrl' => $crmUrl,
                'linkToCrmTitle' => 'Створити замовлення',
            ],
        ]);
    }

    public function callCompleted(Request $request): JsonResponse
    {
        Log::info('Binotel API CALL COMPLETED received', [
            'request_type' => (string) $request->input('requestType', ''),
            'call_id' => data_get($request->input('callDetails', []), 'generalCallID'),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function nextIncomingCall(Request $request): JsonResponse
    {
        $user = auth('admin')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $queue = Cache::get(self::INCOMING_QUEUE_KEY, []);
        if (! is_array($queue) || empty($queue)) {
            return response()->json(['call' => null]);
        }

        $call = array_shift($queue);
        Cache::put(self::INCOMING_QUEUE_KEY, $queue, now()->addMinutes(30));

        return response()->json(['call' => $call]);
    }

    private function isAuthorized(Request $request): bool
    {
        $mode = (string) config('services.binotel.ip_check_mode', 'strict');
        $ip = (string) $request->ip();
        $allowedIps = (array) config('services.binotel.allowed_ips', []);

        if ($mode === 'strict') {
            return in_array($ip, $allowedIps, true);
        }

        if (in_array($ip, $allowedIps, true)) {
            return true;
        }

        $secret = (string) config('services.binotel.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        $provided = (string) ($request->header('X-Binotel-Secret')
            ?: $request->input('secret')
            ?: $request->query('secret', ''));

        return hash_equals($secret, $provided);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function extractPayload(Request $request): array
    {
        $payload = $request->all();

        $raw = trim((string) $request->getContent());
        if ($raw === '') {
            return $payload;
        }

        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            $raw = substr($raw, 1, -1);
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            return array_merge($json, $payload);
        }

        return $payload;
    }

    private function findClientByPhone(string $phone): ?Client
    {
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        $query = Client::query()->select(['id', 'name', 'phone']);

        try {
            return $query
                ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') = ?", [$digits])
                ->orWhereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ['%' . $digits])
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            $tail = substr($digits, -7);

            return Client::query()
                ->select(['id', 'name', 'phone'])
                ->where('phone', 'like', '%' . $digits . '%')
                ->when($tail !== '', fn ($q) => $q->orWhere('phone', 'like', '%' . $tail . '%'))
                ->orderByDesc('id')
                ->first();
        }
    }

    private function pushIncomingCall(array $payload): void
    {
        $queue = Cache::get(self::INCOMING_QUEUE_KEY, []);
        if (! is_array($queue)) {
            $queue = [];
        }

        $queue[] = $payload;

        if (count($queue) > 50) {
            $queue = array_slice($queue, -50);
        }

        Cache::put(self::INCOMING_QUEUE_KEY, $queue, now()->addMinutes(30));
    }
}
