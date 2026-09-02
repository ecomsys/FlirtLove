<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Broadcast;
use App\Jobs\SendBroadcastJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class BroadcastsAction
{
    /**
     * Ручной запуск рассылки.
     */
    public function sendNow(int $id, User $admin): array
    {
        $broadcast = Broadcast::find($id);
        
        if (!$broadcast || !in_array($broadcast->status, ['draft', 'scheduled'])) {
            return ['success' => false, 'message' => 'Эту рассылку уже нельзя отправить.'];
        }

        try {
            $before = [
                'status' => $broadcast->getOriginal('status'), 
                'started_at' => $broadcast->getOriginal('started_at')
            ];

            $updated = Broadcast::where('id', $id)
                ->whereIn('status', ['draft', 'scheduled'])
                ->update([
                    'status' => 'sending', 
                    'started_at' => now()
                ]);
            
            if ($updated) {
                $broadcast->refresh();
                
                $targetUserId = $broadcast->target_audience['user_id'] ?? null;

                $after = [
                    'status' => 'sending', 
                    'started_at' => now()->toDateTimeString(),
                    'context' => [
                        'broadcast_id' => $broadcast->id,
                        'title' => $broadcast->title,
                        'type' => $broadcast->type,
                        'target_user_id' => $targetUserId
                    ]
                ];

                $participants = $targetUserId ? [$targetUserId] : [];

                SendBroadcastJob::dispatch($broadcast->id, $broadcast->target_audience)->onQueue('broadcasts');
                
                AdminLog::record('broadcast.send_now', $broadcast, $admin, $before, $after, participants: $participants);
                Log::info("Админ запустил рассылку вручную", ['broadcast_id' => $id, 'admin_id' => $admin->id]);
                
                return ['success' => true, 'message' => 'Рассылка поставлена в очередь'];
            }
            return ['success' => false, 'message' => 'Не удалось обновить статус рассылки.'];
        } catch (\Exception $e) {
            Log::error("Ошибка ручного запуска рассылки: " . $e->getMessage());
            $broadcast->markAsFailed();
            return ['success' => false, 'message' => 'Ошибка сервера при запуске!'];
        }
    }

    /**
     * Дублирование рассылки.
     */
    public function duplicateBroadcast(int $id, User $admin): ?Broadcast
    {
        try {
            $broadcast = Broadcast::find($id);
            if (!$broadcast) return null;

            $before = ['source_id' => $broadcast->id, 'source_title' => $broadcast->title];

            $new = $broadcast->replicate();
            $new->status = 'draft';
            $new->sent_at = null;
            $new->scheduled_at = null;
            $new->sent_count = 0;
            $new->failed_count = 0;
            $new->total_recipients = 0;
            $new->started_at = null;
            $new->save();

            $targetUserId = $new->target_audience['user_id'] ?? null;

            $after = [
                'new_id' => $new->id, 
                'new_title' => $new->title, 
                'status' => 'draft',
                'context' => [
                    'source_broadcast_id' => $broadcast->id,
                    'new_broadcast_id' => $new->id,
                    'title' => $new->title
                ]
            ];

            $participants = $targetUserId ? [$targetUserId] : [];

            AdminLog::record('broadcast.duplicate', $broadcast, $admin, $before, $after, participants: $participants);
            Log::info("Админ продублировал рассылку", ['source_id' => $id, 'new_id' => $new->id]);

            return $new;
        } catch (\Exception $e) {
            Log::error("Ошибка дублирования рассылки: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Удаление одной рассылки.
     */
    public function deleteBroadcast(int $id, User $admin): array
    {
        try {
            $broadcast = Broadcast::find($id);
            if (!$broadcast) {
                return ['success' => false, 'message' => 'Рассылка не найдена.'];
            }

            if ($broadcast->status === 'sending') {
                return ['success' => false, 'message' => 'Нельзя удалить рассылку в процессе отправки!'];
            }

            $targetUserId = $broadcast->target_audience['user_id'] ?? null;

            $before = [
                'status' => $broadcast->getOriginal('status'), 
                'title' => $broadcast->getOriginal('title')
            ];
            
            $after = [
                'status' => 'destroyed', 
                'deleted_by' => $admin->id,
                'context' => [
                    'broadcast_id' => $broadcast->id,
                    'title' => $broadcast->title,
                    'type' => $broadcast->type
                ]
            ];

            $participants = $targetUserId ? [$targetUserId] : [];

            AdminLog::record('broadcast.delete', $broadcast, $admin, $before, $after, participants: $participants);
            
            $broadcast->delete();
            Log::info("Админ удалил рассылку", ['broadcast_id' => $id]);

            return ['success' => true, 'message' => 'Рассылка удалена'];
        } catch (\Exception $e) {
            Log::error("Ошибка удаления рассылки: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка сервера!'];
        }
    }

    /**
     * Массовое удаление выбранных рассылок.
     */
    public function deleteSelected(array $ids, User $admin): int
    {
        if (empty($ids)) return 0;

        $actualDeletedCount = 0;
        
        DB::transaction(function () use ($ids, $admin, &$actualDeletedCount) {
            $broadcasts = Broadcast::whereIn('id', $ids)
                ->where('status', '!=', 'sending')
                ->lockForUpdate()
                ->get();
            
            foreach ($broadcasts as $broadcast) {
                $targetUserId = $broadcast->target_audience['user_id'] ?? null;

                $before = [
                    'status' => $broadcast->getOriginal('status'), 
                    'title' => $broadcast->getOriginal('title')
                ];
                
                $after = [
                    'status' => 'destroyed', 
                    'deleted_by' => $admin->id,
                    'context' => [
                        'broadcast_id' => $broadcast->id,
                        'title' => $broadcast->title
                    ]
                ];

                $participants = $targetUserId ? [$targetUserId] : [];

                AdminLog::record('broadcast.delete', $broadcast, $admin, $before, $after, participants: $participants);
                
                $broadcast->delete();
                $actualDeletedCount++;
            }
        });

        Log::info("Админ удалил рассылки", ['count' => $actualDeletedCount, 'admin_id' => $admin->id]);

        return $actualDeletedCount;
    }
}