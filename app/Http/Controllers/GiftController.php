<?php

namespace App\Http\Controllers;

use App\Models\Gift;
use App\Models\User;
use App\Models\UserGift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftController extends Controller
{
    public function store(Request $request, User $user)
    {
        // 1. Валидация
        $validated = $request->validate([
            'gift_id' => 'required|exists:gifts,id',
            'message' => 'nullable|string|max:150'
        ]);

        $sender = $request->user();
        $receiver = $user;

        // 2. Защита от дарения самому себе
        if ($sender->id === $receiver->id) {
            return response()->json(['success' => false, 'message' => 'Нельзя дарить подарки себе'], 400);
        }

        // 3. Загружаем баланс отправителя
        $sender->load('balance');
        
        if (!$sender->balance) {
            return response()->json([
                'success' => false, 
                'message' => 'У вас нет счета. Пополните баланс.', 
                'redirect' => '/billing' // Замени на route('billing') если есть
            ], 403);
        }

        // 4. Находим подарок в каталоге
        $gift = Gift::findOrFail($validated['gift_id']);

        // 5. Транзакция для безопасного списания и создания подарка
        try {
            DB::transaction(function () use ($sender, $receiver, $gift, $validated) {
                
                // АТОМАРНОЕ СПИСАНИЕ БАЛАНСА
                // База сама проверит, хватает ли кредитов. 
                // Если не хватает — update вернет 0, мы кинем исключение.
                $updated = $sender->balance()
                    ->where('credits', '>=', $gift->price)
                    ->update([
                        'credits' => DB::raw('credits - ' . $gift->price)
                    ]);

                if (!$updated) {
                    throw new \Exception('Недостаточно кредитов для совершения покупки.');
                }

                // СОЗДАЕМ ПОДАРОК СО СНАПШОТОМ
                // Берем 'сырой' путь картинки, чтобы accessor в UserGift отработал правильно
                UserGift::create([
                    'sender_id'           => $sender->id,
                    'receiver_id'         => $receiver->id,
                    'gift_id'             => $gift->id,
                    'snapshot_name'       => $gift->name,
                    'snapshot_image_url'  => $gift->getRawOriginal('image_url'), 
                    'snapshot_price'      => $gift->price,
                    'message'             => $validated['message'],
                    'is_private'          => false, // По умолчанию публичный. Если добавишь тумблер в модалке — бери оттуда
                    'is_read'             => false,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage() ?: 'Ошибка при отправке подарка',
                'redirect' => '/billing'
            ], 402); // 402 Payment Required
        }

        return response()->json([
            'success' => true, 
            'message' => 'Подарок успешно отправлен!'
        ]);
    }
}