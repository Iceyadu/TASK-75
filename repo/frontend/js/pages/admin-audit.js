/**
 * admin-audit.js - Audit Logs Page
 * Admin only (audit.read). Filter panel, PII toggle, log table with expandable JSON diff.
 */
var AdminAuditPage = (function () {
    'use strict';

    var ACTION_TYPES = [
        { value: '', label: 'All Actions' },
        { value: 'user.login', label: 'User Login' },
        { value: 'user.update_roles', label: 'Role Update' },
        { value: 'user.disable', label: 'User Disable' },
        { value: 'user.enable', label: 'User Enable' },
        { value: 'org.update', label: 'Org Update' },
        { value: 'order.create', label: 'Order Create' },
        { value: 'order.accept', label: 'Order Accept' },
        { value: 'order.start', label: 'Order Start' },
        { value: 'order.complete', label: 'Order Complete' },
        { value: 'order.cancel', label: 'Order Cancel' },
        { value: 'order.dispute', label: 'Order Dispute' },
        { value: 'order.resolve', label: 'Order Resolve' },
        { value: 'review.create', label: 'Review Create' },
        { value: 'review.update', label: 'Review Update' },
        { value: 'review.delete', label: 'Review Delete' },
        { value: 'moderation.approve', label: 'Mod Approve' },
        { value: 'moderation.reject', label: 'Mod Reject' },
        { value: 'moderation.escalate', label: 'Mod Escalate' }
    ];

    var RESOURCE_TYPES = [
        { value: '', label: 'All Resources' },
        { value: 'user', label: 'User' },
        { value: 'organization', label: 'Organization' },
        { value: 'order', label: 'Order' },
        { value: 'review', label: 'Review' },
        { value: 'listing', label: 'Listing' },
        { value: 'moderation', label: 'Moderation' }
    ];

    var state = {
        logs: [],
        userId: '',
        action: '',
        resourceType: '',
        fromDate: '',
        toDate: '',
        unmask: false,
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        loading: false,
        error: null,
        expandedRows: {}
    };

    function hasReadUnmasked() {
        var user = RCAuth.getUser();
        if (!user) return false;
        if (typeof user.hasPermission === 'function') {
            return user.hasPermission('audit', 'read_unmasked');
        }
        // Fallback: check permissions array
        if (user.permissions && Array.isArray(user.permissions)) {
            return user.permissions.some(function (p) {
                return p === 'audit.read_unmasked' || (p.resource === 'audit' && p.action === 'read_unmasked');
            });
        }
        return RCAuth.isAdmin();
    }

    function defaultFromDate() {
        var d = new Date();
        d.setDate(d.getDate() - 30);
        return d.toISOString().split('T')[0];
    }

    function defaultToDate() {
        return new Date().toISOString().split('T')[0];
    }

    function formatJsonDiff(oldVal, newVal) {
        var oldStr, newStr;
        try {
            oldStr = oldVal ? JSON.stringify(oldVal, null, 2) : '(empty)';
        } catch (e) { oldStr = String(oldVal || '(empty)'); }
        try {
            newStr = newVal ? JSON.stringify(newVal, null, 2) : '(empty)';
        } catch (e) { newStr = String(newVal || '(empty)'); }

        var html = '<div style="display:flex;gap:16px;flex-wrap:wrap;">';

        // Old value
        html += '<div style="flex:1;min-width:250px;">' +
            '<div style="font-size:12px;font-weight:600;color:#c62828;margin-bottom:4px;">Old Value</div>' +
            '<pre style="background:#ffebee;border:1px solid #ef9a9a;border-radius:4px;padding:10px;font-size:12px;' +
            'overflow-x:auto;max-height:300px;white-space:pre-wrap;word-break:break-all;">' +
            RCUtil.escapeHtml(oldStr) + '</pre></div>';

        // New value
        html += '<div style="flex:1;min-width:250px;">' +
            '<div style="font-size:12px;font-weight:600;color:#2e7d32;margin-bottom:4px;">New Value</div>' +
            '<pre style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:4px;padding:10px;font-size:12px;' +
            'overflow-x:auto;max-height:300px;white-space:pre-wrap;word-break:break-all;">' +
            RCUtil.escapeHtml(newStr) + '</pre></div>';

        html += '</div>';
        return html;
    }

    function renderRow(log) {
        var expanded = !!state.expandedRows[log.id];
        var chevron = expanded ? 'layui-icon-down' : 'layui-icon-right';
        var hasChanges = log.old_value || log.new_value;

        var html = '<tr class="rc-audit-row' + (hasChanges ? ' rc-expandable' : '') + '" data-log-id="' + log.id + '" ' +
            (hasChanges ? 'style="cursor:pointer;"' : '') + '>' +
            '<td style="font-size:12px;white-space:nowrap;">';
        if (hasChanges) {
            html += '<i class="layui-icon ' + chevron + '" style="font-size:12px;margin-right:4px;"></i>';
        }
        html += (log.created_at ? RCUtil.formatDateTime(log.created_at) : '') + '</td>' +
            '<td>' + RCUtil.escapeHtml(log.user_name || log.user_id || '') + '</td>' +
            '<td><span style="font-size:12px;">' + RCUtil.escapeHtml(log.action || '') + '</span></td>' +
            '<td>' + RCUtil.escapeHtml(log.resource_type || '') + '</td>' +
            '<td style="font-size:12px;">' + (log.resource_id || '-') + '</td>' +
            '<td style="font-size:12px;color:#999;">' + RCUtil.escapeHtml(log.ip_address || '') + '</td>' +
            '</tr>';

        if (expanded && hasChanges) {
            html += '<tr class="rc-audit-detail"><td colspan="6" style="padding:12px 16px;background:#fafafa;">' +
                formatJsonDiff(log.old_value, log.new_value) + '</td></tr>';
        }

        return html;
    }

    function renderContent(container) {
        if (!RCAuth.isAdmin()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">Only administrators can view audit logs.</p></div>';
            return;
        }

        var html = '<div style="max-width:1200px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">Audit Logs</h2>';

        // Filter panel
        html += '<div class="layui-card" style="margin-bottom:20px;"><div class="layui-card-body" style="padding:16px;">' +
            '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">' +
            '<input type="text" id="rc-audit-user" class="layui-input" placeholder="User ID or name" ' +
            'value="' + RCUtil.escapeHtml(state.userId) + '" style="width:160px;height:36px;">' +
            '<select id="rc-audit-action" lay-ignore style="height:36px;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;">' +
            ACTION_TYPES.map(function (a) {
                return '<option value="' + a.value + '"' + (a.value === state.action ? ' selected' : '') + '>' +
                    RCUtil.escapeHtml(a.label) + '</option>';
            }).join('') +
            '</select>' +
            '<select id="rc-audit-resource" lay-ignore style="height:36px;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;">' +
            RESOURCE_TYPES.map(function (r) {
                return '<option value="' + r.value + '"' + (r.value === state.resourceType ? ' selected' : '') + '>' +
                    RCUtil.escapeHtml(r.label) + '</option>';
            }).join('') +
            '</select>' +
            '<input type="date" id="rc-audit-from" class="layui-input" value="' + RCUtil.escapeHtml(state.fromDate) + '" style="width:150px;height:36px;">' +
            '<input type="date" id="rc-audit-to" class="layui-input" value="' + RCUtil.escapeHtml(state.toDate) + '" style="width:150px;height:36px;">' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="rc-audit-filter-btn">Filter</button>' +
            '</div>';

        // PII toggle
        if (hasReadUnmasked()) {
            html += '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;">' +
                '<label style="cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;">' +
                '<input type="checkbox" id="rc-audit-unmask"' + (state.unmask ? ' checked' : '') + ' lay-ignore>' +
                '<span>Show unmasked data</span>' +
                '</label></div>';
        }

        html += '</div></div>';

        // Table
        html += '<div style="overflow-x:auto;">';
        if (state.loading) {
            html += RCUtil.skeleton(5);
        } else if (state.error) {
            html += '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>';
        } else if (state.logs.length === 0) {
            html += RCUtil.emptyState('No audit log entries found for the selected filters.');
        } else {
            html += '<table class="layui-table" style="margin:0;">' +
                '<thead><tr>' +
                '<th>Timestamp</th>' +
                '<th>User</th>' +
                '<th>Action</th>' +
                '<th>Resource</th>' +
                '<th>ID</th>' +
                '<th>IP</th>' +
                '</tr></thead><tbody>';
            state.logs.forEach(function (log) {
                html += renderRow(log);
            });
            html += '</tbody></table>';
        }
        html += '</div>';

        // Pagination
        html += '<div id="rc-audit-pagination" style="margin-top:16px;text-align:center;"></div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Filter button
        var filterBtn = container.querySelector('#rc-audit-filter-btn');
        if (filterBtn) {
            filterBtn.addEventListener('click', function () {
                readFilterState(container);
                state.page = 1;
                state.expandedRows = {};
                loadLogs(container);
            });
        }

        // Unmask toggle
        var unmaskEl = container.querySelector('#rc-audit-unmask');
        if (unmaskEl) {
            unmaskEl.addEventListener('change', function () {
                state.unmask = this.checked;
                state.page = 1;
                state.expandedRows = {};
                loadLogs(container);
            });
        }

        // Expandable rows
        container.querySelectorAll('.rc-expandable').forEach(function (row) {
            row.addEventListener('click', function () {
                var logId = this.getAttribute('data-log-id');
                if (state.expandedRows[logId]) {
                    delete state.expandedRows[logId];
                } else {
                    state.expandedRows[logId] = true;
                }
                renderContent(container);
                bindEvents(container);
            });
        });

        // Pagination
        if (state.lastPage > 1) {
            RCUtil.renderPagination('rc-audit-pagination', {
                total: state.total,
                page: state.page,
                perPage: state.perPage,
                onChange: function (page) {
                    state.page = page;
                    state.expandedRows = {};
                    loadLogs(container);
                }
            });
        }
    }

    function readFilterState(container) {
        var userEl = container.querySelector('#rc-audit-user');
        var actionEl = container.querySelector('#rc-audit-action');
        var resourceEl = container.querySelector('#rc-audit-resource');
        var fromEl = container.querySelector('#rc-audit-from');
        var toEl = container.querySelector('#rc-audit-to');

        state.userId = userEl ? userEl.value.trim() : '';
        state.action = actionEl ? actionEl.value : '';
        state.resourceType = resourceEl ? resourceEl.value : '';
        state.fromDate = fromEl ? fromEl.value : state.fromDate;
        state.toDate = toEl ? toEl.value : state.toDate;
    }

    function loadLogs(container) {
        state.loading = true;
        state.error = null;
        renderContent(container);
        bindEvents(container);

        var params = { page: state.page, per_page: state.perPage };
        if (state.userId) params.user_id = state.userId;
        if (state.action) params.action = state.action;
        if (state.resourceType) params.resource_type = state.resourceType;
        if (state.fromDate) params.from_date = state.fromDate;
        if (state.toDate) params.to_date = state.toDate;
        if (state.unmask) params.unmask = true;

        RCApi.getAuditLogs(params).then(function (envelope) {
            state.loading = false;
            state.logs = envelope.data || [];
            var meta = envelope.meta || {};
            state.total = meta.total || 0;
            state.page = meta.page || 1;
            state.lastPage = meta.last_page || 1;
            renderContent(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load audit logs';
            renderContent(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.userId = '';
            state.action = '';
            state.resourceType = '';
            state.fromDate = (params && params.from_date) || defaultFromDate();
            state.toDate = (params && params.to_date) || defaultToDate();
            state.unmask = false;
            state.page = 1;
            state.logs = [];
            state.expandedRows = {};
            state.error = null;

            if (!RCAuth.isAdmin()) {
                renderContent(container);
                return;
            }
            loadLogs(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminAuditPage;
}
