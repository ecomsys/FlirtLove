<?php

namespace App\Actions\Admin;

use App\Enums\ReportResolution;
use App\Enums\ReportReason;
use App\Models\AdminLog;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportModerated;
use App\Notifications\UserWarned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $this->clearCaches();

        // 1. Уведомляем жалобщика (что жалоба решена)
        if ($report->reporter) {
            $report->reporter->notify(new ReportModerated(
                reportId: $report->id,
                reportableType: $report->reportable_type,
                reportableId: $report->reportable_id,
                reportedName: $report->reported?->name,
                reason: $report->reason,
                action: 'resolved',
                additionalInfo: $note
            ));
        }

        // 2. ФИКС: Если вынесено предупреждение — уведомляем нарушителя (перенесено из Livewire!)
        if ($resolution === ReportResolution::Warn && $report->reported) {
            $reasonText = 'Нарушение правил сервиса';
            $reportReasonEnum = ReportReason::tryFrom($report->reason ?? '');
            if ($reportReasonEnum) {
                $reasonText = $reportReasonEnum->label();
            }
            
            try {
                $report->reported->notify(new UserWarned($reasonText));
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления UserWarned: ' . $e->getMessage());
            }
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
        
        $report->resolve($admin->id, 'no_action', $note);
        
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
        $this->clearCaches();

        if ($report->reporter) {
            $report->reporter->notify(new ReportModerated(
                reportId: $report->id,
                reportableType: $report->reportable_type,
                reportableId: $report->reportable_id,
                reportedName: $report->reported?->name,
                reason: $report->reason,
                action: 'rejected',
                additionalInfo: $note
            ));
        }
    }

    /**
     * МАССОВОЕ ЗАКРЫТИЕ ЖАЛОБ (Оптимизировано под High-Load)
     */
    public function bulkResolveReports($reports, User $admin, ReportResolution $resolution): void
    {
        if ($reports->isEmpty()) return;

        $reports->loadMissing(['reported', 'reporter']);

        $reportIds = $reports->pluck('id');
        $resolutionValue = $resolution->value;
        $resolutionNote = "Автоматическое закрытие при: {$resolution->label()}";
        $resolvedAt = now();

        DB::transaction(function () use ($reports, $reportIds, $admin, $resolutionValue, $resolutionNote, $resolvedAt) {
            Report::whereIn('id', $reportIds)->update([
                'status' => 'resolved',
                'resolution' => $resolutionValue,
                'resolution_note' => $resolutionNote,
                'admin_id' => $admin->id,
                'resolved_at' => $resolvedAt,
            ]);

            $notifiedReporters = []; 

            foreach ($reports as $report) {
                if ($report->reporter && !isset($notifiedReporters[$report->reporter_id])) {
                    $report->reporter->notify(new ReportModerated(
                        reportId: $report->id,
                        reportableType: $report->reportable_type,
                        reportableId: $report->reportable_id,
                        reportedName: $report->reported?->name,
                        reason: $report->reason,
                        action: 'resolved',
                        additionalInfo: $resolutionNote
                    ));
                    $notifiedReporters[$report->reporter_id] = true;
                }
            }

            $firstReport = $reports->first();
            $logIds = $reportIds->take(100)->toArray();
            
            $after = [
                'status' => 'resolved', 
                'resolution' => $resolutionValue, 
                'resolved_by' => $admin->id, 
                'resolved_at' => $resolvedAt->toDateTimeString(),
                'context' => [
                    'count' => $reportIds->count(),
                    'sample_ids' => $logIds,
                    'auto_resolved' => true,
                    'reason' => $firstReport->reason ?? null,
                ]
            ];
            
            $participants = $reports->pluck('reporter_id')->merge($reports->pluck('reported_id'))->filter()->unique()->toArray();
            
            AdminLog::record('report.bulk_resolve', $firstReport, $admin, null, $after, participants: $participants);
        });
        
        $this->clearCaches();
    }

    /**
     * Сброс кэшей счетчиков в админке
     */
    private function clearCaches(): void
    {
        Cache::forget('admin_sidebar_stats');
        Cache::forget('admin_report_counts_all');
        Cache::forget('admin_report_counts_user');
        Cache::forget('admin_report_counts_photo');
    }
}