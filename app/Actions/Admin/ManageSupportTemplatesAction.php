<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\SupportTemplate;
use App\Models\SupportTemplateCategory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ManageSupportTemplatesAction
{
    // ============================================
    // МЕТОДЫ ДЛЯ ШАБЛОНОВ
    // ============================================

    public function create(array $data, User $admin): SupportTemplate
    {
        $template = SupportTemplate::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'template_id' => $template->id,
                'title' => $template->title,
                'category_id' => $template->category_id,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support_template.create', $template, $admin, null, $after);
        
        $this->clearCaches();
        return $template;
    }

    public function update(SupportTemplate $template, array $data, User $admin): SupportTemplate
    {
        $before = [
            'title' => $template->getOriginal('title'), 
            'category_id' => $template->getOriginal('category_id'),
            'is_active' => $template->getOriginal('is_active')
        ];
        
        $template->update($data);
        
        $after = [
            'title' => $template->title, 
            'category_id' => $template->category_id,
            'is_active' => $template->is_active,
            'context' => [
                'template_id' => $template->id,
                'title' => $template->title,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support_template.update', $template, $admin, $before, $after);

        $this->clearCaches();
        return $template;
    }

    public function delete(SupportTemplate $template, User $admin): void
    {
        $after = [
            'status' => 'destroyed',
            'context' => [
                'template_id' => $template->id,
                'title' => $template->title,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support_template.delete', $template, $admin, null, $after);
        $template->delete();
        
        $this->clearCaches();
    }

    // ============================================
    // МЕТОДЫ ДЛЯ КАТЕГОРИЙ
    // ============================================

    public function createCategory(array $data, User $admin): SupportTemplateCategory
    {
        $category = SupportTemplateCategory::create($data);
        
        $after = [
            'status' => 'created',
            'context' => [
                'category_id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support_template_category.create', $category, $admin, null, $after);
        
        $this->clearCaches();
        return $category;
    }

    public function updateCategory(SupportTemplateCategory $category, array $data, User $admin): SupportTemplateCategory
    {
        $before = [
            'name' => $category->getOriginal('name'),
            'slug' => $category->getOriginal('slug'),
            'is_active' => $category->getOriginal('is_active'),
            'sort_order' => $category->getOriginal('sort_order')
        ];
        
        $category->update($data);
        
        $after = [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'context' => [
                'category_id' => $category->id,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support_template_category.update', $category, $admin, $before, $after);

        $this->clearCaches();
        return $category;
    }

    public function deleteCategory(SupportTemplateCategory $category, User $admin): void
    {
        $before = [
            'name' => $category->getOriginal('name'),
            'slug' => $category->getOriginal('slug')
        ];
        
        $after = [
            'status' => 'destroyed',
            'context' => [
                'category_id' => $category->id,
                'name' => $category->name,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support_template_category.delete', $category, $admin, $before, $after);
        $category->delete();
        
        $this->clearCaches();
    }

    /**
     * Сброс всех кэшей, связанных с шаблонами и категориями поддержки
     */
    private function clearCaches(): void
    {
        Cache::forget('admin_support_templates'); // Кэш, который читает чат поддержки
        Cache::forget('admin_support_template_counts'); // Кэш счетчиков на этой странице
    }
}