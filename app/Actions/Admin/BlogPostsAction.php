<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BlogPostsAction
{
    public function duplicate(BlogPost $post, User $admin): BlogPost
    {
        $new = $post->replicate();
        $new->slug = $post->slug . '-copy-' . Str::random(6);
        $new->title = $post->title . ' (Копия)';
        $new->status = 'draft'; 
        $new->is_featured = false;
        $new->views_count = 0;
        $new->save();

        $after = [
            'status' => 'created', 
            'context' => [
                'source_post_id' => $post->id,
                'new_post_id' => $new->id,
                'title' => $new->title,
                'slug' => $new->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('blog.create', $new, $admin, null, $after);
        $this->clearCache();
        Log::info("Админ продублировал пост", ['source_post_id' => $post->id, 'new_post_id' => $new->id, 'admin_id' => $admin->id]);

        return $new;
    }

    public function createPost(array $data, User $admin): BlogPost
    {
        $data['user_id'] = $admin->id;

        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && !empty($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        $post = BlogPost::create($data);
        
        if (!empty($data['is_featured']) && $data['is_featured'] === true) {
            BlogPost::where('id', '!=', $post->id)->update(['is_featured' => false]);
        }
        
        $after = [
            'status' => 'created', 
            'context' => [
                'post_id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'admin_id' => $admin->id
            ]
        ];
        
        AdminLog::record('blog.create', $post, $admin, null, $after);
        $this->clearCache();
        Log::info('Админ создал пост блога', ['post_id' => $post->id, 'admin_id' => $admin->id]);

        return $post;
    }

    public function updatePost(BlogPost $post, array $data, User $admin): BlogPost
    {
        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && !empty($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        $before = [
            'title' => $post->getOriginal('title'), 
            'slug' => $post->getOriginal('slug'), 
            'status' => $post->getOriginal('status')
        ];
        
        $post->update($data);
        
        if (!empty($data['is_featured']) && $data['is_featured'] === true) {
            BlogPost::where('id', '!=', $post->id)->update(['is_featured' => false]);
        }
        
        $after = [
            'title' => $post->title, 
            'slug' => $post->slug, 
            'status' => $post->status,
            'context' => [
                'post_id' => $post->id,
                'title' => $post->title,
                'admin_id' => $admin->id
            ]
        ];
        
        AdminLog::record('blog.update', $post, $admin, $before, $after);
        $this->clearCache();
        Log::info('Админ обновил пост блога', ['post_id' => $post->id, 'admin_id' => $admin->id]);

        return $post;
    }
    
    public function toggle(BlogPost $post, User $admin): void
    {
        $oldStatus = $post->getOriginal('status');

        $newStatus = match($oldStatus) {
            'published' => 'draft',
            'archived'  => 'draft',
            default     => 'published',
        };

        $post->update(['status' => $newStatus]);

        AdminLog::record('blog.update', $post, $admin, 
            ['status' => $oldStatus], 
            [
                'status' => $newStatus,
                'context' => [
                    'post_id' => $post->id,
                    'title' => $post->title,
                    'admin_id' => $admin->id
                ]
            ]
        );
        $this->clearCache();
    }

    public function archive(BlogPost $post, User $admin): void
    {
        if ($post->status === 'archived') return;

        $oldStatus = $post->getOriginal('status');
        $post->update(['status' => 'archived']);

        AdminLog::record('blog.update', $post, $admin, 
            ['status' => $oldStatus], 
            [
                'status' => 'archived',
                'context' => [
                    'post_id' => $post->id,
                    'title' => $post->title,
                    'admin_id' => $admin->id
                ]
            ]
        );
        $this->clearCache();
    }

    public function restore(BlogPost $post, User $admin): void
    {
        if ($post->status !== 'archived') return;

        $post->update(['status' => 'draft']);
        
        AdminLog::record('blog.update', $post, $admin, 
            ['status' => 'archived'], 
            [
                'status' => 'draft',
                'context' => [
                    'post_id' => $post->id,
                    'title' => $post->title,
                    'admin_id' => $admin->id
                ]
            ]
        );
        $this->clearCache();
    }

    public function delete(BlogPost $post, User $admin): void
    {
        $postId = $post->id;
        $postTitle = $post->title;
        
        $after = [
            'status' => 'destroyed',
            'context' => [
                'post_id' => $postId,
                'title' => $postTitle,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('blog.delete', $post, $admin, null, $after);
        $post->forceDelete();
        $this->clearCache();
    }

    /**
     * МАССОВЫЕ ДЕЙСТВИЯ (HIGH-LOAD ОПТИМИЗАЦИЯ)
     * Заменили цикл foreach на 1 Bulk-запрос к базе данных!
     */
    public function applyBulk(array $postIds, string $action, bool $isArchiveTab, User $admin): string
    {
        if (empty($postIds)) return 'Нет выбранных постов';

        // 1. Обработка удаления
        if ($action === 'delete') {
            if (!$isArchiveTab) return 'Удалять можно только из архива!';
            
            // 1 Bulk DELETE запрос
            $deletedCount = BlogPost::whereIn('id', $postIds)->forceDelete();
            
            if ($deletedCount > 0) {
                AdminLog::record('blog.bulk_delete', null, $admin, null, [
                    'count' => $deletedCount,
                    'sample_ids' => array_slice($postIds, 0, 100)
                ]);
                $this->clearCache();
            }
            return 'Посты удалены';
        }

        // 2. Обработка смены статуса
        $newStatus = match($action) {
            'publish'  => 'published',
            'draft'    => 'draft',
            'archive'  => 'archived',
            default    => null,
        };

        if ($newStatus) {
            // 1 Bulk UPDATE запрос (меняем статус только тем, у кого он отличается)
            $affected = BlogPost::whereIn('id', $postIds)
                ->where('status', '!=', $newStatus)
                ->update(['status' => $newStatus]);

            if ($affected > 0) {
                AdminLog::record('blog.bulk_update', null, $admin, null, [
                    'action' => $action,
                    'count' => $affected,
                    'sample_ids' => array_slice($postIds, 0, 100)
                ]);
                $this->clearCache();
            }
        }

        return match($action) {
            'delete'   => 'Посты удалены',
            'publish'  => 'Выбранные посты опубликованы',
            'draft'    => 'Выбранные посты сняты с публикации',
            'archive'  => 'Посты перемещены в архив',
            default    => 'Действие применено',
        };
    }

    private function clearCache(): void
    {
        Cache::forget('admin_blog_counts');
    }
}