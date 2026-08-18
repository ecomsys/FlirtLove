<?php

namespace App\Actions\Admin;
use App\Enums\ReportResolution;

use App\Models\AdminLog;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportModerated;
use Illuminate\Support\Facades\Log;

class ModerateReportAction
{
    /**
     * Принять жалобу (наказать).
     */
   public function resolve(Report $report, User $admin, ReportResolution $resolution = ReportResolution::Warn, ?string $note = null): void
{
    $before = $report->only(['status', 'resolution', 'admin_id', 'resolved_at']);
    
    $report->resolve($admin->id, $resolution->value, $note);
        
        $after = $report->fresh()->only(['status', 'resolution', 'admin_id', 'resolved_at']);
        AdminLog::record('report.resolve', $report, $admin, $before, $after);

        if ($report->reporter) {
            $report->reporter->notify(new ReportModerated($report, 'resolved'));
        }
    }

    /**
     * Отклонить жалобу (нет нарушения).
     */
    public function reject(Report $report, User $admin, ?string $note = null): void
    {
        $before = $report->only(['status', 'resolution', 'admin_id', 'resolved_at']);
        
        // Используем resolve модели, но передаем 'no_action' (модель сама поставит status='rejected')
        $report->resolve($admin->id, 'no_action', $note);
        
        $after = $report->fresh()->only(['status', 'resolution', 'admin_id', 'resolved_at']);
        AdminLog::record('report.reject', $report, $admin, $before, $after);

        if ($report->reporter) {
            $report->reporter->notify(new ReportModerated($report, 'rejected'));
        }
    }

    /**
     * Массовое закрытие жалоб (например, при бане юзера или удалении фото).
     * Используется внутри toggleBan и deletePhoto.
     */
        /**
     * Массовое закрытие жалоб (например, при бане юзера или удалении фото).
     * Используется внутри toggleBan и deletePhoto.
     */
    public function bulkResolveReports($reports, User $admin, ReportResolution $resolution): void
{
    // Массив для запоминания, кому мы уже отправили уведомление
    $notifiedReporters = [];

    foreach ($reports as $report) {
        // 1. Закрываем жалобу напрямую через модель (чтобы не триггерить отправку письма в resolve())
        $before = $report->only(['status', 'resolution', 'admin_id', 'resolved_at']);
        $report->resolve($admin->id, $resolution->value, "Автоматическое закрытие при: {$resolution->label()}");
        $after = $report->fresh()->only(['status', 'resolution', 'admin_id', 'resolved_at']);
        
        // 2. Логируем
        AdminLog::record('report.resolve', $report, $admin, $before, $after);

        // 3. Отправляем уведомление только если этот юзер еще его не получал
        if ($report->reporter && !in_array($report->reporter->id, $notifiedReporters)) {
            $report->reporter->notify(new ReportModerated($report, 'resolved'));
            $notifiedReporters[] = $report->reporter->id; // Записываем, что Васе уже отправили
        }
    }
}
}