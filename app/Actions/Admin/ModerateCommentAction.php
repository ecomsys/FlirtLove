<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\PhotoComment;
use App\Models\User;
use App\Notifications\CommentModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModerateCommentAction
{
    public function approve(PhotoComment $comment, User $admin): bool
    {
        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->update([
            'status' => 'approved', 
            'moderated_at' => now(),
            'reject_reason' => null,
            'moderated_by' => $admin->id
        ]);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('comment.approve', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'approved');
        
        return true;
    }

    public function reject(PhotoComment $comment, User $admin, string $reason = 'other'): void
    {
        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->update([
            'status' => 'rejected', 
            'moderated_at' => now(),
            'reject_reason' => $reason,
            'moderated_by' => $admin->id
        ]);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('comment.reject', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'rejected');
    }

    public function markSpam(PhotoComment $comment, User $admin): void
    {
        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->update([
            'status' => 'spam', 
            'moderated_at' => now(),
            'reject_reason' => 'spam',
            'moderated_by' => $admin->id
        ]);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('comment.spam', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'spam');
    }

    public function restore(PhotoComment $comment, User $admin): void
    {
        $before = $comment->only(['status', 'moderated_at', 'reject_reason']);
        $comment->update([
            'status' => 'pending',
            'moderated_at' => null,
            'reject_reason' => null,
            'moderated_by' => null
        ]);
        $after = $comment->fresh()->only(['status', 'moderated_at', 'reject_reason']);
        
        AdminLog::record('comment.restore', $comment, $admin, $before, $after);
        $this->notifyAuthor($comment, 'restored');
    }

    public function bulkApprove($comments, User $admin): int
    {
        $approvedCount = 0;
        $firstComment = null;
        $approvedIds = [];

        DB::transaction(function () use ($comments, $admin, &$approvedCount, &$firstComment, &$approvedIds) {
                foreach ($comments as $comment) {
                    if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
                        continue;
                    }

                    $comment->update([
                        'status' => 'approved', 
                        'moderated_at' => now(),
                        'reject_reason' => null, // <--- ДОБАВИЛИ СБРОС ПРИЧИНЫ!
                        'moderated_by' => $admin->id
                    ]);
                $this->notifyAuthor($comment, 'approved');
                
                if (!$firstComment) $firstComment = $comment;
                $approvedIds[] = $comment->id;
                $approvedCount++;
            }
        });

        if ($approvedCount > 0 && $firstComment) {
            AdminLog::record('comment.mass_approve', $firstComment, $admin, null, ['count' => $approvedCount, 'ids' => $approvedIds]);
        }

        return $approvedCount;
    }

    public function bulkReject($comments, User $admin, string $reason = 'mass_reject'): int
    {
        $firstComment = null;
        $rejectedIds = [];
        $count = 0;

        DB::transaction(function () use ($comments, $admin, $reason, &$firstComment, &$count, &$rejectedIds) {
             $notifiedUsers = [];

            foreach ($comments as $comment) {
                $comment->update([
                    'status' => 'rejected', 
                    'moderated_at' => now(),
                    'reject_reason' => $reason,
                    'moderated_by' => $admin->id
                ]);
                 // Запоминаем, кому уже отправили
                if ($comment->user_id && !in_array($comment->user_id, $notifiedUsers)) {
                    $this->notifyAuthor($comment, 'rejected');
                    $notifiedUsers[] = $comment->user_id;
                }
                
                if (!$firstComment) $firstComment = $comment;
                $rejectedIds[] = $comment->id;
                $count++;
            }
        });

        if ($count > 0 && $firstComment) {
            AdminLog::record('comment.mass_reject', $firstComment, $admin, null, ['count' => $count, 'ids' => $rejectedIds]);
        }

        return $count;
    }

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