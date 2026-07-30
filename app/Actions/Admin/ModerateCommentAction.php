<?php

namespace App\Actions\Admin;

use App\Models\PhotoComment;
use App\Notifications\CommentModerated;
use Illuminate\Support\Facades\Log;

class ModerateCommentAction
{
    /**
     * Одобрить комментарий.
     * Возвращает false, если нельзя одобрить (ответ на неодобренный родитель).
     */
    public function approve(PhotoComment $comment): bool
    {
        // Бизнес-логика: нельзя одобрить ответ, если родитель еще не одобрен
        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $comment->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);

        $this->notifyAuthor($comment, 'approved');
        return true;
    }

    /**
     * Отклонить комментарий.
     */
    public function reject(PhotoComment $comment): void
    {
        $comment->update([
            'status' => 'rejected',
            'rejected_at' => now()
        ]);

        $this->notifyAuthor($comment, 'rejected');
    }

    /**
     * Пометить как спам.
     */
    public function markSpam(PhotoComment $comment): void
    {
        $comment->update(['status' => 'spam']);
        $this->notifyAuthor($comment, 'spam');
    }

    /**
     * Удалить комментарий (с уведомлением до удаления).
     */
    public function delete(PhotoComment $comment): void
    {
        $this->notifyAuthor($comment, 'deleted');
        $comment->delete();
    }

    /**
     * Восстановить комментарий (вернуть на модерацию).
     */
    public function restore(PhotoComment $comment): void
    {
        $comment->update(['status' => 'pending']);
        $this->notifyAuthor($comment, 'restored');
    }

    /**
     * Массовое одобрение (с проверкой родителей).
     */
    public function bulkApprove($comments): int
    {
        $approvedCount = 0;
        foreach ($comments as $comment) {
            if ($this->approve($comment)) {
                $approvedCount++;
            }
        }
        return $approvedCount;
    }

    /**
     * Массовое отклонение.
     */
    public function bulkReject($comments): int
    {
        foreach ($comments as $comment) {
            $this->reject($comment);
        }
        return $comments->count();
    }

    /**
     * Безопасная отправка уведомлений.
     */
    private function notifyAuthor(PhotoComment $comment, string $status): void
    {
        try {
            if ($comment->user) {
                $comment->user->notify(new CommentModerated($comment, $status));
            }
        } catch (\Exception $e) {
            Log::error('Ошибка уведомления о модерации комментария: ' . $e->getMessage());
        }
    }
}