<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManagePagesAction
{
        /**
     * Создать новую страницу.
     */
    public function create(array $data, User $admin): Page
    {
        // Очистка HTML от XSS
        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && isset($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        $page = Page::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'page_id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('page.create', $page, $admin, null, $after);
        Log::info("Админ создал новую страницу", ['page_id' => $page->id, 'title' => $page->title, 'admin_id' => $admin->id]);

        return $page;
    }

    /**
     * Обновить существующую страницу.
     */
    public function update(Page $page, array $data, User $admin): Page
    {
        // Очистка HTML от XSS
        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && isset($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        // Берем только нужные поля для диффа, чтобы не писать весь HTML в лог
        $before = [
            'title' => $page->getOriginal('title'), 
            'slug' => $page->getOriginal('slug'), 
            'is_active' => $page->getOriginal('is_active')
        ];
        
        $page->update($data);
        $page->refresh();

        $after = [
            'title' => $page->title, 
            'slug' => $page->slug, 
            'is_active' => $page->is_active,
            'context' => [
                'page_id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('page.update', $page, $admin, $before, $after);
        Log::info("Админ обновил страницу", ['page_id' => $page->id, 'admin_id' => $admin->id]);

        return $page;
    }
    
    /**
     * Удалить страницу.
     */
    public function delete(Page $page, User $admin): void
    {
        $before = ['title' => $page->getOriginal('title'), 'slug' => $page->getOriginal('slug')];
        
        $after = [
            'status' => 'destroyed', 
            'deleted_by' => $admin->id,
            'context' => [
                'page_id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('page.delete', $page, $admin, $before, $after);
        $page->delete();
        
        Log::info("Админ удалил страницу", ['page_id' => $page->id, 'admin_id' => $admin->id]);
    }

    /**
     * Переключить статус публикации.
     */
    public function toggleStatus(Page $page, User $admin): void
    {
        $before = ['is_active' => $page->getOriginal('is_active')];
        
        $page->update(['is_active' => !$page->is_active]);
        $page->refresh();

        $after = [
            'is_active' => $page->is_active, 
            'toggled_by' => $admin->id,
            'context' => [
                'page_id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('page.update', $page, $admin, $before, $after);
    }

    /**
     * Дублировать страницу.
     */
    public function duplicate(Page $page, User $admin): Page
    {
        $new = $page->replicate();
        $new->slug = $page->slug . '-copy-' . time();
        $new->title = $page->title . ' (Копия)';
        $new->is_active = false; 
        $new->save();

        $after = [
            'status' => 'created', 
            'context' => [
                'source_page_id' => $page->id,
                'new_page_id' => $new->id,
                'title' => $new->title,
                'slug' => $new->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('page.create', $new, $admin, null, $after);
        
        Log::info("Админ продублировал страницу", ['source_page_id' => $page->id, 'new_page_id' => $new->id, 'admin_id' => $admin->id]);

        return $new;
    }

    /**
     * Массовое применение действий.
     */
    public function bulkAction(array $ids, string $action, User $admin): int
    {
        if (empty($ids) || empty($action)) return 0;

        $pages = Page::whereIn('id', $ids)->get();
        $affectedCount = 0;

        DB::transaction(function () use ($pages, $action, $admin, &$affectedCount) {
            foreach ($pages as $page) {
                if ($action === 'delete') {
                    $this->delete($page, $admin);
                    $affectedCount++;
                } elseif ($action === 'activate' && !$page->is_active) {
                    $this->toggleStatus($page, $admin);
                    $affectedCount++;
                } elseif ($action === 'draft' && $page->is_active) {
                    $this->toggleStatus($page, $admin);
                    $affectedCount++;
                }
            }
        });

        return $affectedCount;
    }
}