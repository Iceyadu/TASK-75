<?php
declare(strict_types=1);

namespace app\command;

use app\model\Listing;
use app\model\Organization;
use app\model\SearchDictionary;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * Rebuild search dictionary from listing content.
 *
 * For each organization, extracts words from all active listing titles,
 * descriptions, and tags. Words are normalized (lowercase, strip non-alpha,
 * min length 3). Only words with frequency >= 2 are kept. The dictionary
 * table is truncated and rebuilt per org (idempotent full rebuild).
 */
class SearchBuildDictionaryCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('search:build-dictionary')
             ->setDescription('Rebuild search dictionary from listing content');
    }

    protected function execute(Input $input, Output $output): int
    {
        $organizations = Organization::select();
        $totalWords = 0;
        $orgCount = 0;

        foreach ($organizations as $org) {
            $orgId = $org->id;

            // Get all active listings for this org
            $listings = Listing::where('organization_id', $orgId)
                ->where('status', 'active')
                ->select();

            $wordFrequency = [];

            foreach ($listings as $listing) {
                $text = implode(' ', array_filter([
                    $listing->title ?? '',
                    $listing->description ?? '',
                    $listing->tags ?? '',
                ]));

                $words = $this->extractWords($text);

                foreach ($words as $word) {
                    if (!isset($wordFrequency[$word])) {
                        $wordFrequency[$word] = 0;
                    }
                    $wordFrequency[$word]++;
                }
            }

            // Filter: keep only words with frequency >= 2
            $wordFrequency = array_filter($wordFrequency, function (int $count): bool {
                return $count >= 2;
            });

            // Truncate existing dictionary for this org
            SearchDictionary::where('organization_id', $orgId)->delete();

            // Rebuild
            $nowTs        = date('Y-m-d H:i:s');
            $insertData   = [];
            foreach ($wordFrequency as $word => $frequency) {
                $insertData[] = [
                    'organization_id' => $orgId,
                    'word'            => (string) $word,
                    'frequency'       => $frequency,
                    // Table has updated_at (no created_at); explicit timestamp keeps bulk inserts deterministic.
                    'updated_at'      => $nowTs,
                ];
            }

            // Batch insert in chunks
            foreach (array_chunk($insertData, 500) as $chunk) {
                (new SearchDictionary())->saveAll($chunk);
            }

            $wordCount = count($wordFrequency);
            $totalWords += $wordCount;

            if ($wordCount > 0) {
                $orgCount++;
            }
        }

        Log::info("search:build-dictionary completed: {$totalWords} words for {$orgCount} organizations");
        $output->writeln("Dictionary rebuilt: {$totalWords} words for {$orgCount} organizations.");

        return 0;
    }

    /**
     * Extract and normalize words from text.
     *
     * @param string $text
     * @return string[]
     */
    private function extractWords(string $text): array
    {
        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Strip non-alpha characters (keep spaces)
        $text = preg_replace('/[^a-z\s]/u', ' ', $text);

        // Split on whitespace
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Filter minimum length 3
        return array_filter($words, function (string $word): bool {
            return strlen($word) >= 3;
        });
    }
}
