var ListingsPage = {
    render: function(container, params) {
        var currentPage = 1;
        var currentSort = 'newest';
        var currentFilters = { status: 'active' };
        var currentQuery = '';
        var debounceTimer = null;

        container.innerHTML =
            '<div class="rc-page-header"><h2>Browse Listings</h2></div>' +
            '<div class="rc-search-section">' +
            '  <div class="rc-search-bar" style="position:relative;">' +
            '    <div class="layui-input-group">' +
            '      <input type="text" id="search-input" class="layui-input" placeholder="Search rides by keyword..." autocomplete="off">' +
            '      <button class="layui-btn" id="search-btn"><i class="layui-icon layui-icon-search"></i></button>' +
            '    </div>' +
            '    <div id="search-suggestions" class="rc-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:999;background:#fff;border:1px solid #e6e6e6;border-top:none;max-height:240px;overflow-y:auto;"></div>' +
            '  </div>' +
            '  <div id="search-history" class="rc-search-history" style="margin-top:8px;"></div>' +
            '  <div id="did-you-mean" class="rc-did-you-mean" style="display:none;margin-top:8px;color:#666;font-size:14px;"></div>' +
            '</div>' +
            '<div class="layui-row layui-col-space15" style="margin-top:15px;">' +
            '  <div class="layui-col-md3">' +
            '    <div class="layui-card rc-filter-panel">' +
            '      <div class="layui-card-header"><i class="layui-icon layui-icon-set-fill"></i> Filters</div>' +
            '      <div class="layui-card-body">' +
            '        <div class="rc-filter-group" style="margin-bottom:15px;">' +
            '          <label class="rc-filter-label" style="font-weight:bold;display:block;margin-bottom:6px;">Vehicle Type</label>' +
            '          <div><input type="checkbox" name="vtype" value="sedan" lay-skin="primary" title="Sedan"></div>' +
            '          <div><input type="checkbox" name="vtype" value="suv" lay-skin="primary" title="SUV"></div>' +
            '          <div><input type="checkbox" name="vtype" value="van" lay-skin="primary" title="Van"></div>' +
            '        </div>' +
            '        <div class="rc-filter-group" style="margin-bottom:15px;">' +
            '          <label class="rc-filter-label" style="font-weight:bold;display:block;margin-bottom:6px;">Rider Count</label>' +
            '          <div class="layui-row layui-col-space5">' +
            '            <div class="layui-col-xs6"><input type="number" id="rider-min" class="layui-input" placeholder="Min" min="1" max="6" value="1"></div>' +
            '            <div class="layui-col-xs6"><input type="number" id="rider-max" class="layui-input" placeholder="Max" min="1" max="6" value="6"></div>' +
            '          </div>' +
            '        </div>' +
            '        <div class="rc-filter-group" style="margin-bottom:15px;">' +
            '          <label class="rc-filter-label" style="font-weight:bold;display:block;margin-bottom:6px;">Status</label>' +
            '          <select id="filter-status" class="layui-select">' +
            '            <option value="">All</option>' +
            '            <option value="active" selected>Active</option>' +
            '            <option value="matched">Matched</option>' +
            '            <option value="in_progress">In Progress</option>' +
            '            <option value="completed">Completed</option>' +
            '          </select>' +
            '        </div>' +
            '        <button class="layui-btn layui-btn-sm layui-btn-fluid" id="apply-filters">Apply Filters</button>' +
            '        <button class="layui-btn layui-btn-sm layui-btn-primary layui-btn-fluid" id="clear-filters" style="margin-top:6px;">Clear Filters</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="layui-col-md9">' +
            '    <div class="rc-sort-tabs" style="margin-bottom:15px;border-bottom:1px solid #e6e6e6;padding-bottom:10px;">' +
            '      <span class="rc-sort-tab rc-sort-active" data-sort="newest" style="cursor:pointer;padding:6px 16px;margin-right:5px;border-radius:3px;">Newest</span>' +
            '      <span class="rc-sort-tab" data-sort="most_discussed" style="cursor:pointer;padding:6px 16px;margin-right:5px;border-radius:3px;">Most Discussed</span>' +
            '      <span class="rc-sort-tab" data-sort="most_popular" style="cursor:pointer;padding:6px 16px;margin-right:5px;border-radius:3px;">Most Popular</span>' +
            '    </div>' +
            '    <div id="listings-results"></div>' +
            '    <div id="listings-pagination" class="rc-pagination" style="margin-top:20px;text-align:center;"></div>' +
            '  </div>' +
            '</div>';

        layui.form.render();

        var searchInput = document.getElementById('search-input');
        var suggestionsDiv = document.getElementById('search-suggestions');

        // Search suggestions with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var q = this.value.trim();
            if (q.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            debounceTimer = setTimeout(function() {
                RCApi.getSuggestions(q).then(function(envelope) {
                    var suggestions = (envelope.data && envelope.data.suggestions) || [];
                    if (suggestions.length) {
                        suggestionsDiv.innerHTML = suggestions.map(function(s) {
                            return '<div class="rc-suggestion-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;">' + RCUtil.escapeHtml(s) + '</div>';
                        }).join('');
                        suggestionsDiv.style.display = 'block';
                        var items = suggestionsDiv.querySelectorAll('.rc-suggestion-item');
                        for (var i = 0; i < items.length; i++) {
                            items[i].addEventListener('click', function() {
                                searchInput.value = this.textContent;
                                suggestionsDiv.style.display = 'none';
                                doSearch();
                            });
                            items[i].addEventListener('mouseenter', function() {
                                this.style.backgroundColor = '#f2f2f2';
                            });
                            items[i].addEventListener('mouseleave', function() {
                                this.style.backgroundColor = '';
                            });
                        }
                    } else {
                        suggestionsDiv.style.display = 'none';
                    }
                }).catch(function() {
                    suggestionsDiv.style.display = 'none';
                });
            }, 300);
        });

        // Hide suggestions on outside click
        document.addEventListener('click', function(e) {
            if (!suggestionsDiv.contains(e.target) && e.target !== searchInput) {
                suggestionsDiv.style.display = 'none';
            }
        });

        // Search on Enter
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                suggestionsDiv.style.display = 'none';
                doSearch();
            }
        });

        // Search button
        document.getElementById('search-btn').addEventListener('click', function() {
            suggestionsDiv.style.display = 'none';
            doSearch();
        });

        // Sort tabs
        var sortTabs = container.querySelectorAll('.rc-sort-tab');
        for (var i = 0; i < sortTabs.length; i++) {
            sortTabs[i].addEventListener('click', function() {
                for (var j = 0; j < sortTabs.length; j++) {
                    sortTabs[j].classList.remove('rc-sort-active');
                    sortTabs[j].style.backgroundColor = '';
                    sortTabs[j].style.color = '';
                }
                this.classList.add('rc-sort-active');
                this.style.backgroundColor = '#009688';
                this.style.color = '#fff';
                currentSort = this.getAttribute('data-sort');
                currentPage = 1;
                loadListings();
            });
        }

        // Set initial active tab style
        var activeTab = container.querySelector('.rc-sort-active');
        if (activeTab) {
            activeTab.style.backgroundColor = '#009688';
            activeTab.style.color = '#fff';
        }

        // Apply filters
        document.getElementById('apply-filters').addEventListener('click', function() {
            gatherFilters();
            currentPage = 1;
            loadListings();
        });

        // Clear filters
        document.getElementById('clear-filters').addEventListener('click', function() {
            var checkboxes = container.querySelectorAll('input[name="vtype"]');
            for (var c = 0; c < checkboxes.length; c++) {
                checkboxes[c].checked = false;
            }
            document.getElementById('rider-min').value = '1';
            document.getElementById('rider-max').value = '6';
            document.getElementById('filter-status').value = '';
            layui.form.render();
            currentFilters = {};
            currentPage = 1;
            loadListings();
        });

        function gatherFilters() {
            currentFilters = {};
            var checkedTypes = [];
            var checkboxes = container.querySelectorAll('input[name="vtype"]:checked');
            for (var c = 0; c < checkboxes.length; c++) {
                checkedTypes.push(checkboxes[c].value);
            }
            if (checkedTypes.length) {
                currentFilters.vehicle_type = checkedTypes.join(',');
            }
            var riderMin = parseInt(document.getElementById('rider-min').value, 10);
            var riderMax = parseInt(document.getElementById('rider-max').value, 10);
            if (riderMin && riderMin > 1) currentFilters.rider_count_min = riderMin;
            if (riderMax && riderMax < 6) currentFilters.rider_count_max = riderMax;
            var statusVal = document.getElementById('filter-status').value;
            if (statusVal) currentFilters.status = statusVal;
        }

        function renderSearchHistory() {
            var historyDiv = document.getElementById('search-history');
            var history = RCUtil.getSearchHistory();
            if (!history || !history.length) {
                historyDiv.innerHTML = '';
                return;
            }
            var chips = history.slice(0, 8).map(function(term) {
                return '<span class="rc-history-chip" style="display:inline-block;padding:3px 10px;margin:3px;background:#f0f0f0;border-radius:12px;font-size:12px;cursor:pointer;color:#666;">' +
                    RCUtil.escapeHtml(term) + '</span>';
            }).join('');
            historyDiv.innerHTML = '<span style="font-size:12px;color:#999;margin-right:6px;">Recent:</span>' + chips;
            var chipEls = historyDiv.querySelectorAll('.rc-history-chip');
            for (var h = 0; h < chipEls.length; h++) {
                chipEls[h].addEventListener('click', function() {
                    searchInput.value = this.textContent;
                    doSearch();
                });
            }
        }

        function doSearch() {
            currentQuery = searchInput.value.trim();
            if (currentQuery) {
                RCUtil.addSearchHistory(currentQuery);
                renderSearchHistory();
            }
            currentPage = 1;
            loadListings();
        }

        function loadListings() {
            var resultsDiv = document.getElementById('listings-results');
            resultsDiv.innerHTML = RCUtil.skeleton(4);

            var apiParams = { page: currentPage, per_page: 12, sort: currentSort };
            if (currentQuery) apiParams.q = currentQuery;
            if (currentFilters.vehicle_type) apiParams.vehicle_type = currentFilters.vehicle_type;
            if (currentFilters.rider_count_min) apiParams.rider_count_min = currentFilters.rider_count_min;
            if (currentFilters.rider_count_max) apiParams.rider_count_max = currentFilters.rider_count_max;
            if (currentFilters.status) apiParams.status = currentFilters.status;

            RCApi.getListings(apiParams).then(function(envelope) {
                var data = envelope.data || {};
                var meta = envelope.meta || {};
                var dymDiv = document.getElementById('did-you-mean');

                if (!data.listings || data.listings.length === 0) {
                    // Did you mean
                    if (currentQuery && data.did_you_mean) {
                        var dymText = typeof data.did_you_mean === 'object' ? data.did_you_mean.suggestion : data.did_you_mean;
                        dymDiv.innerHTML = 'Did you mean: <a href="javascript:;" class="rc-dym-link" style="color:#009688;text-decoration:underline;">' +
                            RCUtil.escapeHtml(dymText) + '</a>?';
                        dymDiv.style.display = 'block';
                        dymDiv.querySelector('.rc-dym-link').addEventListener('click', function() {
                            searchInput.value = dymText;
                            doSearch();
                        });
                    } else {
                        dymDiv.style.display = 'none';
                    }

                    // No results fallback
                    if (currentQuery && data.recent_active && data.recent_active.length) {
                        resultsDiv.innerHTML =
                            '<div class="rc-empty-state" style="text-align:center;padding:30px 0;color:#999;">' +
                            '<i class="layui-icon layui-icon-search" style="font-size:48px;display:block;margin-bottom:12px;"></i>' +
                            '<p>No rides found for "<strong>' + RCUtil.escapeHtml(currentQuery) +
                            '</strong>". Here are some recently active listings:</p></div>' +
                            renderListingCards(data.recent_active, false);
                    } else {
                        resultsDiv.innerHTML = RCUtil.emptyState(
                            'No trip requests yet. Be the first to post one!',
                            'Post a Trip Request', '#/listings/create'
                        );
                    }
                    document.getElementById('listings-pagination').innerHTML = '';
                    return;
                }

                dymDiv.style.display = 'none';

                // Check for did_you_mean even with results
                if (currentQuery && data.did_you_mean) {
                    var dymText2 = typeof data.did_you_mean === 'object' ? data.did_you_mean.suggestion : data.did_you_mean;
                    dymDiv.innerHTML = 'Did you mean: <a href="javascript:;" class="rc-dym-link" style="color:#009688;text-decoration:underline;">' +
                        RCUtil.escapeHtml(dymText2) + '</a>?';
                    dymDiv.style.display = 'block';
                    dymDiv.querySelector('.rc-dym-link').addEventListener('click', function() {
                        searchInput.value = dymText2;
                        doSearch();
                    });
                }

                resultsDiv.innerHTML =
                    '<div class="rc-results-count" style="margin-bottom:12px;color:#666;font-size:13px;">' +
                    '<strong>' + meta.total + '</strong> result' + (meta.total !== 1 ? 's' : '') +
                    (currentQuery ? ' for "<em>' + RCUtil.escapeHtml(currentQuery) + '</em>"' : '') +
                    '</div>' +
                    renderListingCards(data.listings, !!currentQuery);

                RCUtil.renderPagination('listings-pagination', meta.total, meta.page, meta.per_page, function(page) {
                    currentPage = page;
                    loadListings();
                    window.scrollTo(0, 0);
                });
            }).catch(function(err) {
                resultsDiv.innerHTML =
                    '<div class="rc-empty-state rc-error-state" style="text-align:center;padding:40px 0;color:#FF5722;">' +
                    '<i class="layui-icon layui-icon-face-cry" style="font-size:48px;display:block;margin-bottom:12px;"></i>' +
                    '<p>Failed to load listings: ' + RCUtil.escapeHtml(err.message || 'Unknown error') + '</p>' +
                    '<button class="layui-btn layui-btn-sm" id="retry-btn">Retry</button></div>';
                document.getElementById('listings-pagination').innerHTML = '';
                document.getElementById('retry-btn').addEventListener('click', function() {
                    loadListings();
                });
            });
        }

        function renderListingCards(listings, showHighlights) {
            return '<div class="rc-listing-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:15px;">' +
                listings.map(function(l) {
                    var title = showHighlights && l.highlight && l.highlight.title
                        ? l.highlight.title
                        : RCUtil.escapeHtml(l.title);
                    var desc = showHighlights && l.highlight && l.highlight.description
                        ? l.highlight.description
                        : RCUtil.escapeHtml(RCUtil.truncate(l.description || '', 120));
                    var tags = (l.tags || []).map(function(t) {
                        return '<span class="rc-tag-chip" style="display:inline-block;padding:2px 8px;margin:2px;background:#e8f5e9;color:#388e3c;border-radius:10px;font-size:11px;">' +
                            RCUtil.escapeHtml(t) + '</span>';
                    }).join('');
                    var vehicleLabel = (l.vehicle_type || '').charAt(0).toUpperCase() + (l.vehicle_type || '').slice(1);

                    return '<div class="rc-listing-card layui-card" style="cursor:pointer;" data-id="' + l.id + '">' +
                        '<div class="layui-card-header" style="display:flex;justify-content:space-between;align-items:center;">' +
                        '  <a href="#/listings/' + l.id + '" class="rc-listing-title" style="color:#333;font-weight:bold;text-decoration:none;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + title + '</a>' +
                        '  ' + RCUtil.statusBadge(l.status) +
                        '</div>' +
                        '<div class="layui-card-body" style="font-size:13px;">' +
                        '  <div class="rc-listing-meta" style="margin-bottom:8px;">' +
                        '    <div style="margin-bottom:4px;"><i class="layui-icon layui-icon-location" style="color:#009688;margin-right:4px;"></i><strong>Pickup:</strong> ' + RCUtil.escapeHtml(RCUtil.truncate(l.pickup_address || '', 40)) + '</div>' +
                        '    <div><i class="layui-icon layui-icon-location" style="color:#FF5722;margin-right:4px;"></i><strong>Drop-off:</strong> ' + RCUtil.escapeHtml(RCUtil.truncate(l.dropoff_address || '', 40)) + '</div>' +
                        '  </div>' +
                        '  <div class="rc-listing-details" style="margin-bottom:8px;display:flex;gap:12px;flex-wrap:wrap;color:#666;">' +
                        '    <span class="rc-detail-item"><i class="layui-icon layui-icon-group"></i> ' + l.rider_count + ' rider(s)</span>' +
                        '    <span class="rc-detail-item"><i class="layui-icon layui-icon-car"></i> ' + RCUtil.escapeHtml(vehicleLabel) + '</span>' +
                        '    <span class="rc-detail-item"><i class="layui-icon layui-icon-time"></i> ' + RCUtil.formatTime12h(l.time_window_start) + ' - ' + RCUtil.formatTime12h(l.time_window_end) + '</span>' +
                        '  </div>' +
                        '  <div class="rc-listing-desc" style="color:#555;margin-bottom:8px;line-height:1.5;">' + desc + '</div>' +
                        '  <div class="rc-listing-tags" style="margin-bottom:8px;">' + tags + '</div>' +
                        '  <div class="rc-listing-footer" style="display:flex;justify-content:space-between;align-items:center;color:#999;font-size:12px;border-top:1px solid #f0f0f0;padding-top:8px;">' +
                        '    <span>by ' + RCUtil.escapeHtml(l.user ? l.user.name : 'Unknown') + ' &middot; ' + RCUtil.formatDate(l.created_at) + '</span>' +
                        '    <span>' + (l.view_count || 0) + ' views &middot; ' + (l.favorite_count || 0) + ' favs</span>' +
                        '  </div>' +
                        '</div></div>';
                }).join('') + '</div>';
        }

        // Apply initial URL params if present
        if (params && params.q) {
            searchInput.value = params.q;
            currentQuery = params.q;
        }
        if (params && params.status) {
            currentFilters.status = params.status;
            document.getElementById('filter-status').value = params.status;
            layui.form.render('select');
        }

        // Init
        renderSearchHistory();
        gatherFilters();
        loadListings();
    }
};
