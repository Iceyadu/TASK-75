/**
 * reviews.js - Reviews List Page
 * Displays reviews with filtering by listing/user, pagination, verified badges.
 */
var ReviewsPage = (function () {
    'use strict';

    var state = {
        reviews: [],
        listingId: '',
        userId: '',
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        loading: false,
        error: null
    };

    function renderSkeleton() {
        var html = '';
        for (var i = 0; i < 3; i++) {
            html += '<div class="layui-card" style="margin-bottom:12px;">' +
                '<div class="layui-card-body">' + RCUtil.skeleton(3) + '</div></div>';
        }
        return html;
    }

    function renderMediaThumbnails(review) {
        if (!review.media || review.media.length === 0) return '';
        var html = '<div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">';
        review.media.forEach(function (m) {
            if (m.file_type === 'photo' && m.signed_url) {
                html += '<img src="' + RCUtil.escapeHtml(m.signed_url) + '" ' +
                    'style="width:56px;height:56px;object-fit:cover;border-radius:4px;border:1px solid #eee;" ' +
                    'alt="' + RCUtil.escapeHtml(m.file_name || 'photo') + '">';
            } else if (m.file_type === 'video') {
                html += '<div style="width:56px;height:56px;background:#f0f0f0;border-radius:4px;display:inline-flex;' +
                    'align-items:center;justify-content:center;border:1px solid #eee;">' +
                    '<i class="layui-icon layui-icon-play" style="font-size:20px;color:#666;"></i></div>';
            }
        });
        html += '</div>';
        return html;
    }

    function renderReviewCard(review) {
        var verified = review.credibility_score && review.credibility_score > 0.7;
        var authorName = review.user_name || review.user_info ? (review.user_info ? review.user_info.name : review.user_name) : 'User #' + review.user_id;
        var textExcerpt = review.text ? RCUtil.truncate(review.text, 200) : '';

        var html = '<div class="layui-card" style="margin-bottom:12px;">' +
            '<div class="layui-card-body" style="padding:16px;">';

        // Header: stars + verified
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
            RCUtil.starsHtml(review.rating || 0);
        if (verified) {
            html += '<span style="background:#e8f5e9;color:#2e7d32;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:500;">' +
                '<i class="layui-icon layui-icon-ok-circle" style="font-size:12px;"></i> Verified</span>';
        }
        html += '</div>';

        // Text
        if (textExcerpt) {
            html += '<div style="font-size:14px;color:#333;margin-bottom:8px;line-height:1.5;">' +
                RCUtil.escapeHtml(textExcerpt) + '</div>';
        }

        // Media
        html += renderMediaThumbnails(review);

        // Author and date
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:8px;border-top:1px solid #f0f0f0;">' +
            '<span style="font-size:13px;color:#666;">' + RCUtil.escapeHtml(authorName) + '</span>' +
            '<span style="font-size:12px;color:#999;">' + (review.created_at ? RCUtil.formatRelative(review.created_at) : '') + '</span>' +
            '</div>';

        html += '</div></div>';
        return html;
    }

    function renderContent(container) {
        var html = '<div style="max-width:800px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">Reviews</h2>';

        // Filter bar
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">' +
            '<div style="flex:1;min-width:200px;">' +
            '<input type="text" id="rc-reviews-listing-filter" class="layui-input" placeholder="Filter by Listing ID" ' +
            'value="' + RCUtil.escapeHtml(state.listingId) + '" style="height:36px;"></div>' +
            '<div style="flex:1;min-width:200px;">' +
            '<input type="text" id="rc-reviews-user-filter" class="layui-input" placeholder="Filter by User ID" ' +
            'value="' + RCUtil.escapeHtml(state.userId) + '" style="height:36px;"></div>' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="rc-reviews-filter-btn">Filter</button>' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="rc-reviews-clear-btn">Clear</button>' +
            '</div>';

        // Review list
        html += '<div id="rc-reviews-list">';
        if (state.loading) {
            html += renderSkeleton();
        } else if (state.error) {
            html += '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>';
        } else if (state.reviews.length === 0) {
            html += RCUtil.emptyState('No reviews found. Reviews will appear here after trips are completed.');
        } else {
            state.reviews.forEach(function (review) {
                html += renderReviewCard(review);
            });
        }
        html += '</div>';

        // Pagination
        html += '<div id="rc-reviews-pagination" style="margin-top:16px;text-align:center;"></div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Filter
        var filterBtn = container.querySelector('#rc-reviews-filter-btn');
        if (filterBtn) {
            filterBtn.addEventListener('click', function () {
                var listingInput = container.querySelector('#rc-reviews-listing-filter');
                var userInput = container.querySelector('#rc-reviews-user-filter');
                state.listingId = listingInput ? listingInput.value.trim() : '';
                state.userId = userInput ? userInput.value.trim() : '';
                state.page = 1;
                loadReviews(container);
            });
        }

        // Clear
        var clearBtn = container.querySelector('#rc-reviews-clear-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                state.listingId = '';
                state.userId = '';
                state.page = 1;
                loadReviews(container);
            });
        }

        // Enter key on inputs
        ['#rc-reviews-listing-filter', '#rc-reviews-user-filter'].forEach(function (sel) {
            var el = container.querySelector(sel);
            if (el) {
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        filterBtn && filterBtn.click();
                    }
                });
            }
        });

        // Pagination
        if (state.lastPage > 1) {
            RCUtil.renderPagination('rc-reviews-pagination', {
                total: state.total,
                page: state.page,
                perPage: state.perPage,
                onChange: function (page) {
                    state.page = page;
                    loadReviews(container);
                }
            });
        }
    }

    function loadReviews(container) {
        state.loading = true;
        state.error = null;
        renderContent(container);

        var params = { page: state.page, per_page: state.perPage };
        if (state.listingId) params.listing_id = state.listingId;
        if (state.userId) params.user_id = state.userId;

        RCApi.getReviews(params).then(function (res) {
            state.loading = false;
            state.reviews = res.data || [];
            var meta = res.meta || {};
            state.total = meta.total || 0;
            state.page = meta.page || 1;
            state.lastPage = meta.last_page || 1;
            renderContent(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load reviews';
            renderContent(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.listingId = (params && params.listing_id) || '';
            state.userId = (params && params.user_id) || '';
            state.page = 1;
            state.reviews = [];
            state.error = null;
            loadReviews(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReviewsPage;
}
