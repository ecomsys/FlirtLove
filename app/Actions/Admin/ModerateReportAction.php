<?php

namespace App\Actions\Admin;

use App\Enums\ReportResolution;
use App\Models\AdminLog;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportModerated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ModerateReportAction
{
    /**
     * Принять жалобу (наказать).
     */
    public function resolve(Report $report, User $admin, ReportResolution $resolution = ReportResolution::Warn, ?string $note = null): void
    {
        $before = [
            'status' => $report->getOriginal('status'), 
            'resolution' => $report->getOriginal('resolution')
        ];
        
        $report->resolve($admin->id, $resolution->value, $note);
        $report->refresh();
        
        $after = [
            'status' => 'resolved', 
            'resolution' => $resolution->value, 
            'resolved_by' => $admin->id, 
            'resolved_at' => now()->toDateTimeString(),
            'context' => [
                'report_id' => $report->id,
                'reporter_id' => $report->reporter_id,
                'reported_id' => $report->reported_id,
                'reason' => $report->reason,
                'resolution_label' => $resolution->label(),
                'note' => $note
            ]
        ];

        $participants = array_filter([$report->reporter_id, $report->reported_id]);

        AdminLog::record('report.resolve', $report, $admin, $before, $after, participants: $participants);
        Cache::forget('admin_sidebar_stats');
        

        if ($report->reporter) {
            $report->reporter->notify(new ReportModerated($report, 'resolved'));
        }
    }

    /**
     * Отклонить жалобу (нет нарушения).
     */
    public function reject(Report $report, User $admin, ?string $note = null): void
    {
        $before = [
            'status' => $report->getOriginal('status'), 
            'resolution' => $report->getOriginal('resolution')
        ];
        
        // Используем resolve модели, но передаем 'no_action' (модель сама поставит status='rejected')
        $report->resolve($admin->id, 'no_action', $note);
        $report->refresh();
        
        $after = [
            'status' => 'rejected', 
            'resolution' => 'no_action', 
            'resolved_by' => $admin->id, 
            'resolved_at' => now()->toDateTimeString(),
            'context' => [
                'report_id' => $report->id,
                'reporter_id' => $report->reporter_id,
                'reported_id' => $report->reported_id,
                'reason' => $report->reason,
                'note' => $note
            ]
        ];

        $participants = array_filter([$report->reporter_id, $report->reported_id]);

        AdminLog::record('report.reject', $report, $admin, $before, $after, participants: $participants);
        Cache::forget('admin_sidebar_stats');
        

        if ($report->reporter) {
            $report->reporter->notify(new ReportModerated($report, 'rejected'));
        }
    }

    /**
     * Массовое закрытие жалоб (например, при бане юзера или удалении фото).
     * Используется внутри toggleBan и deletePhoto.
     */
    public function bulkResolveReports($reports, User $admin, ReportResolution $resolution): void
    {
        // Массив для запоминания, кому мы уже отправили уведомление
        $notifiedReporters = [];

        foreach ($reports as $report) {
            $before = [
                'status' => $report->getOriginal('status'), 
                'resolution' => $report->getOriginal('resolution')
            ];
            
            $report->resolve($admin->id, $resolution->value, "Автоматическое закрытие при: {$resolution->label()}");
            $report->refresh();
            
            $after = [
                'status' => 'resolved', 
                'resolution' => $resolution->value, 
                'resolved_by' => $admin->id, 
                'resolved_at' => now()->toDateTimeString(),
                'context' => [
                    'report_id' => $report->id,
                    'reporter_id' => $report->reporter_id,
                    'reported_id' => $report->reported_id,
                    'reason' => $report->reason,
                    'auto_resolved' => true
                ]
            ];
            
            $participants = array_filter([$report->reporter_id, $report->reported_id]);

            AdminLog::record('report.resolve', $report, $admin, $before, $after, participants: $participants);
            

            if ($report->reporter && !in_array($report->reporter->id, $notifiedReporters)) {
                $report->reporter->notify(new ReportModerated($report, 'resolved'));
                $notifiedReporters[] = $report->reporter->id;
            }
        }
        Cache::forget('admin_sidebar_stats');
        
    }
}