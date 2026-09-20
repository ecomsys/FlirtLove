<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:card,yoomoney',
            'auto_renew' => 'boolean',
            'return_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'type' => Transaction::TYPE_SUBSCRIPTION,
                'status' => Transaction::STATUS_PENDING,
                'provider' => $validated['payment_method'],
                'meta' => [
                    'plan_id' => $plan->id,
                    'tier' => $plan->tier,
                    'auto_renew' => $validated['auto_renew'] ?? false,
                    'return_url' => $validated['return_url'] ?? route('home'),
                ]
            ]);

            // ЗАПУСКАЕМ АСИНХРОННУЮ СИМУЛЯЦИЮ БАНКА (Задержка 5 секунд)
            ProcessPaymentGateway::dispatch($transaction->id)->delay(now()->addSeconds(5))->onQueue('payments');;

            // Возвращаем ссылку на "Зал ожидания"
            $confirmationUrl = route('subscription.success', ['transaction_id' => $transaction->id]);

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

        // Вычисляем URL для кнопки "Продолжить" (чтобы не было цикла при возврате на /premium)
        $returnUrl = $transaction->meta['return_url'] ?? route('home');
        if (str_contains($returnUrl, '/premium') || str_contains($returnUrl, '/vip')) {
            $returnUrl = route('home');
        }

        // Отдаем страницу, которая будет опрашивать статус (polling)
        return view('pages.subscriptions.success', [
            'transactionId' => $transaction->id,
            'tier' => $transaction->meta['tier'] ?? 'subscription',
            'returnUrl' => $returnUrl
        ]);
    }

    // НОВЫЙ МЕТОД: API для опроса статуса (Polling)
    public function status(Request $request, Transaction $transaction)
    {
        // Проверка безопасности
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $planName = SubscriptionPlan::find($transaction->meta['plan_id'] ?? null)?->name ?? 'тариф';
        $endsAt = null;
        if ($transaction->status === Transaction::STATUS_SUCCESS) {
            $sub = $transaction->user->subscriptions()->where('transaction_id', $transaction->id)->first();
            if ($sub) $endsAt = $sub->ends_at->format('d.m.Y H:i');
        }

        return response()->json([
            'status' => $transaction->status,
            'planName' => $planName,
            'endsAt' => $endsAt,
            'failReason' => $transaction->meta['fail_reason'] ?? null
        ]);
    }
}