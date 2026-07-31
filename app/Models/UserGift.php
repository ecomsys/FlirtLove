<?php

// UserGift — это лог транзакций между юзерами. Как мы помним из миграции, здесь заложен паттерн "Снапшот" 
// (сохранение имени, картинки и цены на момент отправки), чтобы история не ломалась при изменении каталога админом.

// Также мы добавили SoftDeletes, чтобы юзер мог убрать подарок со своей страницы (если ему прислали что-то пошлое), 
// но в админке он остался для фин. отчетности.

// Я использовал тот же гениальный паттерн с filter_var для картинки снапшота, чтобы мы могли безопасно отдавать 
// её на фронт.

// Разбор архитектуры:

// Приоритет Снапшота: В аксессоре getImageUrlAttribute мы отдаем snapshot_image_url. 
// Если админ через год поменяет картинку у "Мишки" в каталоге, то у всех юзеров, которым его дарили раньше, 
// останется старая картинка. Это правильное поведение монетизации.
// SoftDeletes: Если юзеру подарили оскорбительный подарок, он может нажать "Удалить". 
// Подарок исчезнет с его страницы, но связь с sender_id и snapshot_price останется в БД (в корзине), 
// чтобы саппорт мог разобраться, если юзер будет жаловаться на мошенничество.
// markAsRead(): Используем микро-оптимизацию с проверкой if (!$this->is_read), как мы делали в ChatParticipant, 
// чтобы не гонять лишние UPDATE запросы в БД, когда юзер просто открывает страницу подарков.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class UserGift extends Model
{
    use SoftDeletes; // Чтобы юзер мог "удалить" подарок со страницы, не ломая фин.логи

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'gift_id',
        'snapshot_name',
        'snapshot_image_url',
        'snapshot_price',
        'message',
        'is_private',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'snapshot_price' => 'integer',
        'is_private' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Только публичные подарки (для вывода в анкете юзера).
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Только приватные подарки (видят только отправитель и получатель).
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Только непрочитанные подарки (для счетчика "Новые подарки").
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // ============================================
    // АКСЕССОРЫ
    // ============================================

    /**
     * URL картинки подарка.
     * Всегда берем из СНЭПШОТА, чтобы история не ломалась при изменении каталога.
     * Паттерн с filter_var работает так же, как в Photo и Gift.
     */
    public function getImageUrlAttribute(): string
    {
        if (empty($this->snapshot_image_url)) {
            // Фолбэк: если снапшота вдруг нет (что не должно случиться), берем из каталога
            return $this->gift?->image_url ?? '';
        }

        if (filter_var($this->snapshot_image_url, FILTER_VALIDATE_URL)) {
            return $this->snapshot_image_url;
        }

        return Storage::url($this->snapshot_image_url);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Пометить подарок как прочитанный.
     * Оптимизация: обновляем БД только если он еще не прочитан.
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }
}