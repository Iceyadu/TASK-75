<?php
declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\model\Listing;
use app\model\ListingVersion;
use app\model\ModerationQueue;
use app\model\SearchDictionary;
use think\Collection;

class ListingService
{
    protected ModerationService $moderationService;
    protected SearchService $searchService;

    public function __construct()
    {
        $this->moderationService = new ModerationService();
        $this->searchService     = new SearchService();
    }

    /**
     * Search listings with filters, sorting, highlighting, suggestions, and did-you-mean.
     */
    public function search(int $orgId, array $filters): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));
        $q       = trim($filters['q'] ?? '');
        $sort    = $filters['sort'] ?? 'newest';

        $query = Listing::where('organization_id', $orgId);

        // Text search
        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $likeQ = '%' . $q . '%';
                $subQuery->whereLike('title|description|tags', $likeQ);
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Vehicle type filter
        if (!empty($filters['vehicle_type'])) {
            $vehicleTypes = $this->normalizeVehicleTypes($filters['vehicle_type']);
            if (count($vehicleTypes) === 1) {
                $query->where('vehicle_type', $vehicleTypes[0]);
            } elseif (!empty($vehicleTypes)) {
                $query->whereIn('vehicle_type', $vehicleTypes);
            }
        }

        // Rider count range
        if (isset($filters['rider_count_min'])) {
            $query->where('rider_count', '>=', (int) $filters['rider_count_min']);
        }
        if (isset($filters['rider_count_max'])) {
            $query->where('rider_count', '<=', (int) $filters['rider_count_max']);
        }

        // Sorting
        switch ($sort) {
            case 'most_discussed':
                $query->order('comment_count', 'desc');
                break;
            case 'most_popular':
                $query->orderRaw('(favorite_count + view_count) DESC');
                break;
            case 'newest':
            default:
                $query->order('created_at', 'desc');
                break;
        }

        $total    = $query->count();
        $listings = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        // Build highlight data
        $highlights = [];
        if ($q !== '' && $listings->count() > 0) {
            $terms = preg_split('/\s+/', $q);
            foreach ($listings as $listing) {
                $highlighted = [];
                foreach (['title', 'description'] as $field) {
                    $value = $listing->$field ?? '';
                    foreach ($terms as $term) {
                        $value = preg_replace(
                            '/(' . preg_quote($term, '/') . ')/i',
                            '<em>$1</em>',
                            $value
                        );
                    }
                    $highlighted[$field] = $value;
                }
                $highlights[$listing->id] = $highlighted;
            }
        }

        // Fallback: recent active if no results for a query
        $recentActive = [];
        if ($q !== '' && $listings->count() === 0) {
            $recentActive = Listing::where('organization_id', $orgId)
                ->where('status', 'active')
                ->order('created_at', 'desc')
                ->limit(10)
                ->select()
                ->toArray();
        }

        // Suggestions and did-you-mean
        $suggestions = [];
        $didYouMean  = null;
        if ($q !== '') {
            $suggestions = $this->searchService->getSuggestions($orgId, $q);
            $didYouMean  = $this->searchService->getDidYouMean($orgId, $q);
        }

        $listingsArray = $listings->toArray();
        foreach ($listingsArray as &$listing) {
            $listingId = $listing['id'] ?? null;
            if ($listingId !== null && isset($highlights[$listingId])) {
                $listing['highlight'] = $highlights[$listingId];
            }
        }
        unset($listing);

        return [
            'listings'      => $listingsArray,
            'highlights'    => $highlights,
            'recent_active' => $recentActive,
            'suggestions'   => $suggestions,
            'did_you_mean'  => $didYouMean,
            'meta'          => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Create a new listing with optional publish.
     */
    public function create(int $orgId, int $userId, array $data): Listing
    {
        $publish = !empty($data['publish']);

        $listing = new Listing();
        $listing->organization_id  = $orgId;
        $listing->user_id          = $userId;
        $listing->title            = $data['title'];
        $listing->description      = $data['description'] ?? '';
        $listing->pickup_address   = $data['pickup_address'];
        $listing->dropoff_address  = $data['dropoff_address'];
        $listing->rider_count      = (int) $data['rider_count'];
        $listing->vehicle_type     = $data['vehicle_type'];
        $listing->baggage_notes    = $data['baggage_notes'] ?? '';
        $listing->time_window_start = $this->normalizeDateTime((string) $data['time_window_start']);
        $listing->time_window_end  = $this->normalizeDateTime((string) $data['time_window_end']);
        $listing->tags             = isset($data['tags']) ? json_encode($data['tags']) : '[]';
        $listing->status           = $publish ? 'active' : 'draft';
        $listing->version          = 1;
        $listing->comment_count    = 0;
        $listing->favorite_count   = 0;
        $listing->view_count       = 0;
        $listing->save();

        // Create first version snapshot
        $this->createVersionSnapshot($listing, 'Initial creation');

        // Sensitive word check
        $textToCheck = $listing->title . ' ' . $listing->description;
        $checkResult = $this->moderationService->checkSensitiveWords($textToCheck);
        if ($checkResult && $checkResult['matched']) {
            $listing->status = 'draft';
            $listing->save();

            $this->moderationService->flagItem(
                $orgId,
                'listing',
                $listing->id,
                'sensitive_words',
                'Matched words: ' . implode(', ', $checkResult['words']),
                null
            );
        }

        return $listing;
    }

    /**
     * Update an existing listing.
     */
    public function update(int $listingId, int $userId, array $data): Listing
    {
        $listing = Listing::find($listingId);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }
        if ((int) $listing->user_id !== $userId) {
            throw new ForbiddenException('You do not own this listing');
        }
        if (!in_array($listing->status, ['draft', 'active'])) {
            throw new BusinessException('Listing cannot be edited in its current status', 40901, 409);
        }

        $oldSnapshot = $this->takeSnapshot($listing);

        // Apply updates
        $updatableFields = [
            'title', 'description', 'pickup_address', 'dropoff_address',
            'rider_count', 'vehicle_type', 'baggage_notes',
            'time_window_start', 'time_window_end', 'tags',
        ];
        foreach ($updatableFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'tags' && is_array($data[$field])) {
                    $listing->$field = json_encode($data[$field]);
                } elseif ($field === 'rider_count') {
                    $listing->$field = (int) $data[$field];
                } elseif ($field === 'time_window_start' || $field === 'time_window_end') {
                    $listing->$field = $this->normalizeDateTime((string) $data[$field]);
                } else {
                    $listing->$field = $data[$field];
                }
            }
        }

        $listing->version = (int) $listing->version + 1;
        $listing->save();

        // Build change summary
        $newSnapshot = $this->takeSnapshot($listing);
        $changeSummary = $this->buildChangeSummary($oldSnapshot, $newSnapshot);
        $this->createVersionSnapshot($listing, $changeSummary);

        // Sensitive word check
        $textToCheck = $listing->title . ' ' . ($listing->description ?? '');
        $checkResult = $this->moderationService->checkSensitiveWords($textToCheck);
        if ($checkResult && $checkResult['matched']) {
            $listing->status = 'draft';
            $listing->save();

            $this->moderationService->flagItem(
                (int) $listing->organization_id,
                'listing',
                $listing->id,
                'sensitive_words',
                'Matched words: ' . implode(', ', $checkResult['words']),
                null
            );
        }

        return $listing;
    }

    /**
     * Publish a draft listing (transition draft -> active).
     */
    public function publish(int $listingId, int $userId): Listing
    {
        $listing = Listing::find($listingId);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }
        if ((int) $listing->user_id !== $userId) {
            throw new ForbiddenException('You do not own this listing');
        }
        if ($listing->status !== 'draft') {
            throw new BusinessException('Only draft listings can be published', 40901, 409);
        }

        // Validate required fields are present
        $required = ['title', 'pickup_address', 'dropoff_address', 'rider_count', 'vehicle_type', 'time_window_start', 'time_window_end'];
        $missing  = [];
        foreach ($required as $field) {
            if (empty($listing->$field)) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            throw new ValidationException(
                'Required fields missing for publication',
                array_fill_keys($missing, 'This field is required')
            );
        }

        $listing->status = 'active';
        $listing->save();

        return $listing;
    }

    /**
     * Unpublish a listing (transition active -> draft).
     */
    public function unpublish(int $listingId, int $userId, bool $isModerator = false): Listing
    {
        $listing = Listing::find($listingId);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }
        if ((int) $listing->user_id !== $userId && !$isModerator) {
            throw new ForbiddenException('You do not own this listing');
        }
        if ($listing->status !== 'active') {
            throw new BusinessException('Only active listings can be unpublished', 40901, 409);
        }

        $listing->status = 'draft';
        $listing->save();

        return $listing;
    }

    /**
     * Delete a draft listing.
     */
    public function destroy(int $listingId, int $userId): void
    {
        $listing = Listing::find($listingId);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }
        if ((int) $listing->user_id !== $userId) {
            throw new ForbiddenException('You do not own this listing');
        }
        if ($listing->status !== 'draft') {
            throw new BusinessException('Only draft listings can be deleted', 40901, 409);
        }

        $listing->delete();
    }

    /**
     * Bulk close active listings with no matched orders (admin only).
     */
    public function bulkClose(int $orgId, array $listingIds, string $reason): array
    {
        $closed = [];
        $failed = [];

        foreach ($listingIds as $id) {
            $listing = Listing::where('id', $id)
                ->where('organization_id', $orgId)
                ->find();

            if (!$listing) {
                $failed[] = ['id' => $id, 'reason' => 'Listing not found'];
                continue;
            }

            if ($listing->status !== 'active') {
                $failed[] = ['id' => $id, 'reason' => 'Listing is not active (status: ' . $listing->status . ')'];
                continue;
            }

            // Check for matched orders
            $hasMatchedOrders = \app\model\Order::where('listing_id', $id)
                ->whereIn('status', ['pending_match', 'accepted', 'in_progress'])
                ->count();

            if ($hasMatchedOrders > 0) {
                $failed[] = ['id' => $id, 'reason' => 'Listing has active orders'];
                continue;
            }

            $listing->status = 'closed';
            $listing->save();
            $closed[] = $id;
        }

        return [
            'closed' => $closed,
            'failed' => $failed,
        ];
    }

    /**
     * Get all versions for a listing.
     */
    public function getVersions(int $listingId): Collection
    {
        $listing = Listing::find($listingId);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }

        return ListingVersion::where('listing_id', $listingId)
            ->order('version', 'asc')
            ->select();
    }

    /**
     * Get a specific version of a listing.
     */
    public function getVersion(int $listingId, int $version): ListingVersion
    {
        $listingVersion = ListingVersion::where('listing_id', $listingId)
            ->where('version', $version)
            ->find();

        if (!$listingVersion) {
            throw new NotFoundException('Version not found');
        }

        return $listingVersion;
    }

    /**
     * Diff two versions of a listing, returning field-level changes.
     */
    public function diffVersions(int $listingId, int $v1, int $v2): array
    {
        $version1 = $this->getVersion($listingId, $v1);
        $version2 = $this->getVersion($listingId, $v2);

        $snapshot1 = is_string($version1->snapshot) ? json_decode($version1->snapshot, true) : ($version1->snapshot ?? []);
        $snapshot2 = is_string($version2->snapshot) ? json_decode($version2->snapshot, true) : ($version2->snapshot ?? []);

        $allKeys = array_unique(array_merge(array_keys($snapshot1), array_keys($snapshot2)));
        $changes = [];

        foreach ($allKeys as $field) {
            $oldVal = $snapshot1[$field] ?? null;
            $newVal = $snapshot2[$field] ?? null;

            if ($oldVal !== $newVal) {
                $changes[] = [
                    'field'     => $field,
                    'old_value' => $oldVal,
                    'new_value' => $newVal,
                ];
            }
        }

        return $changes;
    }

    // ---- Private helpers ----

    private function takeSnapshot(Listing $listing): array
    {
        return [
            'title'             => $listing->title,
            'description'       => $listing->description,
            'pickup_address'    => $listing->pickup_address,
            'dropoff_address'   => $listing->dropoff_address,
            'rider_count'       => $listing->rider_count,
            'vehicle_type'      => $listing->vehicle_type,
            'baggage_notes'     => $listing->baggage_notes,
            'time_window_start' => $listing->time_window_start,
            'time_window_end'   => $listing->time_window_end,
            'tags'              => $listing->tags,
            'status'            => $listing->status,
        ];
    }

    private function createVersionSnapshot(Listing $listing, string $changeSummary): void
    {
        $version = new ListingVersion();
        $version->listing_id     = $listing->id;
        $version->version        = (int) $listing->version;
        $version->snapshot       = json_encode($this->takeSnapshot($listing));
        $version->change_summary = $changeSummary;
        $version->created_by     = (int) $listing->user_id;
        $version->save();
    }

    private function buildChangeSummary(array $old, array $new): string
    {
        $changes = [];
        foreach ($new as $key => $value) {
            $oldVal = $old[$key] ?? null;
            if ($value !== $oldVal) {
                $changes[] = $key;
            }
        }

        if (empty($changes)) {
            return 'No changes';
        }

        return 'Updated: ' . implode(', ', $changes);
    }

    /**
     * @param mixed $rawVehicleType
     * @return array<int,string>
     */
    private function normalizeVehicleTypes($rawVehicleType): array
    {
        $allowed = ['sedan', 'suv', 'van'];
        $values = is_array($rawVehicleType)
            ? $rawVehicleType
            : explode(',', (string) $rawVehicleType);

        $normalized = [];
        foreach ($values as $value) {
            $item = strtolower(trim((string) $value));
            if ($item !== '' && in_array($item, $allowed, true)) {
                $normalized[] = $item;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d h:i A',
            'Y-m-d h:i:s A',
        ];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            throw new ValidationException('Invalid time format', [
                'time_window' => 'Please provide a valid datetime value.',
            ]);
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
