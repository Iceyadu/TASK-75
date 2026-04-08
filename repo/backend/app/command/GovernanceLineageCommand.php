<?php
declare(strict_types=1);

namespace app\command;

use app\model\DataLineage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

/**
 * Record lineage summary for today's governance runs.
 *
 * Queries data_lineage for today's runs, computes totals across all steps,
 * and records a meta-lineage entry (step='daily_summary'). This is the
 * "lineage of lineage" ensuring a top-level record exists.
 */
class GovernanceLineageCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('governance:lineage')
             ->setDescription("Record lineage summary for today's governance runs");
    }

    protected function execute(Input $input, Output $output): int
    {
        $today = date('Y-m-d');
        $startOfDay = $today . ' 00:00:00';
        $endOfDay = $today . ' 23:59:59';

        // Query all lineage records for today, excluding daily_summary to avoid self-referencing
        $todayRecords = DataLineage::where('executed_at', '>=', $startOfDay)
            ->where('executed_at', '<=', $endOfDay)
            ->where('step', '<>', 'daily_summary')
            ->select();

        $totalInput = 0;
        $totalOutput = 0;
        $totalRemoved = 0;

        foreach ($todayRecords as $record) {
            $totalInput += (int) $record->input_count;
            $totalOutput += (int) $record->output_count;
            $totalRemoved += (int) $record->removed_count;
        }

        $runId = $this->generateUuid();

        // Record the meta-lineage entry
        DataLineage::create([
            'organization_id' => null,
            'job_name'        => 'governance:lineage',
            'run_id'          => $runId,
            'step'            => 'daily_summary',
            'input_count'     => $totalInput,
            'output_count'    => $totalOutput,
            'removed_count'   => $totalRemoved,
            'details'         => json_encode([
                'date'         => $today,
                'record_count' => count($todayRecords),
                'steps'        => array_unique(array_column($todayRecords->toArray(), 'step')),
            ]),
            'executed_at'     => date('Y-m-d H:i:s'),
        ]);

        Log::info("governance:lineage daily summary recorded for {$today}: input={$totalInput}, output={$totalOutput}, removed={$totalRemoved}");
        $output->writeln("Lineage summary recorded for {$today}.");

        return 0;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
