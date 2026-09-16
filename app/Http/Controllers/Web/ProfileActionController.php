<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserBlock;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileActionController extends Controller
{
    // Написать сообщение (Заглушка, позже подключим чаты)
    public function chat(User $user)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Auth required'], 401);
        }
        
        // Здесь будет логика создания/возврата диалога
        // Пока просто возвращаем успех
        return response()->json(['success' => true, 'message' => 'Чат скоро будет доступен!']);
    }

    // Добавить/Убрать из избранного
    public function toggleFavorite(User $user)
    {
        if (!Auth::check()) return response()->json(['error' => 'Auth required'], 401);
        if (Auth::id() === $user->id) abort(403);

        $isFavorited = UserFavorite::isFavorite(Auth::id(), $user->id);

        if ($isFavorited) {
            UserFavorite::where('user_id', Auth::id())->where('favorite_user_id', $user->id)->delete();
            $status = false;
        } else {
            UserFavorite::create(['user_id' => Auth::id(), 'favorite_user_id' => $user->id]);
            $status = true;
        }

        return response()->json(['success' => true, 'isFavorited' => $status]);
    }

    // Заблокировать/Разблокировать
    public function toggleBlock(User $user)
    {
        if (!Auth::check()) return response()->json(['error' => 'Auth required'], 401);
        if (Auth::id() === $user->id) abort(403);

        $isBlocked = UserBlock::isBlocked(Auth::id(), $user->id);

        if ($isBlocked) {
            UserBlock::where('blocker_id', Auth::id())->where('blocked_id', $user->id)->delete();
            $status = false;
        } else {
            UserBlock::create(['blocker_id' => Auth::id(), 'blocked_id' => $user->id, 'reason' => 'User block']);
            // При блокировке убираем из избранного, если был
            UserFavorite::where('user_id', Auth::id())->where('favorite_user_id', $user->id)->delete();
            $status = true;
        }

        return response()->json(['success' => true, 'isBlocked' => $status]);
    }

    // Отправить жалобу        
    public function report(Request $request, User $user)
    {
        if (!Auth::check()) return response()->json(['error' => 'Auth required'], 401);
        if (Auth::id() === $user->id) abort(403);

        $validated = $request->validate([
            'reason' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ReportReason::class)],
            'description' => 'nullable|string|max:1000',
        ]);

        // Проверяем, не отправлял ли уже жалобу (защита от спама)
        $existingReport = Report::where('reporter_id', Auth::id())
            ->where('reported_id', $user->id)
            ->where('status', Report::STATUS_PENDING)
            ->exists();

        if ($existingReport) {
            return response()->json(['success' => false, 'message' => 'Вы уже отправили жалобу на этого пользователя.']);
        }

        Report::create([
            'reporter_id' => Auth::id(),             // Кто жалуется
            'reported_id' => $user->id,               // На кого жалуются
            'reportable_type' => User::class,         // Указываем, что жалоба на ЮЗЕРА (а не на фото)
            'reportable_id' => $user->id,             // ID этого юзера
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => Report::STATUS_PENDING,
        ]);

        return response()->json(['success' => true, 'message' => 'Жалоба отправлена модераторам.']);
    }
}