<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_runs')
            ->whereNull('conversation_id')
            ->orderBy('id')
            ->chunkById(100, function ($runs): void {
                foreach ($runs as $run) {
                    $assistant = DB::table('messages')->where('id', $run->message_id)->first();
                    $input = $run->input_message_id
                        ? DB::table('messages')->where('id', $run->input_message_id)->first()
                        : null;
                    DB::table('ai_runs')->where('id', $run->id)->update([
                        'actor_id' => $input?->sender_id,
                        'conversation_id' => $assistant?->conversation_id ?? $input?->conversation_id,
                        'current_stage' => $run->status === 'completed' ? 'completed' : ($run->status === 'failed' ? 'failed' : $run->status),
                        'queued_at' => $run->created_at,
                    ]);
                }
            }, 'id');

        DB::table('ai_execution_plans')
            ->whereNull('ai_run_id')
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $metadata = json_decode((string) ($plan->metadata_json ?? ''), true);
                    $sourceMessageId = is_array($metadata) ? ($metadata['source_message_id'] ?? null) : null;
                    if (! is_string($sourceMessageId) || $sourceMessageId === '') {
                        continue;
                    }

                    $runId = DB::table('ai_runs')
                        ->where('workspace_id', $plan->workspace_id)
                        ->where(function ($query) use ($sourceMessageId): void {
                            $query->where('message_id', $sourceMessageId)
                                ->orWhere('input_message_id', $sourceMessageId);
                        })
                        ->orderByDesc('created_at')
                        ->value('id');
                    if (! $runId) {
                        continue;
                    }

                    DB::table('ai_execution_plans')->where('id', $plan->id)->update(['ai_run_id' => $runId]);
                    DB::table('ai_runs')->where('id', $runId)->update(['execution_plan_id' => $plan->id]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Context backfill is intentionally retained; all added columns are
        // removed by the preceding schema migration when fully rolled back.
    }
};
