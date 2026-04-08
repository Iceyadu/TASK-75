<?php
declare(strict_types=1);

namespace app\controller;

use app\service\SearchService;

class SearchController extends BaseController
{
    protected SearchService $searchService;

    public function __construct()
    {
        parent::__construct();
        $this->searchService = new SearchService();
    }

    /**
     * GET /api/search/suggestions?q=...
     */
    public function suggestions()
    {
        $query = $this->request->get('q', '');

        $suggestions = $this->searchService->getSuggestions(
            $this->request->orgId,
            $query
        );

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => [
                'suggestions' => $suggestions,
            ],
        ], 200);
    }

    /**
     * GET /api/search/did-you-mean?q=...
     */
    public function didYouMean()
    {
        $query = $this->request->get('q', '');

        $result = $this->searchService->getDidYouMean(
            $this->request->orgId,
            $query
        );

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => [
                'did_you_mean' => $result,
            ],
        ], 200);
    }
}
