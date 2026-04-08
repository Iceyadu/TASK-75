/**
 * admin-governance.js - Governance Dashboard Page
 * Admin only. Date range, metric cards, credibility distribution, job runs.
 */
var AdminGovernancePage = (function () {
    'use strict';

    var METRIC_LABELS = {
        listing_completeness: { label: 'Listing Completeness', suffix: '%', multiplier: 100 },
        dedup_ratio: { label: 'Dedup Ratio', suffix: '%', multiplier: 100 },
        missing_value_rate: { label: 'Missing Value Rate', suffix: '%', multiplier: 100 },
        counter_drift: { label: 'Counter Drift', suffix: '', multiplier: 1 },
        queue_depth: { label: 'Queue Depth', suffix: '', multiplier: 1 },
        stale_listing_rate: { label: 'Stale Listing Rate', suffix: '%', multiplier: 100 }
    };

    var state = {
        metrics: null,
        fromDate: '',
        toDate: '',
        loading: false,
        error: null
    };

    function defaultFromDate() {
        var d = new Date();
        d.setDate(d.getDate() - 30);
        return d.toISOString().split('T')[0];
    }

    function defaultToDate() {
        return new Date().toISOString().split('T')[0];
    }

    function renderMetricCard(key, value) {
        var config = METRIC_LABELS[key] || { label: key.replace(/_/g, ' '), suffix: '', multiplier: 1 };
        var displayVal = value !== null && value !== undefined
            ? (parseFloat(value) * config.multiplier).toFixed(config.multiplier > 1 ? 1 : 0)
            : 'N/A';

        return '<div class="layui-col-md4 layui-col-sm6" style="margin-bottom:16px;">' +
            '<div class="layui-card" style="text-align:center;">' +
            '<div class="layui-card-body" style="padding:20px;">' +
            '<div style="font-size:32px;font-weight:700;color:#333;">' + displayVal + config.suffix + '</div>' +
            '<div style="font-size:13px;color:#999;margin-top:6px;">' +
            RCUtil.escapeHtml(config.label) + '</div>' +
            '</div></div></div>';
    }

    function renderCredibilityDistribution(dist) {
        if (!dist) return '';

        var values = {
            min: dist.min !== undefined ? parseFloat(dist.min) : 0,
            p25: dist.p25 !== undefined ? parseFloat(dist.p25) : 0,
            median: dist.median !== undefined ? parseFloat(dist.median) : 0,
            p75: dist.p75 !== undefined ? parseFloat(dist.p75) : 0,
            max: dist.max !== undefined ? parseFloat(dist.max) : 1
        };

        var html = '<div class="layui-card" style="margin-bottom:20px;">' +
            '<div class="layui-card-header">Credibility Score Distribution</div>' +
            '<div class="layui-card-body" style="padding:20px;">';

        // Box plot using divs
        html += '<div style="position:relative;height:50px;background:#f5f5f5;border-radius:4px;margin:20px 0;">';

        var minPct = values.min * 100;
        var p25Pct = values.p25 * 100;
        var medPct = values.median * 100;
        var p75Pct = values.p75 * 100;
        var maxPct = values.max * 100;

        // Whisker line (min to max)
        html += '<div style="position:absolute;top:50%;left:' + minPct + '%;width:' +
            (maxPct - minPct) + '%;height:2px;background:#999;transform:translateY(-50%);"></div>';

        // Box (p25 to p75)
        html += '<div style="position:absolute;top:10%;left:' + p25Pct + '%;width:' +
            (p75Pct - p25Pct) + '%;height:80%;background:#e3f2fd;border:2px solid #1E9FFF;border-radius:3px;"></div>';

        // Median line
        html += '<div style="position:absolute;top:5%;left:' + medPct +
            '%;width:3px;height:90%;background:#FF5722;border-radius:2px;"></div>';

        // End caps
        html += '<div style="position:absolute;top:25%;left:' + minPct +
            '%;width:2px;height:50%;background:#999;"></div>';
        html += '<div style="position:absolute;top:25%;left:' + maxPct +
            '%;width:2px;height:50%;background:#999;"></div>';

        html += '</div>';

        // Labels
        html += '<div style="display:flex;justify-content:space-between;font-size:12px;color:#666;">' +
            '<span>Min: ' + values.min.toFixed(2) + '</span>' +
            '<span>P25: ' + values.p25.toFixed(2) + '</span>' +
            '<span style="color:#FF5722;font-weight:600;">Median: ' + values.median.toFixed(2) + '</span>' +
            '<span>P75: ' + values.p75.toFixed(2) + '</span>' +
            '<span>Max: ' + values.max.toFixed(2) + '</span>' +
            '</div>';

        html += '</div></div>';
        return html;
    }

    function renderJobRuns(jobs) {
        if (!jobs || jobs.length === 0) return '';

        var html = '<div class="layui-card" style="margin-bottom:20px;">' +
            '<div class="layui-card-header">Last Job Runs</div>' +
            '<div class="layui-card-body" style="padding:0;">' +
            '<table class="layui-table" style="margin:0;">' +
            '<thead><tr>' +
            '<th>Job Name</th>' +
            '<th>Last Run</th>' +
            '<th style="text-align:right;">Records Processed</th>' +
            '</tr></thead><tbody>';

        jobs.forEach(function (job) {
            html += '<tr>' +
                '<td>' + RCUtil.escapeHtml(job.job_name || job.name || '') + '</td>' +
                '<td style="font-size:13px;">' + (job.last_run || job.executed_at ? RCUtil.formatDateTime(job.last_run || job.executed_at) : 'Never') + '</td>' +
                '<td style="text-align:right;">' + (job.records_processed !== undefined ? job.records_processed : '-') + '</td>' +
                '</tr>';
        });

        html += '</tbody></table></div></div>';
        return html;
    }

    function renderPage(container) {
        if (!RCAuth.isAdmin()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">Only administrators can view governance metrics.</p></div>';
            return;
        }

        var html = '<div style="max-width:1100px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">Governance Dashboard</h2>';

        // Date range + refresh
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">' +
            '<label style="font-size:13px;color:#666;">From:</label>' +
            '<input type="date" id="rc-gov-from" class="layui-input" value="' + RCUtil.escapeHtml(state.fromDate) + '" style="width:160px;height:36px;">' +
            '<label style="font-size:13px;color:#666;">To:</label>' +
            '<input type="date" id="rc-gov-to" class="layui-input" value="' + RCUtil.escapeHtml(state.toDate) + '" style="width:160px;height:36px;">' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="rc-gov-refresh">' +
            '<i class="layui-icon layui-icon-refresh"></i> Refresh</button></div>';

        if (state.loading) {
            html += RCUtil.skeleton(5);
        } else if (state.error) {
            html += '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>';
        } else if (!state.metrics || (!state.metrics.metrics && !state.metrics.summary)) {
            html += RCUtil.emptyState('Data quality metrics will appear after the first nightly governance job runs.');
        } else {
            var metricsData = state.metrics.metrics || state.metrics.summary || {};

            // Metric cards
            html += '<div class="layui-row layui-col-space16">';
            var metricKeys = Object.keys(METRIC_LABELS);
            metricKeys.forEach(function (key) {
                var val = metricsData[key] !== undefined ? metricsData[key] : null;
                html += renderMetricCard(key, val);
            });
            html += '</div>';

            // Credibility distribution
            html += renderCredibilityDistribution(metricsData.credibility_distribution || state.metrics.credibility_distribution);

            // Job runs
            html += renderJobRuns(state.metrics.job_runs || state.metrics.last_jobs || []);
        }

        html += '</div>';
        container.innerHTML = html;
    }

    function bindEvents(container) {
        var refreshBtn = container.querySelector('#rc-gov-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                var fromEl = container.querySelector('#rc-gov-from');
                var toEl = container.querySelector('#rc-gov-to');
                state.fromDate = fromEl ? fromEl.value : state.fromDate;
                state.toDate = toEl ? toEl.value : state.toDate;
                loadMetrics(container);
            });
        }
    }

    function loadMetrics(container) {
        state.loading = true;
        state.error = null;
        renderPage(container);
        bindEvents(container);

        var params = {};
        if (state.fromDate) params.from_date = state.fromDate;
        if (state.toDate) params.to_date = state.toDate;

        RCApi.getQualityMetrics(params).then(function (res) {
            state.loading = false;
            state.metrics = res.data || null;
            renderPage(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load metrics';
            renderPage(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.fromDate = (params && params.from_date) || defaultFromDate();
            state.toDate = (params && params.to_date) || defaultToDate();
            state.metrics = null;
            state.error = null;

            if (!RCAuth.isAdmin()) {
                renderPage(container);
                return;
            }
            loadMetrics(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminGovernancePage;
}
