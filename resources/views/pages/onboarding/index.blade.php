<x-layouts.onboarding>

<div x-data="photoUploader()" class="max-w-5xl mx-auto px-4 py-12 md:py-20">
    
    <div class="grid md:grid-cols-2 gap-12 items-center">
       <!-- ЛЕВАЯ КОЛОНКА (Информация) -->
        <div class="max-w-[21rem] mx-auto">
            <label for="main-photo-input" class="relative w-54 h-54 mb-10 mx-auto md:mx-[initial] block cursor-pointer group">
                
                <div class="absolute -inset-2 rounded-full border-4 border-dashed border-primary/40 group-hover:border-primary group-hover:animate-spin group-hover:[animation-duration:100s] transition-colors"></div>

                <div class="absolute inset-0 rounded-full bg-muted flex items-center justify-center border-4 border-border group-hover:bg-accent transition-colors">
                    <svg class="w-20 h-20 text-muted-foreground group-hover:text-primary transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                </div>
            </label>

            <div class="flex gap-9 mb-8 justify-center md:justify-start">
                <div class="relative inline-flex">
                    <x-avatar src="https://i.pravatar.cc/150?img=1&blur=5" name="Math" size="lg" />
                    <span class="border-background bg-red-500 text-white absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2">
                        <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </span>
                </div>
                <div class="relative inline-flex">
                    <x-avatar src="https://i.pravatar.cc/150?img=2&dark=1" name="Iren" size="lg" />
                    <span class="border-background bg-red-500 text-white absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2">
                        <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </span>
                </div>
                <div class="relative inline-flex">
                    <x-avatar src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=face&auto=format" name="Joe" size="lg" />
                    <span class="border-background bg-green-500 text-white absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2">
                        <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </span>
                </div>
            </div>

            <ul class="space-y-1 text-sm text-muted-foreground inline-block text-left mx-auto">
                <li class="flex items-start gap-3"><span>·</span><span>{{ __('common.rule_no_other_people') }}</span></li>
                <li class="flex items-start gap-3"><span>·</span><span>{{ __('common.rule_no_indecent') }}</span></li>
                <li class="flex items-start gap-3"><span>·</span><span>{{ __('common.rule_clear_face') }}</span></li>
            </ul>
        </div>

        <!-- ПРАВАЯ КОЛОНКА (Загрузка) -->
        <div class="bg-card border border-border rounded-xl p-8 shadow-sm">
            <h1 class="text-2xl font-semibold text-foreground mb-2">{{ __('common.upload_photos') }}</h1>
            <p class="text-muted-foreground mb-8">{{ __('common.upload_photos_desc') }}</p>               

            <label for="main-photo-input" class="cursor-pointer w-full inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors h-12 px-8 bg-primary text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/20">
                {{ __('common.select_photos') }}
            </label>
            
            <!-- input теперь слушает Alpine -->
            <input id="main-photo-input" type="file" @change="handleFiles($event)" class="hidden" accept="image/jpeg, image/png, image/webp" multiple>

            <!-- Кнопка VK/OK -->
            <div class="mt-8 pt-8 border-t border-border">
                <p class="text-sm text-center text-muted-foreground mb-4">{{ __('common.or_upload_from_social') }}</p>
                <div class="flex justify-center gap-4">
                   <!-- Кнопки соцсетей -->
                </div>
            </div>

            <button @click="skipUpload()" class="underline block w-full text-center mt-6 text-sm text-primary hover:no-underline transition-colors">
                {{ __('common.skip_photos') }}
            </button>
        </div>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО ПРЕДПРОСМОТРА (Alpine) -->
    <div x-cloak x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" style="display: none;">
        <div x-show="showModal" x-transition class="bg-card w-full max-w-2xl rounded-xl shadow-2xl border border-border flex flex-col max-h-[90vh]">
            
            <div class="flex items-center p-6 border-b border-border">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-foreground">{{ __('common.photo_preview') }}</h3>
                    <div class="text-xs text-muted-foreground mt-1 flex gap-3">
                        <span x-text="`Фотографий: ${totalFiles}`"></span>
                        <span x-text="`Вес: ${totalSize}`"></span>
                    </div>
                </div>
                <div class="shrink-0">
                    <x-ui.button @click="savePhotos()" x-bind:disabled="isSaving" variant="default" size="md">
                        <span x-show="!isSaving">{{ __('common.save') }}</span>
                        <span x-show="isSaving" class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('common.loading') }}
                        </span>
                    </x-ui.button>
                </div>
            </div>

            <div class="p-6 overflow-y-auto flex-1 space-y-6">
                
                @if (!$existingPhotos->isEmpty())
                <div>
                    <h4 class="text-sm text-muted-foreground uppercase font-semibold mb-3">{{ __('common.your_current_photos') }}</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach ($existingPhotos as $photo)
                            <div class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                <img src="{{ $photo->thumb_url }}" class="w-full h-40 object-cover" alt="Photo">
                                
                                @if ($photo->is_primary)
                                    <span class="absolute top-2 left-2 bg-primary text-primary-foreground text-[10px] px-2 py-1 rounded">{{ __('common.avatar') }}</span>
                                @endif

                                <button @click="deleteExisting({{ $photo->id }})" class="absolute top-2 right-2 bg-destructive/90 hover:bg-destructive text-destructive-foreground rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>

                                <div class="absolute bottom-2 left-2 bg-background/90 backdrop-blur-sm px-2 py-1.5 rounded-md border border-border/50 inline-flex items-center gap-2">
                                    <x-ui.checkbox x-bind:value="{{ $photo->id }}" x-model="existingIntimateFlags" id="existing-intimate-{{ $photo->id }}"/>                                          
                                    <label for="existing-intimate-{{ $photo->id }}" class="text-xs font-medium cursor-pointer select-none">18+</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

                <div x-show="newPhotos.length > 0">
                    <h4 x-show="{{ $existingPhotos->isNotEmpty() ? 'true' : 'false' }}" class="text-sm text-muted-foreground uppercase font-semibold mb-3">{{ __('common.new_photos') }}</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <template x-for="(photo, index) in newPhotos" :key="index">
                            <div class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                <img :src="photo.preview" class="w-full h-40 object-cover" alt="Preview">
                                
                                <button @click="removePhoto(index)" class="absolute top-2 right-2 bg-destructive/90 hover:bg-destructive text-destructive-foreground rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>

                                <div class="absolute bottom-2 left-2 bg-background/90 backdrop-blur-sm px-2 py-1.5 rounded-md border border-border/50 inline-flex items-center gap-2">
                                    <x-ui.checkbox  x-bind:value="index" x-model="newIntimateFlags" id="intimate-new-${index}"/>                                                                              
                                    <label :for="`intimate-new-${index}`" class="text-xs font-medium cursor-pointer select-none">18+</label>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="newPhotos.length === 0 && {{ $existingPhotos->isEmpty() ? 'true' : 'false' }}" class="text-center py-12 text-muted-foreground">
                    {{ __('common.no_photos_selected') }}
                </div>
            </div>

            <div class="p-6 border-t border-border bg-muted/30">
                <label for="add-more-photos" class="cursor-pointer w-full inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium h-10 px-4 border border-border bg-background hover:bg-accent text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    {{ __('common.add_more_photos') }}
                </label>
                <input id="add-more-photos" type="file" @change="handleFiles($event)" class="hidden" accept="image/jpeg, image/png, image/webp" multiple>
            </div>
        </div>
    </div>

    <!-- Скрипт Alpine -->
    <script>
        function photoUploader() {
            return {
                showModal: false,
                isSaving: false,
                newPhotos: [],
                newIntimateFlags: [],
                existingIntimateFlags: [], // Заполняется автоматически x-model из верстки

                get totalFiles() {
                     return this.newPhotos.length + {{ (int) $existingPhotos->count() }};
                },

                handleFiles(event) {
                    const files = Array.from(event.target.files);
                    if (files.length === 0) return;

                    let addedCount = 0;
                    let duplicateCount = 0;

                    files.forEach(file => {
                        // Проверка на дубликат (по имени и размеру)
                        const isDuplicate = this.newPhotos.some(p => p.file.name === file.name && p.file.size === file.size);
                        
                        if (isDuplicate) {
                            duplicateCount++;
                        } else {
                            this.newPhotos.push({
                                file: file,
                                preview: URL.createObjectURL(file),
                                name: file.name,
                                size: file.size
                            });
                            addedCount++;
                        }
                    });

                    // Вызываем тосты (если они есть в проекте)
                    if (addedCount > 0) {
                        this.showToast('success', `Добавлено новых фото: ${addedCount}`);
                        this.showModal = true; // Открываем модалку, если что-то добавили
                    }
                    if (duplicateCount > 0) {
                        this.showToast('error', `Пропущено дубликатов: ${duplicateCount}`);
                    }

                    event.target.value = ''; // Сбрасываем инпут
                },

                // Считаем общий вес новых файлов
                get totalSize() {
                    const bytes = this.newPhotos.reduce((sum, p) => sum + p.file.size, 0);
                    if (bytes === 0) return '0 MB';
                    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
                },

                // Вспомогалка для тостов (заглушка, если нет библиотеки)
                showToast(type, message) {
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: type,
                            message: message
                        }
                    }));
                },

                removePhoto(index) {
                    URL.revokeObjectURL(this.newPhotos[index].preview);
                    this.newPhotos.splice(index, 1);
                    // Удаляем из массива флагов 18+, если был выбран
                    this.newIntimateFlags = this.newIntimateFlags.filter(i => i !== index).map(i => i > index ? i - 1 : i);
                    
                    if (this.newPhotos.length === 0 && {{ $existingPhotos->isEmpty() ? 'true' : 'false' }}) {
                            this.showModal = false;
                        }
                },

                async deleteExisting(id) {
                    if (!confirm('Удалить это фото?')) return;
                    
                    // Простой fetch запрос на удаление
                    // Можно сделать отдельный роут или удалить через тот же API
                    // Для примера:
                    fetch(`/api/photos/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }})
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload(); // Самый простой способ обновить $existingPhotos
                            } else {
                                alert('Ошибка удаления');
                            }
                        });
                },

               async savePhotos() {
                    if (this.newPhotos.length === 0 && {{ $existingPhotos->isEmpty() ? 'true' : 'false' }}) {
                        this.showToast('error', 'Добавьте хотя бы одно фото!');
                        return;
                    }

                    this.isSaving = true;

                    const formData = new FormData();
                    this.newPhotos.forEach((photo, index) => {
                        formData.append(`photos[${index}]`, photo.file);
                        if (this.newIntimateFlags.includes(index)) {
                            formData.append(`intimate_flags[${index}]`, 1);
                        }
                    });

                    this.existingIntimateFlags.forEach(id => {
                        formData.append(`existing_intimate_flags[]`, id);
                    });

                    try {
                        const response = await fetch('{{ route('onboarding.save') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        // Проверяем, вернул ли сервер JSON
                        const contentType = response.headers.get("content-type");
                        if (!contentType || !contentType.includes("application/json")) {
                            const htmlText = await response.text();
                            console.error("Сервер вернул HTML. Код статуса:", response.status, htmlText);
                            
                            if (response.status === 419) {
                                this.showToast('error', 'Сессия истекла (419). Возможно, файлы слишком большие. Обновите страницу.');
                            } else if (response.status === 413) {
                                this.showToast('error', 'Файлы слишком большие (413). Превышен лимит сервера.');
                            } else {
                                this.showToast('error', 'Ошибка сервера (статус ' + response.status + '). Открой консоль (F12).');
                            }
                            this.isSaving = false;
                            return;
                        }

                        const data = await response.json();

                        if (!response.ok) {
                            // Обработка ошибок валидации Laravel (422)
                            if (response.status === 422 && data.errors) {
                                const firstError = Object.values(data.errors)[0][0];
                                this.showToast('error', firstError);
                            } else {
                                this.showToast('error', data.message || 'Произошла ошибка при загрузке.');
                            }
                            this.isSaving = false;
                        } else {
                            this.showToast('success', 'Фото успешно сохранены!');
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 800); // Небольшая задержка, чтобы юзер увидел тост
                        }
                    } catch (error) {
                        console.error('Критическая ошибка fetch:', error);
                        this.showToast('error', 'Критическая ошибка сети.');
                        this.isSaving = false;
                    }
                },

               async skipUpload() {
                    fetch('{{ route("onboarding.skip") }}', { // Обновили роут!
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    }).then(res => res.json()).then(data => {
                        if (data.success) window.location.href = data.redirect;
                    });
                }
            }
        }
    </script>
</div>
</x-layouts.onboarding>