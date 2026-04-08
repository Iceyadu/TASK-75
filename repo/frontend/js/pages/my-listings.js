var MyListingsPage = {
    render: function(container, params) {
        var currentStatus = (params && params.status) || '';
        var currentPage = 1;
        var perPage = 15;
        var selectedIds = [];

        var isAdmin = RCAuth.isAdmin();

        container.innerHTML =
            '<div class="rc-page-header" style="display:flex;justify-content:space-between;align-items:center;">' +
            '  <h2>My Listings</h2>' +
            '  <a href="#/listings/create" class="layui-btn layui-btn-sm"><i class="layui-icon layui-icon-add-1"></i> New Listing</a>' +
            '</div>' +
            '<div class="rc-status-tabs" style="margin-bottom:15px;border-bottom:2px solid #e6e6e6;padding-bottom:0;">' +
            '  <span class="rc-status-tab' + (!currentStatus ? ' rc-tab-active' : '') + '" data-status="" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">All</span>' +
            '  <span class="rc-status-tab' + (currentStatus === 'draft' ? ' rc-tab-active' : '') + '" data-status="draft" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">Draft</span>' +
            '  <span class="rc-status-tab' + (currentStatus === 'active' ? ' rc-tab-active' : '') + '" data-status="active" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">Active</span>' +
            '  <span class="rc-status-tab' + (currentStatus === 'matched' ? ' rc-tab-active' : '') + '" data-status="matched" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">Matched</span>' +
            '  <span class="rc-status-tab' + (currentStatus === 'in_progress' ? ' rc-tab-active' : '') + '" data-status="in_progress" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">In Progress</span>' +
            '  <span class="rc-status-tab' + (currentStatus === 'completed' ? ' rc-tab-active' : '') + '" data-status="completed" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">Completed</span>' +
            '  <span class="rc-status-tab' + (currentStatus === 'canceled' ? ' rc-tab-active' : '') + '" data-status="canceled" style="cursor:pointer;display:inline-block;padding:8px 16px;margin-right:2px;border-bottom:2px solid transparent;">Canceled</span>' +
            '</div>' +
            (isAdmin ? '<div id="bulk-actions" style="margin-bottom:10px;display:none;">' +
            '  <button class="layui-btn layui-btn-sm layui-btn-danger" id="btn-bulk-close"><i class="layui-icon layui-icon-close"></i> Close Selected (<span id="bulk-count">0</span>)</button>' +
            '</div>' : '') +
            '<div id="my-listings-table"></div>' +
            '<div id="my-listings-pagination" class="rc-pagination" style="margin-top:20px;text-align:center;"></div>';

        // Tab click handlers
        var tabEls = container.querySelectorAll('.rc-status-tab');
        for (var t = 0; t < tabEls.length; t++) {
            tabEls[t].addEventListener('click', function() {
                for (var j = 0; j < tabEls.length; j++) {
                    tabEls[j].classList.remove('rc-tab-active');
                    tabEls[j].style.borderBottomColor = 'transparent';
                    tabEls[j].style.color = '';
                }
                this.classList.add('rc-tab-active');
                this.style.borderBottomColor = '#009688';
                this.style.color = '#009688';
                currentStatus = this.getAttribute('data-status');
                currentPage = 1;
                selectedIds = [];
                updateBulkUI();
                loadListings();
            });
        }

        // Set active tab style
        var activeTabEl = container.querySelector('.rc-tab-active');
        if (activeTabEl) {
            activeTabEl.style.borderBottomColor = '#009688';
            activeTabEl.style.color = '#009688';
        }

        // Bulk close handler (admin)
        if (isAdmin) {
            document.getElementById('btn-bulk-close').addEventListener('click', function() {
                if (selectedIds.length === 0) {
                    layui.layer.msg('No listings selected', { icon: 0 });
                    return;
                }
                layui.layer.prompt({
                    title: 'Reason for closing ' + selectedIds.length + ' listing(s)',
                    formType: 2,
                    value: 'Closed by admin'
                }, function(reason, promptIdx) {
                    layui.layer.close(promptIdx);
                    RCApi.bulkClose(selectedIds, reason).then(function() {
                        layui.layer.msg(selectedIds.length + ' listing(s) closed', { icon: 1 });
                        selectedIds = [];
                        updateBulkUI();
                        loadListings();
                    }).catch(function(err) {
                        layui.layer.msg(err.message || 'Failed to close listings', { icon: 2 });
                    });
                });
            });
        }

        function updateBulkUI() {
            if (!isAdmin) return;
            var bulkDiv = document.getElementById('bulk-actions');
            var countSpan = document.getElementById('bulk-count');
            if (selectedIds.length > 0) {
                bulkDiv.style.display = 'block';
                countSpan.textContent = selectedIds.length;
            } else {
                bulkDiv.style.display = 'none';
            }
        }

        function loadListings() {
            var tableDiv = document.getElementById('my-listings-table');
            tableDiv.innerHTML = RCUtil.skeleton(3);

            var apiParams = { page: currentPage, per_page: perPage, mine: true };
            if (currentStatus) apiParams.status = currentStatus;

            RCApi.getListings(apiParams).then(function(envelope) {
                var data = envelope.data || envelope;
                var meta = envelope.meta || data.meta || {};
                if (!data.listings || data.listings.length === 0) {
                    tableDiv.innerHTML = RCUtil.emptyState(
                        'You have no listings' + (currentStatus ? ' with status "' + currentStatus.replace('_', ' ') + '"' : '') + '.',
                        'Create Your First Listing', '#/listings/create'
                    );
                    document.getElementById('my-listings-pagination').innerHTML = '';
                    return;
                }

                var checkAll = isAdmin ? '<th style="width:40px;"><input type="checkbox" id="check-all" lay-skin="primary"></th>' : '';
                var html =
                    '<table class="layui-table">' +
                    '<thead><tr>' + checkAll +
                    '<th>Title</th><th style="width:100px;">Status</th><th style="width:130px;">Created</th><th style="width:130px;">Updated</th><th style="width:150px;">Actions</th>' +
                    '</tr></thead><tbody>';

                data.listings.forEach(function(l) {
                    var checkCell = isAdmin ? '<td><input type="checkbox" class="row-check" data-id="' + l.id + '" lay-skin="primary"></td>' : '';
                    var actions = '';
                    if (l.status === 'draft') {
                        actions =
                            '<a href="#/listings/' + l.id + '/edit" class="layui-btn layui-btn-xs"><i class="layui-icon layui-icon-edit"></i> Edit</a>' +
                            '<button class="layui-btn layui-btn-xs layui-btn-danger btn-delete-listing" data-id="' + l.id + '"><i class="layui-icon layui-icon-delete"></i> Delete</button>';
                    } else if (l.status === 'active') {
                        actions = '<a href="#/listings/' + l.id + '/edit" class="layui-btn layui-btn-xs"><i class="layui-icon layui-icon-edit"></i> Edit</a>';
                    }
                    actions += '<a href="#/listings/' + l.id + '" class="layui-btn layui-btn-xs layui-btn-primary">View</a>';

                    html += '<tr>' + checkCell +
                        '<td><a href="#/listings/' + l.id + '" style="color:#333;">' + RCUtil.escapeHtml(RCUtil.truncate(l.title, 50)) + '</a></td>' +
                        '<td>' + RCUtil.statusBadge(l.status) + '</td>' +
                        '<td>' + RCUtil.formatDate(l.created_at) + '</td>' +
                        '<td>' + RCUtil.formatDate(l.updated_at) + '</td>' +
                        '<td>' + actions + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table>';
                tableDiv.innerHTML = html;

                // Bind delete buttons
                var deleteBtns = tableDiv.querySelectorAll('.btn-delete-listing');
                for (var d = 0; d < deleteBtns.length; d++) {
                    deleteBtns[d].addEventListener('click', function() {
                        var deleteId = this.getAttribute('data-id');
                        layui.layer.confirm('Are you sure you want to delete this draft listing?', {
                            title: 'Delete Listing', btn: ['Delete', 'Cancel']
                        }, function(idx) {
                            layui.layer.close(idx);
                            RCApi.deleteListing(deleteId).then(function() {
                                layui.layer.msg('Listing deleted', { icon: 1 });
                                loadListings();
                            }).catch(function(err) {
                                layui.layer.msg(err.message || 'Failed to delete', { icon: 2 });
                            });
                        });
                    });
                }

                // Bind checkboxes (admin)
                if (isAdmin) {
                    var checkAllEl = document.getElementById('check-all');
                    if (checkAllEl) {
                        checkAllEl.addEventListener('change', function() {
                            var checked = this.checked;
                            var rowChecks = tableDiv.querySelectorAll('.row-check');
                            selectedIds = [];
                            for (var rc = 0; rc < rowChecks.length; rc++) {
                                rowChecks[rc].checked = checked;
                                if (checked) selectedIds.push(rowChecks[rc].getAttribute('data-id'));
                            }
                            updateBulkUI();
                        });
                    }
                    var rowChecks = tableDiv.querySelectorAll('.row-check');
                    for (var rc = 0; rc < rowChecks.length; rc++) {
                        rowChecks[rc].addEventListener('change', function() {
                            var id = this.getAttribute('data-id');
                            if (this.checked) {
                                if (selectedIds.indexOf(id) === -1) selectedIds.push(id);
                            } else {
                                var idx = selectedIds.indexOf(id);
                                if (idx > -1) selectedIds.splice(idx, 1);
                            }
                            updateBulkUI();
                        });
                    }
                }

                // Pagination
                RCUtil.renderPagination('my-listings-pagination', meta.total, meta.page, meta.per_page, function(page) {
                    currentPage = page;
                    selectedIds = [];
                    updateBulkUI();
                    loadListings();
                    window.scrollTo(0, 0);
                });
            }).catch(function(err) {
                tableDiv.innerHTML =
                    '<div class="rc-empty-state rc-error-state" style="text-align:center;padding:40px;color:#FF5722;">' +
                    '<p>' + RCUtil.escapeHtml(err.message || 'Failed to load listings') + '</p>' +
                    '<button class="layui-btn layui-btn-sm" id="retry-my-listings">Retry</button></div>';
                document.getElementById('my-listings-pagination').innerHTML = '';
                document.getElementById('retry-my-listings').addEventListener('click', function() {
                    loadListings();
                });
            });
        }

        // Init
        loadListings();
    }
};
