<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BlogPostsAction
{
    /**
     * Создать пост
     */
    public function createPost(array $data): BlogPost
    {
        $data['user_id'] = Auth::id();

        if (class_exists(\Mews\Purifier\Facades\Purifier::class)) {
            $data['body'] = clean($data['body']);
        }

        $post = BlogPost::create($data);
        
        AdminLog::record('blog.create', $post, Auth::user());
        Log::info('Админ создал пост блога', ['post_id' => $post->id, 'admin_id' => Auth::id()]);

        return $post;
    }

    /**
     * Обновить пост
     */
    public function updatePost(BlogPost $post, array $data): BlogPost
    {
        if (class_exists(\Mews\Purifier\Facades\Purifier::class)) {
            $data['body'] = clean($data['body']);
        }

        $before = $post->getOriginal();
        
        $post->update($data);
        
        AdminLog::record('blog.update', $post, Auth::user(), $before, $post->fresh()->toArray());
        Log::info('Админ обновил пост блога', ['post_id' => $post->id, 'admin_id' => Auth::id()]);

        return $post;
    }

    /**
     * Быстрый Toggle (Опубликовать / В черновики / Из архива в черновики)
     */
    public function toggle(BlogPost $post): void
    {
        $oldStatus = $post->getOriginal('status');

        $newStatus = match($oldStatus) {
            'published' => 'draft',
            'archived'  => 'draft',
            default     => 'published',
        };

        $post->update(['status' => $newStatus]);

        AdminLog::record('blog.update', $post, Auth::user(), 
            ['status' => $oldStatus], 
            ['status' => $newStatus]
        );
    }

    /**
     * Отправить в архив
     */
    public function archive(BlogPost $post): void
    {
        if ($post->status === 'archived') return;

        $oldStatus = $post->getOriginal('status');
        $post->update(['status' => 'archived']);

        AdminLog::record('blog.update', $post, Auth::user(), ['status' => $oldStatus], ['status' => 'archived']);
    }

    /**
     * Восстановить из архива
     */
    public function restore(BlogPost $post): void
    {
        if ($post->status !== 'archived') return;

        $post->update(['status' => 'draft']);
        AdminLog::record('blog.update', $post, Auth::user(), ['status' => 'archived'], ['status' => 'draft']);
    }

    /**
     * Удалить навсегда
     */
    public function delete(BlogPost $post): void
    {
        AdminLog::record('blog.delete', $post, Auth::user());
        $post->delete();
    }

    /**
     * Массовое применение действий
     */
    public function applyBulk(array $postIds, string $action, bool $isArchiveTab): string
    {
        $posts = BlogPost::whereIn('id', $postIds)->get();

        foreach ($posts as $post) {
            match($action) {
                'delete'   => ($isArchiveTab) ? $this->delete($post) : null,
                'publish'  => ($post->status !== 'published') ? $this->toggle($post) : null,
                'draft'    => ($post->status !== 'draft') ? $this->toggle($post) : null,
                'archive'  => ($post->status !== 'archived') ? $this->archive($post) : null,
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