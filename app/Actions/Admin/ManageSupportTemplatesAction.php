<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\SupportTemplate;
use App\Models\User;

class ManageSupportTemplatesAction
{
    /**
     * Создать шаблон.
     */
    public function create(array $data, User $admin): SupportTemplate
    {
        $template = SupportTemplate::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'template_id' => $template->id,
                'title' => $template->title,
                'category' => $template->category,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('template.create', $template, $admin, null, $after);
        
        return $template;
    }

    /**
     * Обновить шаблон.
     */
    public function update(SupportTemplate $template, array $data, User $admin): SupportTemplate
    {
        // Берем только ключевые поля для диффа, чтобы не писать весь текст (body) в БД
        $before = [
            'category' => $template->getOriginal('category'), 
            'title' => $template->getOriginal('title'), 
            'is_active' => $template->getOriginal('is_active')
        ];
        
        $template->update($data);
        $template->refresh();
        
        $after = [
            'category' => $template->category, 
            'title' => $template->title, 
            'is_active' => $template->is_active,
            'context' => [
                'template_id' => $template->id,
                'title' => $template->title,
                'category' => $template->category,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('template.update', $template, $admin, $before, $after);

        return $template;
    }

    /**
     * Удалить шаблон.
     */
    public function delete(SupportTemplate $template, User $admin): void
    {
        $templateId = $template->id;
        $templateTitle = $template->title;
        $templateCategory = $template->category;
        
        $after = [
            'status' => 'destroyed',
            'context' => [
                'template_id' => $templateId,
                'title' => $templateTitle,
                'category' => $templateCategory,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('template.delete', $template, $admin, null, $after);
        $template->delete();
    }
}