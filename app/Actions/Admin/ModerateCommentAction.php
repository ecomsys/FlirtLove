<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\PhotoComment;
use App\Models\User;
use App\Notifications\CommentModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ModerateCommentAction
{
    public function approve(PhotoComment $comment, User $admin): bool
    {
        // Проверяем родителя, подгружая его только если он не загружен (избегаем N+1)
        if ($comment->parent_id && !$comment->relationLoaded('parent')) {
            $comment->load('parent');
        }

        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $before = [
            'status' => $comment->getOriginal('status'), 
            'reject_reason' => $comment->getOriginal('reject_reason')
        ];
        
        // Атомарный апдейт без транзакции
        $comment->update([
            'status' => 'approved', 
            'moderated_at' => now(),
            'reject_reason' => null,
            'moderated_by' => $admin->id
        ]);
        
        $after = [
            'status' => 'approved', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'photo_id' => $comment->photo_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->photo?->user_id]);
        
        AdminLog::record('photo_comment.approve', $comment, $admin, $before, $after, participants: $participants);
        $this->notifyAuthor($comment, 'approved');
        $this->clearCaches();
        
        return true;
    }

    public function reject(PhotoComment $comment, User $admin, string $reason = 'other'): void
    {
        $before = [
            'status' => $comment->getOriginal('status'), 
            'reject_reason' => $comment->getOriginal('reject_reason')
        ];
        
        $comment->update([
            'status' => 'rejected', 
            'moderated_at' => now(),
            'reject_reason' => $reason,
            'moderated_by' => $admin->id
        ]);
        
        $after = [
            'status' => 'rejected', 
            'reject_reason' => $reason, 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'photo_id' => $comment->photo_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->photo?->user_id]);
        
        AdminLog::record('photo_comment.reject', $comment, $admin, $before, $after, participants: $participants);
        $this->notifyAuthor($comment, 'rejected');
        $this->clearCaches();
    }

    public function markSpam(PhotoComment $comment, User $admin): void
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
        
        $after = [
            'status' => 'spam', 
            'reject_reason' => 'spam', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'photo_id' => $comment->photo_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->photo?->user_id]);
        
        AdminLog::record('photo_comment.spam', $comment, $admin, $before, $after, participants: $participants);
        $this->notifyAuthor($comment, 'spam');
        $this->clearCaches();
    }

    public function restore(PhotoComment $comment, User $admin): void
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
        
        $after = [
            'status' => 'pending', 
            'restored_by' => $admin->id, 
            'restored_at' => now()->toDateTimeString(),
            'context' => [
                'comment_id' => $comment->id,
                'photo_id' => $comment->photo_id,
                'author_id' => $comment->user_id,
                'snippet' => Str::limit($comment->content, 50)
            ]
        ];
        
        $participants = array_filter([$comment->user_id, $comment->photo?->user_id]);
        
        AdminLog::record('photo_comment.restore', $comment, $admin, $before, $after, participants: $participants);
        $this->clearCaches();
    }

    public function bulkApprove($comments, User $admin): int
    {
        $approvedIds = [];
        $firstComment = null;
        $notifiedUsers = []; 

        foreach ($comments as $comment) {
            if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
                continue;
            }

            $approvedIds[] = $comment->id;
            
            if ($comment->user_id && !isset($notifiedUsers[$comment->user_id])) {
                $this->notifyAuthor($comment, 'approved');
                $notifiedUsers[$comment->user_id] = true; 
            }

            if (!$firstComment) $firstComment = $comment;
        }

        if (empty($approvedIds)) return 0;

        DB::transaction(function () use ($approvedIds, $admin) {
            PhotoComment::whereIn('id', $approvedIds)->update([
                'status' => 'approved', 
                'moderated_at' => now(),
                'reject_reason' => null,
                'moderated_by' => $admin->id
            ]);
        });

        $logIds = count($approvedIds) > 100 ? array_slice($approvedIds, 0, 100) : $approvedIds;
        
        $after = [
            'count' => count($approvedIds), 
            'sample_ids' => $logIds, 
            'moderated_by' => $admin->id,
            'context' => ['first_user_id' => $firstComment->user_id ?? null]
        ];
        
        AdminLog::record('photo_comment.mass_approve', $firstComment, $admin, null, $after, participants: array_keys($notifiedUsers));
        $this->clearCaches();

        return count($approvedIds);
    }

    public function bulkReject($comments, User $admin, string $reason = 'mass_reject'): int
    {
        $rejectedIds = [];
        $firstComment = null;
        $notifiedUsers = []; 

        foreach ($comments as $comment) {
            $rejectedIds[] = $comment->id;

            if ($comment->user_id && !isset($notifiedUsers[$comment->user_id])) {
                $this->notifyAuthor($comment, 'rejected');
                $notifiedUsers[$comment->user_id] = true;
            }

            if (!$firstComment) $firstComment = $comment;
        }

        if (empty($rejectedIds)) return 0;

        DB::transaction(function () use ($rejectedIds, $admin, $reason) {
            PhotoComment::whereIn('id', $rejectedIds)->update([
                'status' => 'rejected', 
                'moderated_at' => now(),
                'reject_reason' => $reason,
                'moderated_by' => $admin->id
            ]);
        });

        $logIds = count($rejectedIds) > 100 ? array_slice($rejectedIds, 0, 100) : $rejectedIds;
        
        $after = [
            'count' => count($rejectedIds), 
            'sample_ids' => $logIds, 
            'reason' => $reason, 
            'moderated_by' => $admin->id,
            'context' => ['first_user_id' => $firstComment->user_id ?? null]
        ];
        
        AdminLog::record('photo_comment.mass_reject', $firstComment, $admin, null, $after, participants: array_keys($notifiedUsers));
        $this->clearCaches();

        return count($rejectedIds);
    }

    private function notifyAuthor(PhotoComment $comment, string $status): void
    {
        try {
            if ($comment->user) {
                 // ВАЖНО: Передаем $status, а не жестко прописанное 'approved'
                 $comment->user->notify(new CommentModerated(
                    $comment->id, 
                    $comment->photo_id, 
                    $comment->content, 
                    $status
                 ));
            }
        } catch (\Exception $e) {
            Log::error('Ошибка уведомления о модерации комментария: ' . $e->getMessage());
        }
    }

    private function clearCaches(): void
    {
        Cache::forget('admin_sidebar_stats');
        Cache::forget('admin_comment_counts');
    }
}