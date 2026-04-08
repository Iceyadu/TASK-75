<?php
declare(strict_types=1);

namespace app\service;

use app\model\SearchDictionary;

class SearchService
{
    /**
     * Get autocomplete suggestions from the search dictionary.
     */
    public function getSuggestions(int $orgId, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return SearchDictionary::where('organization_id', $orgId)
            ->whereLike('word', $query . '%')
            ->order('frequency', 'desc')
            ->limit(10)
            ->column('word');
    }

    /**
     * Compute a did-you-mean suggestion via Levenshtein distance.
     */
    public function getDidYouMean(int $orgId, string $query): ?array
    {
        $query = trim(mb_strtolower($query));
        if ($query === '') {
            return null;
        }

        $allWords = SearchDictionary::where('organization_id', $orgId)
            ->column('word');

        if (empty($allWords)) {
            return null;
        }

        $closestWord     = null;
        $closestDistance  = PHP_INT_MAX;

        foreach ($allWords as $word) {
            $lowerWord = mb_strtolower($word);
            $distance  = levenshtein($query, $lowerWord);

            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestWord    = $word;
            }
        }

        // Only suggest if distance <= 2 and distance > 0 (not an exact match)
        if ($closestWord !== null && $closestDistance > 0 && $closestDistance <= 2) {
            $maxLen     = max(mb_strlen($query), mb_strlen($closestWord));
            $confidence = $maxLen > 0 ? 1 - ($closestDistance / $maxLen) : 0;

            return [
                'original'   => $query,
                'suggestion' => $closestWord,
                'confidence' => round($confidence, 4),
            ];
        }

        return null;
    }
}
