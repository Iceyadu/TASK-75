<?php
declare(strict_types=1);

namespace app\command;

use app\model\BehaviorEvent;
use app\model\DataLineage;
use app\model\DataQualityMetric;
use app\model\Listing;
use app\model\ModerationQueue;
use app\model\Organization;
use app\model\Review;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * Compute data quality metrics for all organizations.
 *
 * Calculates and stores seven quality metrics per organization:
 *   1. listing_completeness
 *   2. event_dedup_ratio
 *   3. missing_value_rate
 *   4. counter_drift
 *   5. review_credibility_distribution
 *   6. moderation_queue_depth
 *   7. stale_listing_rate
 */
class GovernanceQualityCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('governance:quality')
             ->setDescription('Compute data quality metrics for all organizations');
    }

    protected function execute(Input $input, Output $output): int
    {
        $organizations = Organization::select();
        $orgCount = 0;
        $today = date('Y-m-d');

        foreach ($organizations as $org) {
            $orgId = $org->id;
            $metrics = [];

            // 1. listing_completeness: % of listings where description, baggage_notes, and tags are all non-null
            $totalListings = Listing::where('organization_id', $orgId)->count();
            $completeListings = Listing::where('organization_id', $orgId)
                ->whereNotNull('description')
                ->whereNotNull('baggage_notes')
                ->whereNotNull('tags')
                ->count();
            $metrics['listing_completeness'] = $totalListings > 0
                ? round($completeListings / $totalListings, 4)
                : 1.0;

            // 2. event_dedup_ratio: events removed today / total events before dedup (from lineage)
            $dedupLineage = DataLineage::where('organization_id', $orgId)
                ->where('job_name', 'governance:dedup')
                ->where('executed_at', '>=', $today . ' 00:00:00')
                ->where('executed_at', '<=', $today . ' 23:59:59')
                ->order('executed_at', 'desc')
                ->find();

            if ($dedupLineage && $dedupLineage->input_count > 0) {
                $metrics['event_dedup_ratio'] = round(
                    (int) $dedupLineage->removed_count / (int) $dedupLineage->input_count,
                    4
                );
            } else {
                $metrics['event_dedup_ratio'] = 0.0;
            }

            // 3. missing_value_rate: % of listings with NULL description or NULL tags
            $missingListings = Listing::where('organization_id', $orgId)
                ->where(function ($query) {
                    $query->whereNull('description')->whereOr('tags', null);
                })
                ->count();
            $metrics['missing_value_rate'] = $totalListings > 0
                ? round($missingListings / $totalListings, 4)
                : 0.0;

            // 4. counter_drift: MAX(ABS(listing.view_count - actual_event_count))
            $maxDrift = 0;
            $listings = Listing::where('organization_id', $orgId)->select();
            foreach ($listings as $listing) {
                $actualViews = BehaviorEvent::where('organization_id', $orgId)
                    ->where('event_type', 'view')
                    ->where('target_type', 'listing')
                    ->where('target_id', $listing->id)
                    ->count();
                $drift = abs((int) $listing->view_count - $actualViews);
                if ($drift > $maxDrift) {
                    $maxDrift = $drift;
                }
            }
            $metrics['counter_drift'] = $maxDrift;

            // 5. review_credibility_distribution: JSON with min, p25, median, p75, max
            $scores = Review::where('organization_id', $orgId)
                ->whereNotNull('credibility_score')
                ->order('credibility_score', 'asc')
                ->column('credibility_score');

            if (!empty($scores)) {
                $count = count($scores);
                $metrics['review_credibility_distribution'] = json_encode([
                    'min'    => round((float) $scores[0], 4),
                    'p25'    => round((float) $scores[(int) floor($count * 0.25)], 4),
                    'median' => round((float) $scores[(int) floor($count * 0.5)], 4),
                    'p75'    => round((float) $scores[(int) floor($count * 0.75)], 4),
                    'max'    => round((float) $scores[$count - 1], 4),
                ]);
            } else {
                $metrics['review_credibility_distribution'] = json_encode([
                    'min' => 0, 'p25' => 0, 'median' => 0, 'p75' => 0, 'max' => 0,
                ]);
            }

            // 6. moderation_queue_depth: COUNT of pending items
            $metrics['moderation_queue_depth'] = ModerationQueue::where('organization_id', $orgId)
                ->where('status', 'pending')
                ->count();

            // 7. stale_listing_rate: % of active listings where last_activity_at < 7 days ago (or NULL)
            $activeListings = Listing::where('organization_id', $orgId)
                ->where('status', 'active')
                ->count();
            $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $staleListings = Listing::where('organization_id', $orgId)
                ->where('status', 'active')
                ->where(function ($query) use ($sevenDaysAgo) {
                    $query->whereNull('last_activity_at')
                          ->whereOr('last_activity_at', '<', $sevenDaysAgo);
                })
                ->count();
            $metrics['stale_listing_rate'] = $activeListings > 0
                ? round($staleListings / $activeListings, 4)
                : 0.0;

            // Store each metric
            foreach ($metrics as $metricName => $value) {
                DataQualityMetric::create([
                    'organization_id' => $orgId,
                    'metric_date'     => $today,
                    'metric_name'     => $metricName,
                    'metric_value'    => is_string($value) ? $value : (string) $value,
                    'details'         => null,
                    'computed_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            $orgCount++;
        }

        Log::info("governance:quality completed: metrics computed for {$orgCount} organizations");
        $output->writeln("Quality metrics computed for {$orgCount} organizations.");

        return 0;
    }
}
