<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Search\UserSearchService;
use Illuminate\Http\Request;
use Exception;

class FeedController extends Controller
{
    public function search(Request $request, UserSearchService $searchService)
    {
        try {
            $filters = $request->all();
            
            // ВАЛИДАЦИЯ НА БЭКЕНДЕ: жестко очищаем интересы перед запросом в БД
            if (!empty($filters['interests']) && is_array($filters['interests'])) {
                $filters['interests'] = array_filter(array_map(function($item) {
                    $cleaned = preg_replace('/[^a-zA-Zа-яА-ЯёЁ0-9\s-]/u', '', $item);
                    return trim($cleaned);
                }, $filters['interests']));
            }

            $users = $searchService->search(auth()->user(), $filters)->paginate(9);

            $mappedUsers = $users->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'age' => $user->profile->age ?? '?',
                    'city' => $user->profile?->city?->name ?? 'Город не указан',
                    'is_online' => $user->is_online,
                    'has_premium' => $user->has_active_premium,
                    'photo' => $user->photos->isNotEmpty() ? $user->photos->first()->medium_url : null,
                    'photos_count' => $user->photos_count,
                    'zodiac' => $user->profile->zodiac_name ?? 'Знак не указан',
                    'distance' => $user->distance ? $user->distance . ' км' : null,
                    'status_text' => $user->is_online ? 'Онлайн' : 'Был недавно',
                ];
            })->values();

            return response()->json([
                'users' => $mappedUsers,
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}