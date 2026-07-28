/*============================================
НА ФРОНТЕ
==============================================*/

<!-- public function updateSearchFilters(): void
    {
        $user = auth()->user();

        // Никаких проверок на Premium! Сохраняем всем.
        $validated = $this->validate([
            'filter_height_from' => 'nullable|integer',
            'filter_height_to' => 'nullable|integer',
            'filter_education' => 'nullable|string',
            'filter_zodiac_sign' => 'nullable|string',
            'filter_is_verified_only' => 'boolean',
            'filter_is_premium_only' => 'boolean',
        ]);

        $user->search_filters = [
            'height_from' => $validated['filter_height_from'] ?? null,
            'height_to' => $validated['filter_height_to'] ?? null,
            'education' => $validated['filter_education'] ?? null,
            'zodiac_sign' => $validated['filter_zodiac_sign'] ?? null,
            'is_verified_only' => $validated['filter_is_verified_only'] ?? false,
            'is_premium_only' => $validated['filter_is_premium_only'] ?? false,
        ];
        
        $user->save();
        $this->dispatch('show-toast', type: 'success', message: 'Настройки поиска сохранены');
    } -->