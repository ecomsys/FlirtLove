<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ManagePagesAction
{
    public function create(array $data, User $admin): Page
    {
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
        $this->clearCache();
        Log::info("Админ создал новую страницу", ['page_id' => $page->id, 'title' => $page->title, 'admin_id' => $admin->id]);

        return $page;
    }

    public function update(Page $page, array $data, User $admin): Page
    {
        if (class_exists(\Mews\Purifier\Facades\Purifier::class) && isset($data['body'])) {
            $data['body'] = clean($data['body']);
        }

        $before = [
            'title' => $page->getOriginal('title'), 
            'slug' => $page->getOriginal('slug'), 
            'is_active' => $page->getOriginal('is_active')
        ];
        
        $page->update($data);

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
        $this->clearCache();
        Log::info("Админ обновил страницу", ['page_id' => $page->id, 'admin_id' => $admin->id]);

        return $page;
    }

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
        $this->clearCache();
        Log::info("Админ удалил страницу", ['page_id' => $page->id, 'admin_id' => $admin->id]);
    }

    public function toggleStatus(Page $page, User $admin): void
    {
        $before = ['is_active' => $page->getOriginal('is_active')];
        
        $page->update(['is_active' => !$page->is_active]);

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
        $this->clearCache();
    }

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
        $this->clearCache();
        Log::info("Админ продублировал страницу", ['source_page_id' => $page->id, 'new_page_id' => $new->id, 'admin_id' => $admin->id]);

        return $new;
    }

    /**
     * МАССОВЫЕ ДЕЙСТВИЯ (HIGH-LOAD ОПТИМИЗАЦИЯ)
     * Заменили цикл foreach на 1 Bulk-запрос к базе данных!
     */
    public function bulkAction(array $ids, string $action, User $admin): int
    {
        if (empty($ids) || empty($action)) return 0;

        $affectedCount = 0;

        if ($action === 'delete') {
            // 1 Bulk DELETE запрос
            $affectedCount = Page::whereIn('id', $ids)->delete();
            
            if ($affectedCount > 0) {
                AdminLog::record('page.bulk_delete', null, $admin, null, [
                    'count' => $affectedCount,
                    'sample_ids' => array_slice($ids, 0, 100)
                ]);
            }
        } else {
            $isActive = ($action === 'activate');
            
            // 1 Bulk UPDATE запрос (меняем статус только тем, у кого он отличается)
            $affectedCount = Page::whereIn('id', $ids)
                ->where('is_active', '!=', $isActive)
                ->update(['is_active' => $isActive]);

            if ($affectedCount > 0) {
                AdminLog::record('page.bulk_update', null, $admin, null, [
                    'action' => $action,
                    'count' => $affectedCount,
                    'sample_ids' => array_slice($ids, 0, 100)
                ]);
            }
        }

        $this->clearCache();
        return $affectedCount;
    }

    private function clearCache(): void
    {
        Cache::forget('admin_page_counts');
    }
}