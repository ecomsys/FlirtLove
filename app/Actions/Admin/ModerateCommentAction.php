<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\PhotoComment;
use App\Models\User;
use App\Notifications\CommentModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // <--- ДОБАВИЛИ ИМПОРТ
use Illuminate\Support\Facades\Cache;


class ModerateCommentAction
{
    public function approve(PhotoComment $comment, User $admin): bool
    {
        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $before = [
            'status' => $comment->getOriginal('status'), 
            'reject_reason' => $comment->getOriginal('reject_reason')
        ];
        
        $comment->update([
            'status' => 'approved', 
            'moderated_at' => now(),
            'reject_reason' => null,
            'moderated_by' => $admin->id
        ]);
        
        $comment->refresh();
        
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
        Cache::forget('admin_sidebar_stats');
        
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
        
        $comment->refresh();
        
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
        Cache::forget('admin_sidebar_stats');
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
        
        $comment->refresh();
        
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
        Cache::forget('admin_sidebar_stats');
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
        
        $comment->refresh();
        
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
        $this->notifyAuthor($comment, 'restored');
        Cache::forget('admin_sidebar_stats');
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
                    'reject_reason' => null,
                    'moderated_by' => $admin->id
                ]);
                $this->notifyAuthor($comment, 'approved');
                
                if (!$firstComment) $firstComment = $comment;
                $approvedIds[] = $comment->id;
                $approvedCount++;
            }
        });

        if ($approvedCount > 0 && $firstComment) {
            $after = [
                'count' => $approvedCount, 
                'ids' => $approvedIds, 
                'moderated_by' => $admin->id,
                'context' => [
                    'user_id' => $firstComment->user_id
                ]
            ];
            $participants = array_filter([$firstComment->user_id]);
            
            AdminLog::record('photo_comment.mass_approve', $firstComment, $admin, null, $after, participants: $participants);
        }
        Cache::forget('admin_sidebar_stats');

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
            $after = [
                'count' => $count, 
                'ids' => $rejectedIds, 
                'reason' => $reason, 
                'moderated_by' => $admin->id,
                'context' => [
                    'user_id' => $firstComment->user_id
                ]
            ];
            $participants = array_filter([$firstComment->user_id]);
            
            AdminLog::record('photo_comment.mass_reject', $firstComment, $admin, null, $after, participants: $participants);
        }
        Cache::forget('admin_sidebar_stats');

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