/**
 * admin-lineage.js - Governance Lineage Page
 * Admin only. Lineage run list with expandable step details, filters, pagination.
 */
var AdminLineagePage = (function () {
    'use strict';

    var state = {
        runs: [],
        jobName: '',
        fromDate: '',
        toDate: '',
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        loading: false,
        error: null,
        expandedRuns: {}
    };

    function defaultFromDate() {
        var d = new Date();
        d.setDate(d.getDate() - 30);
        return d.toISOString().split('T')[0];
    }

    function defaultToDate() {
        return new Date().toISOString().split('T')[0];
    }

    function truncateUuid(uuid) {
        if (!uuid) return '';
        return uuid.length > 12 ? uuid.substring(0, 8) + '...' : uuid;
    }

    function groupByRunId(items) {
        var groups = {};
        var order = [];
        items.forEach(function (item) {
            var runId = item.run_id;
            if (!groups[runId]) {
                groups[runId] = {
                    run_id: runId,
                    job_name: item.job_name,
                    executed_at: item.executed_at,
                    total_input: 0,
                    total_output: 0,
                    total_removed: 0,
                    steps: []
                };
                order.push(runId);
            }
            groups[runId].steps.push(item);
            groups[runId].total_input += item.input_count || 0;
            groups[runId].total_output += item.output_count || 0;
            groups[runId].total_removed += item.removed_count || 0;
        });
        return order.map(function (id) { return groups[id]; });
    }

    function renderStepTable(steps) {
        var html = '<table class="layui-table" style="margin:8px 0 0 0;font-size:13px;">' +
            '<thead><tr>' +
            '<th>Step</th>' +
            '<th style="text-align:right;">Input</th>' +
            '<th style="text-align:right;">Output</th>' +
            '<th style="text-align:right;">Removed</th>' +
            '<th>Time</th>' +
            '</tr></thead><tbody>';

        steps.forEach(function (step) {
            html += '<tr>' +
                '<td>' + RCUtil.escapeHtml(step.step || '') + '</td>' +
                '<td style="text-align:right;">' + (step.input_count || 0) + '</td>' +
                '<td style="text-align:right;">' + (step.output_count || 0) + '</td>' +
                '<td style="text-align:right;">' + (step.removed_count || 0) + '</td>' +
                '<td style="font-size:12px;color:#999;">' + (step.executed_at ? RCUtil.formatDateTime(step.executed_at) : '') + '</td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    function renderRunRow(run) {
        var expanded = !!state.expandedRuns[run.run_id];
        var chevron = expanded ? 'layui-icon-down' : 'layui-icon-right';

        var html = '<tr class="rc-lineage-run" data-run-id="' + RCUtil.escapeHtml(run.run_id) + '" style="cursor:pointer;">' +
            '<td><i class="layui-icon ' + chevron + ' rc-chevron" style="font-size:12px;margin-right:6px;"></i>' +
            '<code style="font-size:12px;" title="' + RCUtil.escapeHtml(run.run_id) + '">' +
            truncateUuid(run.run_id) + '</code></td>' +
            '<td>' + RCUtil.escapeHtml(run.job_name || '') + '</td>' +
            '<td style="font-size:12px;">' + (run.executed_at ? RCUtil.formatDateTime(run.executed_at) : '') + '</td>' +
            '<td style="text-align:right;">' + run.total_input + '</td>' +
            '<td style="text-align:right;">' + run.total_output + '</td>' +
            '<td style="text-align:right;">' + run.total_removed + '</td>' +
            '</tr>';

        if (expanded && run.steps.length > 0) {
            html += '<tr class="rc-lineage-detail"><td colspan="6" style="padding:4px 16px 16px 32px;background:#fafafa;">' +
                renderStepTable(run.steps) + '</td></tr>';
        }

        return html;
    }

    function renderContent(container) {
        if (!RCAuth.isAdmin()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">Only administrators can view data lineage.</p></div>';
            return;
        }

        var html = '<div style="max-width:1100px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">Data Lineage</h2>';

        // Filter bar
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">' +
            '<input type="text" id="rc-lineage-job" class="layui-input" placeholder="Job name..." ' +
            'value="' + RCUtil.escapeHtml(state.jobName) + '" style="width:200px;height:36px;">' +
            '<label style="font-size:13px;color:#666;">From:</label>' +
            '<input type="date" id="rc-lineage-from" class="layui-input" value="' + RCUtil.escapeHtml(state.fromDate) + '" style="width:160px;height:36px;">' +
            '<label style="font-size:13px;color:#666;">To:</label>' +
            '<input type="date" id="rc-lineage-to" class="layui-input" value="' + RCUtil.escapeHtml(state.toDate) + '" style="width:160px;height:36px;">' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="rc-lineage-filter-btn">Filter</button></div>';

        // Table
        html += '<div style="overflow-x:auto;">';
        if (state.loading) {
            html += RCUtil.skeleton(5);
        } else if (state.error) {
            html += '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>';
        } else {
            var runs = groupByRunId(state.runs);
            if (runs.length === 0) {
                html += RCUtil.emptyState('No lineage data found for the selected period.');
            } else {
                html += '<table class="layui-table" style="margin:0;">' +
                    '<thead><tr>' +
                    '<th>Run ID</th>' +
                    '<th>Job Name</th>' +
                    '<th>Executed</th>' +
                    '<th style="text-align:right;">Input</th>' +
                    '<th style="text-align:right;">Output</th>' +
                    '<th style="text-align:right;">Removed</th>' +
                    '</tr></thead><tbody>';
                runs.forEach(function (run) {
                    html += renderRunRow(run);
                });
                html += '</tbody></table>';
            }
        }
        html += '</div>';

        // Pagination
        html += '<div id="rc-lineage-pagination" style="margin-top:16px;text-align:center;"></div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Filter
        var filterBtn = container.querySelector('#rc-lineage-filter-btn');
        if (filterBtn) {
            filterBtn.addEventListener('click', function () {
                var jobEl = container.querySelector('#rc-lineage-job');
                var fromEl = container.querySelector('#rc-lineage-from');
                var toEl = container.querySelector('#rc-lineage-to');
                state.jobName = jobEl ? jobEl.value.trim() : '';
                state.fromDate = fromEl ? fromEl.value : state.fromDate;
                state.toDate = toEl ? toEl.value : state.toDate;
                state.page = 1;
                state.expandedRuns = {};
                loadLineage(container);
            });
        }

        // Expandable rows
        container.querySelectorAll('.rc-lineage-run').forEach(function (row) {
            row.addEventListener('click', function () {
                var runId = this.getAttribute('data-run-id');
                if (state.expandedRuns[runId]) {
                    delete state.expandedRuns[runId];
                } else {
                    state.expandedRuns[runId] = true;
                }
                renderContent(container);
                bindEvents(container);
            });
        });

        // Pagination
        if (state.lastPage > 1) {
            RCUtil.renderPagination('rc-lineage-pagination', {
                total: state.total,
                page: state.page,
                perPage: state.perPage,
                onChange: function (page) {
                    state.page = page;
                    state.expandedRuns = {};
                    loadLineage(container);
                }
            });
        }
    }

    function loadLineage(container) {
        state.loading = true;
        state.error = null;
        renderContent(container);
        bindEvents(container);

        var params = { page: state.page, per_page: state.perPage };
        if (state.jobName) params.job_name = state.jobName;
        if (state.fromDate) params.from_date = state.fromDate;
        if (state.toDate) params.to_date = state.toDate;

        RCApi.getLineage(params).then(function (res) {
            state.loading = false;
            state.runs = res.data || [];
            var meta = res.meta || {};
            state.total = meta.total || 0;
            state.page = meta.page || 1;
            state.lastPage = meta.last_page || 1;
            renderContent(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load lineage data';
            renderContent(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.jobName = (params && params.job_name) || '';
            state.fromDate = (params && params.from_date) || defaultFromDate();
            state.toDate = (params && params.to_date) || defaultToDate();
            state.page = 1;
            state.runs = [];
            state.expandedRuns = {};
            state.error = null;

            if (!RCAuth.isAdmin()) {
                renderContent(container);
                return;
            }
            loadLineage(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminLineagePage;
}
