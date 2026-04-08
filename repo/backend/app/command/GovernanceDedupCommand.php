<?php
declare(strict_types=1);

namespace app\command;

use app\model\BehaviorEvent;
use app\model\DataLineage;
use app\model\Organization;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * Deduplicate behavior events within 1-minute windows.
 *
 * For each organization, finds duplicate behavior_events that share the same
 * user_id, event_type, target_type, and target_id within a 1-minute window.
 * Keeps the earliest record, deletes duplicates.
 *
 * Idempotent: each run uses a unique run_id, creating separate lineage entries.
 */
class GovernanceDedupCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('governance:dedup')
             ->setDescription('Deduplicate behavior events within 1-minute windows');
    }

    protected function execute(Input $input, Output $output): int
    {
        $runId = $this->generateUuid();
        $totalRemoved = 0;
        $orgCount = 0;

        $organizations = Organization::select();

        foreach ($organizations as $org) {
            $orgId = $org->id;

            // Find all events for this org, ordered by created_at ascending
            $events = BehaviorEvent::where('organization_id', $orgId)
                ->order('user_id', 'asc')
                ->order('event_type', 'asc')
                ->order('target_type', 'asc')
                ->order('target_id', 'asc')
                ->order('created_at', 'asc')
                ->select();

            $inputCount = count($events);
            $toDelete = [];
            $seen = [];

            foreach ($events as $event) {
                $groupKey = implode('|', [
                    $event->user_id,
                    $event->event_type,
                    $event->target_type,
                    $event->target_id,
                ]);

                if (isset($seen[$groupKey])) {
                    $lastTimestamp = $seen[$groupKey];
                    $currentTimestamp = strtotime($event->created_at);
                    $lastTime = strtotime($lastTimestamp);

                    // Within 1-minute window: mark as duplicate
                    if (abs($currentTimestamp - $lastTime) <= 60) {
                        $toDelete[] = $event->id;
                        continue;
                    }
                }

                // Keep this event and update the group's latest timestamp
                $seen[$groupKey] = $event->created_at;
            }

            if (!empty($toDelete)) {
                // Delete in batches to avoid overly long queries
                foreach (array_chunk($toDelete, 500) as $chunk) {
                    BehaviorEvent::whereIn('id', $chunk)->delete();
                }
            }

            $removedCount = count($toDelete);
            $outputCount = $inputCount - $removedCount;
            $totalRemoved += $removedCount;

            if ($removedCount > 0) {
                $orgCount++;
            }

            // Record lineage
            DataLineage::create([
                'organization_id' => $orgId,
                'job_name'        => 'governance:dedup',
                'run_id'          => $runId,
                'step'            => 'dedup_behavior_events',
                'input_count'     => $inputCount,
                'output_count'    => $outputCount,
                'removed_count'   => $removedCount,
                'details'         => null,
                'executed_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        Log::info("governance:dedup completed: removed {$totalRemoved} duplicates across {$orgCount} organizations (run_id={$runId})");
        $output->writeln("Dedup complete: removed {$totalRemoved} duplicate events across {$orgCount} organizations.");

        return 0;
    }

    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant 1

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
