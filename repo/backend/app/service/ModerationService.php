<?php
declare(strict_types=1);

namespace app\service;

use app\exception\NotFoundException;
use app\model\Media;
use app\model\ModerationAction;
use app\model\ModerationQueue;
use app\model\Review;

class ModerationService
{
    protected AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * Check text against a sensitive-word list.
     *
     * @return array|null  ['matched' => true, 'words' => [...]] or null if clean
     */
    public function checkSensitiveWords(string $text): ?array
    {
        $wordListPath = (string) env('MODERATION_WORD_LIST_PATH', '');
        if ($wordListPath === '' || !file_exists($wordListPath)) {
            return null;
        }

        $contents = file_get_contents($wordListPath);
        if ($contents === false) {
            return null;
        }

        $words = array_filter(array_map('trim', explode("\n", $contents)));
        if (empty($words)) {
            return null;
        }

        $matchedWords = [];
        $lowerText    = mb_strtolower($text);

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }

            // Case-insensitive word-boundary match
            $pattern = '/\b' . preg_quote(mb_strtolower($word), '/') . '\b/iu';
            if (preg_match($pattern, $lowerText)) {
                $matchedWords[] = $word;
            }
        }

        if (empty($matchedWords)) {
            return null;
        }

        return [
            'matched' => true,
            'words'   => $matchedWords,
        ];
    }

    /**
     * Check for duplicate review text using trigram Jaccard similarity.
     */
    public function checkDuplicateText(int $userId, string $text, int $orgId): bool
    {
        $threshold    = (float) env('MODERATION_DUPLICATE_THRESHOLD', 0.85);
        $thirtyDaysAgo = date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));

        $recentReviews = Review::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('created_at', '>', $thirtyDaysAgo)
            ->column('text');

        foreach ($recentReviews as $existingText) {
            $similarity = $this->trigramJaccard($text, $existingText);
            if ($similarity >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for duplicate file by hash.
     */
    public function checkDuplicateFile(int $userId, string $fileHash, int $orgId): bool
    {
        $thirtyDaysAgo = date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));

        return Media::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('file_hash', $fileHash)
            ->where('created_at', '>', $thirtyDaysAgo)
            ->count() > 0;
    }

    /**
     * Flag an item for moderation.
     */
    public function flagItem(
        int $orgId,
        string $itemType,
        int $itemId,
        string $reason,
        string $details,
        ?float $credibilityScore
    ): ModerationQueue {
        $entry = new ModerationQueue();
        $entry->organization_id   = $orgId;
        $entry->item_type         = $itemType;
        $entry->item_id           = $itemId;
        $entry->flag_reason       = $reason;
        $entry->flag_details      = $details;
        $entry->credibility_score = $credibilityScore;
        $entry->status            = 'pending';
        $entry->save();

        return $entry;
    }

    /**
     * Approve a moderation queue item.
     */
    public function approve(int $queueId, int $moderatorId): void
    {
        $entry = ModerationQueue::find($queueId);
        if (!$entry) {
            throw new NotFoundException('Moderation queue item not found');
        }

        $entry->status = 'approved';
        $entry->save();

        // Create moderation action record
        $action = new ModerationAction();
        $action->queue_id     = $queueId;
        $action->moderator_id = $moderatorId;
        $action->action       = 'approve';
        $action->save();

        // If the item is a review, set status to published
        if ($entry->item_type === 'review') {
            $review = Review::find($entry->item_id);
            if ($review) {
                $review->status = 'published';
                $review->save();
            }
        }

        $this->auditService->log(
            (int) $entry->organization_id,
            $moderatorId,
            'moderation.approve',
            'moderation_queue',
            $queueId,
            ['status' => 'pending'],
            ['status' => 'approved']
        );
    }

    /**
     * Reject a moderation queue item.
     */
    public function reject(int $queueId, int $moderatorId, string $reason): void
    {
        $entry = ModerationQueue::find($queueId);
        if (!$entry) {
            throw new NotFoundException('Moderation queue item not found');
        }

        $entry->status        = 'rejected';
        $entry->reject_reason = $reason;
        $entry->save();

        // Create moderation action record
        $action = new ModerationAction();
        $action->queue_id     = $queueId;
        $action->moderator_id = $moderatorId;
        $action->action       = 'reject';
        $action->reason       = $reason;
        $action->save();

        // If the item is a review, set status to hidden
        if ($entry->item_type === 'review') {
            $review = Review::find($entry->item_id);
            if ($review) {
                $review->status = 'hidden';
                $review->save();
            }
        }

        $this->auditService->log(
            (int) $entry->organization_id,
            $moderatorId,
            'moderation.reject',
            'moderation_queue',
            $queueId,
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $reason]
        );
    }

    /**
     * Escalate a moderation queue item.
     */
    public function escalate(int $queueId, int $moderatorId): void
    {
        $entry = ModerationQueue::find($queueId);
        if (!$entry) {
            throw new NotFoundException('Moderation queue item not found');
        }

        $entry->status = 'escalated';
        $entry->save();

        $action = new ModerationAction();
        $action->queue_id     = $queueId;
        $action->moderator_id = $moderatorId;
        $action->action       = 'escalate';
        $action->save();

        $this->auditService->log(
            (int) $entry->organization_id,
            $moderatorId,
            'moderation.escalate',
            'moderation_queue',
            $queueId,
            ['status' => 'pending'],
            ['status' => 'escalated']
        );
    }

    /**
     * Compute trigram Jaccard similarity between two strings.
     */
    public function trigramJaccard(string $a, string $b): float
    {
        $trigramsA = $this->extractTrigrams($a);
        $trigramsB = $this->extractTrigrams($b);

        if (empty($trigramsA) && empty($trigramsB)) {
            return 1.0;
        }
        if (empty($trigramsA) || empty($trigramsB)) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($trigramsA, $trigramsB));
        $union        = count(array_unique(array_merge(array_keys($trigramsA), array_keys($trigramsB))));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    /**
     * Extract character trigrams from a string as a set (keys of assoc array).
     */
    private function extractTrigrams(string $text): array
    {
        $text     = mb_strtolower(trim($text));
        $trigrams = [];
        $length   = mb_strlen($text);

        for ($i = 0; $i <= $length - 3; $i++) {
            $trigram = mb_substr($text, $i, 3);
            $trigrams[$trigram] = true;
        }

        return $trigrams;
    }
}
