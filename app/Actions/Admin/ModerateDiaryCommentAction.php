<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\DiaryComment;
use App\Models\User;
use App\Notifications\DiaryCommentModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // <--- ДОБАВИЛИ ИМПОРТ
use Illuminate\Support\Facades\Cache;

class ModerateDiaryCommentAction
{
    public function approve(DiaryComment $comment, User $admin): bool
    {
        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $before = [
            'status' => $comment->getOriginal('status'), 
            'reject_reason' => $comment->getOriginal('reject_reason')
        ];
        
        $comment->approve($admin->id);
        $comment->refresh();
        
        $after = [
            'status' => 'approved', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'diary_id' => $comment->diary_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50) // Сохраняем кусок текста
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->diary?->user_id]);
        
        AdminLog::record('diary_comment.approve', $comment, $admin, $before, $after, participants: $participants);
        $this->notifyAuthor($comment, 'approved');
        Cache::forget('admin_sidebar_stats');
        
        return true;
    }

    public function reject(DiaryComment $comment, User $admin, string $reason = 'other'): void
    {
        $before = [
            'status' => $comment->getOriginal('status'), 
            'reject_reason' => $comment->getOriginal('reject_reason')
        ];
        
        $comment->reject($admin->id, $reason);
        $comment->refresh();
        
        $after = [
            'status' => 'rejected', 
            'reject_reason' => $reason, 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'diary_id' => $comment->diary_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->diary?->user_id]);
        
        AdminLog::record('diary_comment.reject', $comment, $admin, $before, $after, participants: $participants);
        $this->notifyAuthor($comment, 'rejected');
         Cache::forget('admin_sidebar_stats');
    }

    public function markSpam(DiaryComment $comment, User $admin): void
    {
        $before = [
            'status' => $comment->getOriginal('status'), 
            'reject_reason' => $comment->getOriginal('reject_reason')
        ];
        
        $comment->update([
            'status' => 'spam', 
            'moderated_at' => now(),
            'reject_reason' => 'spam',
            'moderated_by' => $admin->id
        ]);
        
        $comment->refresh();
        
        $after = [
            'status' => 'spam', 
            'reject_reason' => 'spam', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'diary_id' => $comment->diary_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->diary?->user_id]);
        
        AdminLog::record('diary_comment.spam', $comment, $admin, $before, $after, participants: $participants);
        $this->notifyAuthor($comment, 'spam');
         Cache::forget('admin_sidebar_stats');
    }

    public function restore(DiaryComment $comment, User $admin): void
    {
        $before = [
            'status' => $comment->getOriginal('status'), 
            'moderated_by' => $comment->getOriginal('moderated_by')
        ];
        
        $comment->update([
            'status' => 'pending',
            'moderated_at' => null,
            'reject_reason' => null,
            'moderated_by' => null
        ]);
        
        $comment->refresh();
        
        $after = [
            'status' => 'pending', 
            'restored_by' => $admin->id, 
            'restored_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'diary_id' => $comment->diary_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->diary?->user_id]);
        
        AdminLog::record('diary_comment.restore', $comment, $admin, $before, $after, participants: $participants);
         Cache::forget('admin_sidebar_stats');
    }

    private function notifyAuthor(DiaryComment $comment, string $status): void
    {
        try {
            if ($comment->user) {
                 $comment->user->notify(new DiaryCommentModerated($comment, $status));
            }
        } catch (\Exception $e) {
            Log::error('Ошибка уведомления о модерации комментария дневника: ' . $e->getMessage());
        }
    }
}