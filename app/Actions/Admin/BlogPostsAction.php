<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BlogPostsAction
{
    /**
     * Дублировать пост.
     */
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
        Log::info("Админ продублировал пост", ['source_post_id' => $post->id, 'new_post_id' => $new->id, 'admin_id' => $admin->id]);

        return $new;
    }

    /**
     * Создать пост
     */
    public function createPost(array $data, User $admin): BlogPost
    {
        $data['user_id'] = $admin->id;

        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && !empty($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        $post = BlogPost::create($data);
        
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
        Log::info('Админ создал пост блога', ['post_id' => $post->id, 'admin_id' => $admin->id]);

        return $post;
    }

    /**
     * Обновить пост
     */
    public function updatePost(BlogPost $post, array $data, User $admin): BlogPost
    {
        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && !empty($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        // ФИКС: Берем только нужные поля для диффа, чтобы не писать огромный HTML в базу логов
        $before = [
            'title' => $post->getOriginal('title'), 
            'slug' => $post->getOriginal('slug'), 
            'status' => $post->getOriginal('status')
        ];
        
        $post->update($data);
        $post->refresh();
        
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
        Log::info('Админ обновил пост блога', ['post_id' => $post->id, 'admin_id' => $admin->id]);

        return $post;
    }

    /**
     * Быстрый Toggle (Опубликовать / В черновики / Из архива в черновики)
     */
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
    }

    /**
     * Отправить в архив
     */
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
    }

    /**
     * Восстановить из архива
     */
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
    }

    /**
     * Удалить навсегда
     */
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
        $post->delete();
    }

    /**
     * Массовое применение действий
     */
    public function applyBulk(array $postIds, string $action, bool $isArchiveTab, User $admin): string
    {
        $posts = BlogPost::whereIn('id', $postIds)->get();

        foreach ($posts as $post) {
            match($action) {
                'delete'   => ($isArchiveTab) ? $this->delete($post, $admin) : null,
                'publish'  => ($post->status !== 'published') ? $this->toggle($post, $admin) : null,
                'draft'    => ($post->status !== 'draft') ? $this->toggle($post, $admin) : null,
                'archive'  => ($post->status !== 'archived') ? $this->archive($post, $admin) : null,
                default => null,
            };
        }

        return match($action) {
            'delete'   => 'Посты удалены',
            'publish'  => 'Выбранные посты опубликованы',
            'draft'    => 'Выбранные посты сняты с публикации',
            'archive'  => 'Посты перемещены в архив',
            default    => 'Действие применено',
        };
    }
}