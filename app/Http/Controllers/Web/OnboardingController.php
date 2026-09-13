<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    /**
     * Показываем страницу онбординга (загрузка фото)
     */
    public function index(): View
    {
        $existingPhotos = Auth::user()->photos()
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->get();

        return view('onboarding.index', compact('existingPhotos'));
    }

    /**
     * Сохраняем новые фото и обновляем флаги существующих (через Fetch API)
     */
   public function save(Request $request): JsonResponse
    {
        // Жёстко заставляем валидатор возвращать JSON
        $validator = Validator::make($request->all(), [
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|min:10|max:5120',
            'intimate_flags.*' => 'boolean',
            'existing_intimate_flags.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации файлов.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $existingPhotos = $user->photos()->orderByDesc('is_primary')->get();
        
        // 1. Обновляем существующие фото (флаги 18+)
        foreach ($existingPhotos as $existingPhoto) {
            $isIntimate = in_array($existingPhoto->id, $request->input('existing_intimate_flags', []));
            if ($existingPhoto->is_intimate !== $isIntimate) {
                $existingPhoto->update(['is_intimate' => $isIntimate]);
            }
        }

        // 2. Сохраняем новые фото
        if ($request->hasFile('photos')) {
            $hasPrimary = $existingPhotos->contains(fn ($p) => $p->is_primary);
            $isFirstPhoto = !$hasPrimary;
            $defaultAlbum = $user->defaultAlbum ?? Album::getDefaultForUser($user);

            foreach ($request->file('photos') as $index => $photo) {
                $path = $photo->store('photos/pending', 'public');

                Photo::create([
                    'user_id' => $user->id,
                    'album_id' => $defaultAlbum?->id,
                    'type' => Photo::TYPE_PROFILE, 
                    'path_original' => $path, 
                    'is_primary' => $isFirstPhoto && $index === 0,
                    'is_intimate' => in_array($index, $request->input('intimate_flags', [])),
                    'status' => Photo::STATUS_PENDING, 
                ]);
                
                $isFirstPhoto = false;
            }
        }
        
        // Отмечаем онбординг пройденным
        $user->update(['has_completed_onboarding' => true]);

        return response()->json(['success' => true, 'redirect' => route('verification.notice')]);
    }

    /**
     * Пропуск загрузки фото
     */
    public function skip(): JsonResponse
    {
        Auth::user()->update(['has_completed_onboarding' => true]);

        return response()->json(['success' => true, 'redirect' => route('verification.notice')]);
    }
}