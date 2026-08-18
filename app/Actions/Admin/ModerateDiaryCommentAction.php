<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\DiaryComment;
use App\Models\User;
use App\Notifications\DiaryCommentModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModerateDiaryCommentAction
{
    public function approve(DiaryComment $comment, User $admin): bool
    {
        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->approve($admin->id);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('diary_comment.approve', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'approved');
        
        return true;
    }

    public function reject(DiaryComment $comment, User $admin, string $reason = 'other'): void
    {
        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->reject($admin->id, $reason);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('diary_comment.reject', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'rejected');
    }

    public function markSpam(DiaryComment $comment, User $admin): void
    {
        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->update([
            'status' => 'spam', 
            'moderated_at' => now(),
            'reject_reason' => 'spam',
            'moderated_by' => $admin->id
        ]);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('diary_comment.spam', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'spam');
    }

    public function restore(DiaryComment $comment, User $admin): void
    {
        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->update([
            'status' => 'pending',
            'moderated_at' => null,
            'reject_reason' => null,
            'moderated_by' => null
        ]);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('diary_comment.restore', $comment, $admin, $before, $after);
    }

    private function notifyAuthor(DiaryComment $comment, string $status): void
    {
        try {
            if ($comment->user) {
                 $comment->user->notify(new DiaryCommentModerated($comment, 'approved'));
            }
        } catch (\Exception $e) {
            Log::error('Ошибка уведомления о модерации комментария дневника: ' . $e->getMessage());
        }
    }
}