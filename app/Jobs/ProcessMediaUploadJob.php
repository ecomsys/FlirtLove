<?php

namespace App\Jobs;

use App\Enums\MediaCollection;
use App\Models\Media;
use App\Services\MediaProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessMediaUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $mediaId,
        public string $tempPath, // Относительный путь (media/temp/xyz.jpg)
        public MediaCollection $collection,
        public string $originalFileName
    ) {}

    public function handle(MediaProcessorService $processor): void
    {
        // Снимаем лимит памяти для нарезки больших картинок
        ini_set('memory_limit', '512M');
        
        $media = Media::find($this->mediaId);
        if (!$media) return;

        try {
            $result = $processor->process($this->tempPath, $this->collection);

            $media->update([
                'disk_path' => $result['main_path'],
                'url' => Storage::url($result['main_path']),
                'type' => 'image', // ФИКС: Жестко указываем 'image', так как видео не поддерживается
                'mime_type' => $result['mime_type'],
                'variants' => $result['variants'],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Media Upload Failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            // Если обработка не удалась (например, загрузили видео), удаляем запись из БД
            $media->delete();
        } finally {
            // В любом случае удаляем временный файл
            if (Storage::disk('public')->exists($this->tempPath)) {
                Storage::disk('public')->delete($this->tempPath);
            }
        }
    }
}