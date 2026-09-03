<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\User;
use App\Notifications\DiaryModerated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ModerateDiaryAction
{
    public function approve(Diary $diary, User $admin, array $metaData = []): void
    {
        $oldStatus = $diary->status;
        $before = [
            'status' => $diary->getOriginal('status'), 
            'reject_reason' => $diary->getOriginal('reject_reason')
        ];

        $diary->update(array_merge($metaData, [
            'status' => 'published',
            'published_at' => !$diary->published_at ? now() : $diary->published_at,
            'reject_reason' => null,
        ]));

        $diary->refresh();

        $after = [
            'status' => 'published', 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'diary_id' => $diary->id,
                'author_id' => $diary->user_id,
                'title' => $diary->title // Название тоже полезно для быстрого понимания
            ]
        ];

        AdminLog::record('diary.approve', $diary, $admin, $before, $after, participants: [$diary->user_id]);
        Cache::forget('admin_sidebar_stats');

        if ($oldStatus !== 'published' && $diary->user) {
            $diary->user->notify(new DiaryModerated($diary, 'approved'));
        }
    }

    public function reject(Diary $diary, User $admin, string $reason, array $metaData = []): void
    {
        $oldStatus = $diary->status;
        $before = [
            'status' => $diary->getOriginal('status'), 
            'reject_reason' => $diary->getOriginal('reject_reason')
        ];

        $diary->update(array_merge($metaData, [
            'status' => 'rejected',
            'reject_reason' => $reason,
        ]));

        $diary->refresh();

        $after = [
            'status' => 'rejected', 
            'reject_reason' => $reason, 
            'moderated_by' => $admin->id, 
            'moderated_at' => now()->toDateTimeString(),
            'context' => [
                'diary_id' => $diary->id,
                'author_id' => $diary->user_id,
                'title' => $diary->title
            ]
        ];

        AdminLog::record('diary.reject', $diary, $admin, $before, $after, participants: [$diary->user_id]);
        Cache::forget('admin_sidebar_stats');

        if ($oldStatus !== 'rejected' && $diary->user) {
            $diary->user->notify(new DiaryModerated($diary, 'rejected', $reason));
        }
    }

    public function unpublish(Diary $diary, User $admin, array $metaData = []): void
    {
        $oldStatus = $diary->status;
        $before = ['status' => $diary->getOriginal('status')];

        $diary->update(array_merge($metaData, [
            'status' => 'pending',
        ]));

        $diary->refresh();

        $after = [
            'status' => 'pending', 
            'unpublished_by' => $admin->id, 
            'unpublished_at' => now()->toDateTimeString(),
            'context' => [
                'diary_id' => $diary->id,
                'author_id' => $diary->user_id,
                'title' => $diary->title
            ]
        ];

        AdminLog::record('diary.unpublish', $diary, $admin, $before, $after, participants: [$diary->user_id]);
        Cache::forget('admin_sidebar_stats');

        if ($oldStatus === 'published' && $diary->user) {
            $diary->user->notify(new DiaryModerated($diary, 'unpublished'));
        }
    }

    public function delete(Diary $diary, User $admin): void
    {
        $before = [
            'status' => $diary->getOriginal('status'), 
            'deleted_at' => $diary->getOriginal('deleted_at')
        ];
        
        $after = [
            'deleted_at' => now()->toDateTimeString(), 
            'deleted_by' => $admin->id,
            'context' => [
                'diary_id' => $diary->id,
                'author_id' => $diary->user_id,
                'title' => $diary->title
            ]
        ];

        AdminLog::record('diary.delete', $diary, $admin, $before, $after, participants: [$diary->user_id]);
        
        $diary->delete();
    }

    public function restore(Diary $diary, User $admin): void
    {
        $before = [
            'status' => $diary->getOriginal('status'), 
            'deleted_at' => $diary->getOriginal('deleted_at')
        ];
        
        $diary->restore();
        
        $diary->update([
            'status' => 'pending',
            'reject_reason' => null
        ]);
        
        $diary->refresh();
        
        $after = [
            'status' => 'pending', 
            'restored_by' => $admin->id, 
            'restored_at' => now()->toDateTimeString(),
            'context' => [
                'diary_id' => $diary->id,
                'author_id' => $diary->user_id,
                'title' => $diary->title
            ]
        ];
        
        AdminLog::record('diary.restore', $diary, $admin, $before, $after, participants: [$diary->user_id]);
        Cache::forget('admin_sidebar_stats');
    }

    public function forceDelete(Diary $diary, User $admin): void
    {
        $userId = $diary->user_id;
        $diaryId = $diary->id;
        $diaryTitle = $diary->title;
        
        $before = ['status' => $diary->getOriginal('status')];
        $after = [
            'status' => 'destroyed', 
            'deleted_by' => $admin->id, 
            'deleted_at' => now()->toDateTimeString(),
            'context' => [
                'diary_id' => $diaryId,
                'author_id' => $userId,
                'title' => $diaryTitle
            ]
        ];
        
        AdminLog::record('diary.force_delete', $diary, $admin, $before, $after, participants: [$userId]);
        
        $diary->forceDelete();
    }
}