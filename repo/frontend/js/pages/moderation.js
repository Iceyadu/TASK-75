/**
 * moderation.js - Moderation Queue Page
 * Accessible only to moderators and admins. Filters, queue table, actions.
 */
var ModerationPage = (function () {
    'use strict';

    var FLAG_COLORS = {
        sensitive_word: '#FF5722',
        duplicate: '#FF9800',
        low_credibility: '#FFB800',
        rate_limit: '#1E9FFF'
    };

    var state = {
        items: [],
        type: '',
        flagReason: '',
        sort: 'newest',
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        loading: false,
        error: null,
        actionLoading: false
    };

    function hasAccess() {
        return RCAuth.isAdmin() || RCAuth.isModerator();
    }

    function credibilityColor(score) {
        if (score === null || score === undefined) return '#999';
        if (score > 0.7) return '#16b777';
        if (score >= 0.3) return '#FFB800';
        return '#FF5722';
    }

    function renderFlagBadge(reason) {
        var color = FLAG_COLORS[reason] || '#999';
        var label = reason ? reason.replace(/_/g, ' ') : 'unknown';
        return '<span style="background:' + color + ';color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;">' +
            RCUtil.escapeHtml(label) + '</span>';
    }

    function renderRow(item) {
        var typeIcon = item.item_type === 'review'
            ? '<i class="layui-icon layui-icon-praise" style="color:#1E9FFF;"></i>'
            : '<i class="layui-icon layui-icon-list" style="color:#FF9800;"></i>';

        var preview = item.content_preview ? RCUtil.truncate(item.content_preview, 100) : '(no content)';
        var userName = item.user_info ? RCUtil.escapeHtml(item.user_info.name) : 'Unknown';
        var score = item.credibility_score !== null && item.credibility_score !== undefined
            ? parseFloat(item.credibility_score).toFixed(2) : 'N/A';
        var scoreColor = credibilityColor(item.credibility_score);

        return '<tr data-item-id="' + item.id + '">' +
            '<td style="width:40px;text-align:center;">' + typeIcon + '</td>' +
            '<td style="max-width:250px;"><a href="#/moderation/' + item.id + '" style="color:#333;">' +
            RCUtil.escapeHtml(preview) + '</a></td>' +
            '<td>' + renderFlagBadge(item.flag_reason) + '</td>' +
            '<td style="text-align:center;"><span style="color:' + scoreColor + ';font-weight:600;">' + score + '</span></td>' +
            '<td>' + userName + '</td>' +
            '<td style="font-size:12px;color:#999;">' + (item.created_at ? RCUtil.formatRelative(item.created_at) : '') + '</td>' +
            '<td style="white-space:nowrap;">' +
            '<button type="button" class="layui-btn layui-btn-xs rc-mod-action" data-action="approve" data-id="' + item.id + '" style="background:#16b777;">Approve</button>' +
            '<button type="button" class="layui-btn layui-btn-xs layui-btn-danger rc-mod-action" data-action="reject" data-id="' + item.id + '">Reject</button>' +
            '<button type="button" class="layui-btn layui-btn-xs layui-btn-warm rc-mod-action" data-action="escalate" data-id="' + item.id + '">Escalate</button>' +
            '</td></tr>';
    }

    function renderContent(container) {
        if (!hasAccess()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">You do not have permission to view the moderation queue.</p></div>';
            return;
        }

        var html = '<div style="max-width:1100px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">Moderation Queue</h2>';

        // Filter bar
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">' +
            '<select id="rc-mod-type-filter" lay-ignore style="height:36px;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;">' +
            '<option value=""' + (state.type === '' ? ' selected' : '') + '>All Types</option>' +
            '<option value="review"' + (state.type === 'review' ? ' selected' : '') + '>Reviews</option>' +
            '<option value="listing"' + (state.type === 'listing' ? ' selected' : '') + '>Listings</option>' +
            '</select>' +
            '<select id="rc-mod-reason-filter" lay-ignore style="height:36px;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;">' +
            '<option value=""' + (state.flagReason === '' ? ' selected' : '') + '>All Reasons</option>' +
            '<option value="sensitive_word"' + (state.flagReason === 'sensitive_word' ? ' selected' : '') + '>Sensitive Word</option>' +
            '<option value="duplicate"' + (state.flagReason === 'duplicate' ? ' selected' : '') + '>Duplicate</option>' +
            '<option value="low_credibility"' + (state.flagReason === 'low_credibility' ? ' selected' : '') + '>Low Credibility</option>' +
            '<option value="rate_limit"' + (state.flagReason === 'rate_limit' ? ' selected' : '') + '>Rate Limit</option>' +
            '</select>' +
            '<select id="rc-mod-sort" lay-ignore style="height:36px;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;">' +
            '<option value="newest"' + (state.sort === 'newest' ? ' selected' : '') + '>Newest First</option>' +
            '<option value="credibility_asc"' + (state.sort === 'credibility_asc' ? ' selected' : '') + '>Lowest Credibility</option>' +
            '</select></div>';

        // Table
        html += '<div style="overflow-x:auto;">';
        if (state.loading) {
            html += RCUtil.skeleton(5);
        } else if (state.error) {
            html += '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>';
        } else if (state.items.length === 0) {
            html += RCUtil.emptyState('The moderation queue is empty. All content has been reviewed.');
        } else {
            html += '<table class="layui-table" style="margin:0;">' +
                '<thead><tr>' +
                '<th style="width:40px;">Type</th>' +
                '<th>Content</th>' +
                '<th>Flag</th>' +
                '<th style="text-align:center;">Score</th>' +
                '<th>User</th>' +
                '<th>Flagged</th>' +
                '<th>Actions</th>' +
                '</tr></thead><tbody>';
            state.items.forEach(function (item) {
                html += renderRow(item);
            });
            html += '</tbody></table>';
        }
        html += '</div>';

        // Pagination
        html += '<div id="rc-mod-pagination" style="margin-top:16px;text-align:center;"></div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Filter changes
        ['#rc-mod-type-filter', '#rc-mod-reason-filter', '#rc-mod-sort'].forEach(function (sel) {
            var el = container.querySelector(sel);
            if (el) {
                el.addEventListener('change', function () {
                    if (sel.indexOf('type') !== -1) state.type = this.value;
                    else if (sel.indexOf('reason') !== -1) state.flagReason = this.value;
                    else state.sort = this.value;
                    state.page = 1;
                    loadQueue(container);
                });
            }
        });

        // Action buttons
        container.querySelectorAll('.rc-mod-action').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.getAttribute('data-action');
                var id = this.getAttribute('data-id');
                handleAction(action, id, container);
            });
        });

        // Pagination
        if (state.lastPage > 1) {
            RCUtil.renderPagination('rc-mod-pagination', {
                total: state.total,
                page: state.page,
                perPage: state.perPage,
                onChange: function (page) {
                    state.page = page;
                    loadQueue(container);
                }
            });
        }
    }

    function handleAction(action, id, container) {
        if (state.actionLoading) return;

        if (action === 'approve') {
            layui.layer.confirm('Approve this item?', { title: 'Confirm Approve' }, function (idx) {
                layui.layer.close(idx);
                executeAction(function () { return RCApi.approveModeration(id); }, container);
            });
        } else if (action === 'reject') {
            layui.layer.open({
                type: 1,
                title: 'Reject Item',
                area: ['400px', '250px'],
                content: '<div style="padding:20px;">' +
                    '<label style="display:block;margin-bottom:8px;font-weight:500;">Rejection Reason</label>' +
                    '<textarea id="rc-mod-reject-reason" class="layui-textarea" placeholder="Please provide a reason..." style="height:80px;"></textarea>' +
                    '</div>',
                btn: ['Reject', 'Cancel'],
                yes: function (layerIdx) {
                    var reasonEl = document.querySelector('#rc-mod-reject-reason');
                    var reason = reasonEl ? reasonEl.value.trim() : '';
                    if (!reason) {
                        layui.layer.msg('Rejection reason is required.', { icon: 0 });
                        return;
                    }
                    layui.layer.close(layerIdx);
                    executeAction(function () { return RCApi.rejectModeration(id, { reason: reason }); }, container);
                }
            });
        } else if (action === 'escalate') {
            layui.layer.confirm('Escalate this item for further review?', { title: 'Confirm Escalate' }, function (idx) {
                layui.layer.close(idx);
                executeAction(function () { return RCApi.escalateModeration(id); }, container);
            });
        }
    }

    function executeAction(apiCall, container) {
        state.actionLoading = true;
        apiCall().then(function (res) {
            state.actionLoading = false;
            layui.layer.msg(res.message || 'Success', { icon: 1 });
            loadQueue(container);
        }).catch(function (err) {
            state.actionLoading = false;
            layui.layer.msg((err && err.message) ? err.message : 'Action failed', { icon: 2 });
        });
    }

    function loadQueue(container) {
        state.loading = true;
        state.error = null;
        renderContent(container);

        var params = { page: state.page, per_page: state.perPage, sort: state.sort };
        if (state.type) params.type = state.type;
        if (state.flagReason) params.flag_reason = state.flagReason;

        RCApi.getModerationQueue(params).then(function (res) {
            state.loading = false;
            state.items = res.data || [];
            var meta = res.meta || {};
            state.total = meta.total || 0;
            state.page = meta.page || 1;
            state.lastPage = meta.last_page || 1;
            renderContent(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load moderation queue';
            renderContent(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.type = (params && params.type) || '';
            state.flagReason = (params && params.flag_reason) || '';
            state.sort = 'newest';
            state.page = 1;
            state.items = [];
            state.error = null;

            if (!hasAccess()) {
                renderContent(container);
                return;
            }
            loadQueue(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModerationPage;
}
