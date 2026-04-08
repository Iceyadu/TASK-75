<?php
declare(strict_types=1);

namespace app\command;

use app\model\BehaviorEvent;
use app\model\DataLineage;
use app\model\Listing;
use app\model\Organization;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * Fill missing values in listing metadata from behavior events.
 *
 * For each organization, counts behavior_events grouped by listing for view,
 * favorite, and click events, then compares with the listing's denormalized
 * counters (view_count, favorite_count, comment_count). Updates listings
 * where counters have drifted.
 */
class GovernanceFillCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('governance:fill')
             ->setDescription('Fill missing values in listing metadata from behavior events');
    }

    protected function execute(Input $input, Output $output): int
    {
        $runId = $this->generateUuid();
        $totalUpdated = 0;

        $organizations = Organization::select();

        foreach ($organizations as $org) {
            $orgId = $org->id;

            // Count behavior events by listing and type
            $viewCounts = BehaviorEvent::where('organization_id', $orgId)
                ->where('event_type', 'view')
                ->where('target_type', 'listing')
                ->group('target_id')
                ->column('COUNT(*)', 'target_id');

            $favoriteCounts = BehaviorEvent::where('organization_id', $orgId)
                ->where('event_type', 'favorite')
                ->where('target_type', 'listing')
                ->group('target_id')
                ->column('COUNT(*)', 'target_id');

            $clickCounts = BehaviorEvent::where('organization_id', $orgId)
                ->where('event_type', 'click')
                ->where('target_type', 'listing')
                ->group('target_id')
                ->column('COUNT(*)', 'target_id');

            // Get all listings for this org
            $listings = Listing::where('organization_id', $orgId)->select();
            $updatedCount = 0;

            foreach ($listings as $listing) {
                $lid = $listing->id;
                $needsUpdate = false;

                $actualViews = $viewCounts[$lid] ?? 0;
                $actualFavorites = $favoriteCounts[$lid] ?? 0;
                $actualClicks = $clickCounts[$lid] ?? 0;

                if ((int) $listing->view_count !== $actualViews) {
                    $listing->view_count = $actualViews;
                    $needsUpdate = true;
                }

                if ((int) $listing->favorite_count !== $actualFavorites) {
                    $listing->favorite_count = $actualFavorites;
                    $needsUpdate = true;
                }

                if ((int) $listing->comment_count !== $actualClicks) {
                    $listing->comment_count = $actualClicks;
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $listing->save();
                    $updatedCount++;
                }
            }

            $totalUpdated += $updatedCount;

            // Record lineage
            DataLineage::create([
                'organization_id' => $orgId,
                'job_name'        => 'governance:fill',
                'run_id'          => $runId,
                'step'            => 'fill_listing_counters',
                'input_count'     => count($listings),
                'output_count'    => $updatedCount,
                'removed_count'   => 0,
                'details'         => null,
                'executed_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        Log::info("governance:fill completed: updated {$totalUpdated} listings (run_id={$runId})");
        $output->writeln("Fill complete: updated {$totalUpdated} listings.");

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
