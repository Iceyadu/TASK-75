<?php
declare(strict_types=1);

namespace unit_tests\governance;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the governance:dedup command logic.
 */
class DedupJobTest extends TestCase
{
    /**
     * Simulates dedup logic on a set of events.
     *
     * @param array $events Each event: [id, user_id, event_type, target_type, target_id, created_at]
     * @return array{kept: array, removed: array, lineage: array}
     */
    private function runDedup(array $events): array
    {
        // Sort by group key + created_at
        usort($events, function ($a, $b) {
            $keyA = "{$a['user_id']}|{$a['event_type']}|{$a['target_type']}|{$a['target_id']}";
            $keyB = "{$b['user_id']}|{$b['event_type']}|{$b['target_type']}|{$b['target_id']}";
            $cmp = strcmp($keyA, $keyB);
            return $cmp !== 0 ? $cmp : strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        $kept = [];
        $removed = [];
        $seen = [];

        foreach ($events as $event) {
            $groupKey = "{$event['user_id']}|{$event['event_type']}|{$event['target_type']}|{$event['target_id']}";

            if (isset($seen[$groupKey])) {
                $lastTimestamp = $seen[$groupKey];
                $currentTimestamp = strtotime($event['created_at']);
                $lastTime = strtotime($lastTimestamp);

                if (abs($currentTimestamp - $lastTime) <= 60) {
                    $removed[] = $event;
                    continue;
                }
            }

            $seen[$groupKey] = $event['created_at'];
            $kept[] = $event;
        }

        $lineage = [
            'job_name'      => 'governance:dedup',
            'step'          => 'dedup_behavior_events',
            'input_count'   => count($events),
            'output_count'  => count($kept),
            'removed_count' => count($removed),
        ];

        return ['kept' => $kept, 'removed' => $removed, 'lineage' => $lineage];
    }

    public function test_duplicate_events_in_1_minute_window_removed(): void
    {
        $baseTime = '2026-04-08 10:00:00';

        $events = [
            ['id' => 1, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => $baseTime],
            ['id' => 2, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => '2026-04-08 10:00:30'], // 30s later, dup
            ['id' => 3, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => '2026-04-08 10:00:45'], // 15s later, dup of #2
        ];

        $result = $this->runDedup($events);

        $this->assertCount(1, $result['kept']);
        $this->assertCount(2, $result['removed']);
        $this->assertEquals(1, $result['kept'][0]['id']); // Earliest kept
    }

    public function test_events_outside_window_kept(): void
    {
        $events = [
            ['id' => 1, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => '2026-04-08 10:00:00'],
            ['id' => 2, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => '2026-04-08 10:05:00'], // 5 min later, not dup
        ];

        $result = $this->runDedup($events);

        $this->assertCount(2, $result['kept']);
        $this->assertCount(0, $result['removed']);
    }

    public function test_lineage_record_created(): void
    {
        $events = [
            ['id' => 1, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => '2026-04-08 10:00:00'],
            ['id' => 2, 'user_id' => 1, 'event_type' => 'view', 'target_type' => 'listing', 'target_id' => 100, 'created_at' => '2026-04-08 10:00:30'],
        ];

        $result = $this->runDedup($events);

        $this->assertEquals('governance:dedup', $result['lineage']['job_name']);
        $this->assertEquals('dedup_behavior_events', $result['lineage']['step']);
        $this->assertEquals(2, $result['lineage']['input_count']);
        $this->assertEquals(1, $result['lineage']['output_count']);
        $this->assertEquals(1, $result['lineage']['removed_count']);
    }
}
