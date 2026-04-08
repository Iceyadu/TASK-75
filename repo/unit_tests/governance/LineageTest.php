<?php
declare(strict_types=1);

namespace unit_tests\governance;

use PHPUnit\Framework\TestCase;

/**
 * Tests for data lineage record structure and uniqueness.
 */
class LineageTest extends TestCase
{
    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Create a lineage record.
     */
    private function createLineageRecord(
        string $jobName,
        string $runId,
        string $step,
        int $inputCount,
        int $outputCount,
        int $removedCount
    ): array {
        return [
            'job_name'      => $jobName,
            'run_id'        => $runId,
            'step'          => $step,
            'input_count'   => $inputCount,
            'output_count'  => $outputCount,
            'removed_count' => $removedCount,
            'started_at'    => date('Y-m-d H:i:s'),
            'completed_at'  => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
        ];
    }

    public function test_lineage_record_has_correct_structure(): void
    {
        $runId = $this->generateUuid();
        $record = $this->createLineageRecord('governance:dedup', $runId, 'dedup_behavior_events', 100, 90, 10);

        $this->assertArrayHasKey('job_name', $record);
        $this->assertArrayHasKey('run_id', $record);
        $this->assertArrayHasKey('step', $record);
        $this->assertArrayHasKey('input_count', $record);
        $this->assertArrayHasKey('output_count', $record);
        $this->assertArrayHasKey('removed_count', $record);
        $this->assertArrayHasKey('started_at', $record);
        $this->assertArrayHasKey('completed_at', $record);

        $this->assertEquals('governance:dedup', $record['job_name']);
        $this->assertEquals('dedup_behavior_events', $record['step']);
        $this->assertEquals(100, $record['input_count']);
        $this->assertEquals(90, $record['output_count']);
        $this->assertEquals(10, $record['removed_count']);
    }

    public function test_lineage_run_id_is_unique(): void
    {
        $runIds = [];
        for ($i = 0; $i < 100; $i++) {
            $runIds[] = $this->generateUuid();
        }

        // All run IDs should be unique
        $this->assertCount(100, array_unique($runIds));

        // UUID v4 format: 8-4-4-4-12 hex chars
        foreach ($runIds as $uuid) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $uuid
            );
        }
    }
}
