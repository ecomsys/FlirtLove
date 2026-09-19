<?php

namespace App\Http\Controllers;

use App\Models\UserBalance;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
         public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|in:80,250,500,900',
            'payment_method' => 'required|in:card,yoomoney',
            'auto_top_up' => 'boolean',
            'return_url' => 'nullable|string' // <--- УБРАЛИ url, ОСТАВИЛИ string
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $user = $request->user();

        $plans = [
            80 => ['credits' => 80, 'bonus' => 0],
            250 => ['credits' => 300, 'bonus' => 50],
            500 => ['credits' => 650, 'bonus' => 150],
            900 => ['credits' => 1250, 'bonus' => 350],
        ];

        $plan = $plans[$validated['amount']];
        $creditsToCharge = $plan['credits'] + $plan['bonus'];

        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amount' => $validated['amount'],
                'currency' => 'RUB',
                'type' => Transaction::TYPE_CREDITS,
                'status' => Transaction::STATUS_PENDING,
                'provider' => $validated['payment_method'],
                'credits_amount' => $creditsToCharge,
                'meta' => [
                    'auto_top_up' => $validated['auto_top_up'] ?? false,
                    'bonus_credits' => $plan['bonus'],
                    'return_url' => $validated['return_url'] ?? route('home'),
                ]
            ]);

            $confirmationUrl = route('billing.success', ['transaction_id' => $transaction->id]);

            return response()->json([
                'success' => true,
                'redirect_url' => $confirmationUrl
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка БД: ' . $e->getMessage()
            ], 500);
        }
    }

      public function success(Request $request)
    {
        $transactionId = $request->query('transaction_id');
        $transaction = Transaction::findOrFail($transactionId);

        if ($transaction->status === Transaction::STATUS_PENDING) {
            $transaction->markAsSuccess();

            UserBalance::where('user_id', $transaction->user_id)
                ->update(['credits' => DB::raw('credits + ' . $transaction->credits_amount)]);
        }

        // Достаем относительный URL, с которого юзер пришел (по умолчанию главная)
        $returnUrl = $transaction->meta['return_url'] ?? '/';
        
        // Разбираем его на путь и параметры
        $parsedUrl = parse_url($returnUrl);
        $path = $parsedUrl['path'] ?? '/';
        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }
        
        // Добавляем флаги успеха
        $query['payment_success'] = 1;
        $query['credits_added'] = $transaction->credits_amount;
        
        // Собираем финальный относительный URL (Laravel сам добавит http://localhost:8000)
        $finalRedirectUrl = $path . '?' . http_build_query($query);

        return redirect()->to($finalRedirectUrl);
    }
}