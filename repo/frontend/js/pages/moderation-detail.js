/**
 * moderation-detail.js - Moderation Detail Page
 * Full content display, flag info, user profile, credibility breakdown, actions.
 */
var ModerationDetailPage = (function () {
    'use strict';

    var FLAG_COLORS = {
        sensitive_word: '#FF5722',
        duplicate: '#FF9800',
        low_credibility: '#FFB800',
        rate_limit: '#1E9FFF'
    };

    var state = {
        itemId: null,
        item: null,
        loading: false,
        error: null,
        actionLoading: false
    };

    function hasAccess() {
        return RCAuth.isAdmin() || RCAuth.isModerator();
    }

    function highlightWords(text, words) {
        if (!text || !words || words.length === 0) return RCUtil.escapeHtml(text);
        var escaped = RCUtil.escapeHtml(text);
        words.forEach(function (w) {
            var safeWord = w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var regex = new RegExp('(' + safeWord + ')', 'gi');
            escaped = escaped.replace(regex, '<mark style="background:#fff176;padding:1px 2px;">$1</mark>');
        });
        return escaped;
    }

    function credibilityColor(score) {
        if (score === null || score === undefined) return '#999';
        if (score > 0.7) return '#16b777';
        if (score >= 0.3) return '#FFB800';
        return '#FF5722';
    }

    function renderScoreBar(label, value, max) {
        max = max || 1;
        var pct = Math.min(100, Math.round((value / max) * 100));
        var color = credibilityColor(value);
        return '<div style="margin-bottom:10px;">' +
            '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">' +
            '<span style="color:#666;">' + RCUtil.escapeHtml(label) + '</span>' +
            '<span style="font-weight:600;color:' + color + ';">' + (typeof value === 'number' ? value.toFixed(2) : 'N/A') + '</span>' +
            '</div>' +
            '<div style="background:#f0f0f0;border-radius:3px;height:8px;overflow:hidden;">' +
            '<div style="width:' + pct + '%;height:100%;background:' + color + ';border-radius:3px;transition:width 0.3s;"></div>' +
            '</div></div>';
    }

    function renderFlagInfo(item) {
        var flagColor = FLAG_COLORS[item.flag_reason] || '#999';
        var details = null;
        try {
            details = typeof item.flag_details === 'string' ? JSON.parse(item.flag_details) : item.flag_details;
        } catch (e) { details = null; }

        var matchedWords = details && details.matched_words ? details.matched_words : [];
        var method = details && details.detection_method ? details.detection_method : '';

        var html = '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Flag Information</div>' +
            '<div class="layui-card-body" style="padding:16px;">' +
            '<div style="margin-bottom:8px;"><strong>Reason:</strong> ' +
            '<span style="background:' + flagColor + ';color:#fff;font-size:12px;padding:2px 10px;border-radius:10px;">' +
            RCUtil.escapeHtml((item.flag_reason || '').replace(/_/g, ' ')) + '</span></div>';

        if (matchedWords.length > 0) {
            html += '<div style="margin-bottom:8px;"><strong>Matched Words:</strong> ';
            matchedWords.forEach(function (w) {
                html += '<span style="background:#fff176;padding:2px 6px;border-radius:3px;margin-right:4px;font-size:13px;">' +
                    RCUtil.escapeHtml(w) + '</span>';
            });
            html += '</div>';
        }

        if (method) {
            html += '<div><strong>Detection Method:</strong> <span style="color:#666;">' +
                RCUtil.escapeHtml(method) + '</span></div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderContent(item) {
        var details = null;
        try {
            details = typeof item.flag_details === 'string' ? JSON.parse(item.flag_details) : item.flag_details;
        } catch (e) { details = null; }
        var matchedWords = details && details.matched_words ? details.matched_words : [];

        var html = '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Content (' + RCUtil.escapeHtml(item.item_type || 'item') + ')</div>' +
            '<div class="layui-card-body" style="padding:16px;">';

        if (item.item_type === 'review' && item.content) {
            html += '<div style="margin-bottom:8px;">' + RCUtil.starsHtml(item.content.rating || 0) + '</div>';
            html += '<div style="line-height:1.6;font-size:14px;">' +
                highlightWords(item.content.text || '', matchedWords) + '</div>';
        } else if (item.item_type === 'listing' && item.content) {
            html += '<h4 style="margin:0 0 8px 0;">' + highlightWords(item.content.title || '', matchedWords) + '</h4>';
            if (item.content.description) {
                html += '<div style="line-height:1.6;font-size:14px;">' +
                    highlightWords(item.content.description, matchedWords) + '</div>';
            }
        } else {
            var preview = item.content_preview || '(no content available)';
            html += '<div style="line-height:1.6;font-size:14px;">' +
                highlightWords(preview, matchedWords) + '</div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderUserProfile(item) {
        var user = item.user_info || item.user_profile;
        if (!user) return '';

        var html = '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">User Profile</div>' +
            '<div class="layui-card-body" style="padding:16px;">' +
            '<div style="font-size:15px;font-weight:500;margin-bottom:8px;">' + RCUtil.escapeHtml(user.name || 'Unknown') + '</div>';

        if (user.created_at) {
            html += '<div style="font-size:13px;color:#666;margin-bottom:4px;">Account age: ' +
                RCUtil.formatRelative(user.created_at) + '</div>';
        }
        if (user.completion_rate !== undefined) {
            html += '<div style="font-size:13px;color:#666;margin-bottom:4px;">Completion rate: ' +
                (user.completion_rate * 100).toFixed(0) + '%</div>';
        }
        if (user.recent_review_count !== undefined) {
            html += '<div style="font-size:13px;color:#666;">Recent reviews: ' + user.recent_review_count + '</div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderCredibilityBreakdown(item) {
        var score = item.credibility_score;
        if (score === null || score === undefined) return '';

        var details = null;
        try {
            details = typeof item.credibility_details === 'string' ? JSON.parse(item.credibility_details) : item.credibility_details;
        } catch (e) { details = null; }

        var html = '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Credibility Score</div>' +
            '<div class="layui-card-body" style="padding:16px;">';

        html += renderScoreBar('Total Score', parseFloat(score), 1);

        if (details) {
            if (details.age_factor !== undefined) html += renderScoreBar('Age Factor', parseFloat(details.age_factor), 1);
            if (details.completion_factor !== undefined) html += renderScoreBar('Completion Factor', parseFloat(details.completion_factor), 1);
            if (details.pattern_factor !== undefined) html += renderScoreBar('Pattern Factor', parseFloat(details.pattern_factor), 1);
        }

        html += '</div></div>';
        return html;
    }

    function renderActions(item) {
        var html = '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Actions</div>' +
            '<div class="layui-card-body" style="padding:16px;">';

        if (item.status !== 'pending') {
            html += '<div style="color:#666;font-size:13px;">This item has already been ' +
                RCUtil.escapeHtml(item.status) + '.</div>';
        } else {
            html += '<div style="display:flex;gap:8px;">' +
                '<button type="button" class="layui-btn rc-mod-detail-action" data-action="approve" style="background:#16b777;">Approve</button>' +
                '<button type="button" class="layui-btn layui-btn-danger rc-mod-detail-action" data-action="reject">Reject</button>' +
                '<button type="button" class="layui-btn layui-btn-warm rc-mod-detail-action" data-action="escalate">Escalate</button>' +
                '</div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderPage(container) {
        if (!hasAccess()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">You do not have permission to view moderation details.</p></div>';
            return;
        }

        if (state.loading) {
            container.innerHTML = '<div style="max-width:900px;margin:0 auto;padding:20px;">' + RCUtil.skeleton(6) + '</div>';
            return;
        }
        if (state.error) {
            container.innerHTML = '<div style="max-width:900px;margin:0 auto;padding:20px;">' +
                '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>' +
                '<a href="#/moderation" class="layui-btn layui-btn-primary layui-btn-sm">Back to Queue</a></div>';
            return;
        }

        var item = state.item;
        if (!item) return;

        var html = '<div style="max-width:900px;margin:0 auto;padding:20px;">' +
            '<a href="#/moderation" style="display:inline-block;margin-bottom:16px;color:#666;text-decoration:none;">' +
            '<i class="layui-icon layui-icon-left"></i> Back to Queue</a>' +
            '<h2 style="margin-bottom:20px;">Moderation Detail #' + item.id + '</h2>';

        html += '<div class="layui-row layui-col-space16">' +
            '<div class="layui-col-md7">' +
            renderContent(item) +
            renderFlagInfo(item) +
            '</div>' +
            '<div class="layui-col-md5">' +
            renderUserProfile(item) +
            renderCredibilityBreakdown(item) +
            renderActions(item) +
            '</div></div></div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Action buttons
        container.querySelectorAll('.rc-mod-detail-action').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.getAttribute('data-action');
                handleAction(action, container);
            });
        });
    }

    function handleAction(action, container) {
        if (state.actionLoading) return;
        var id = state.itemId;

        if (action === 'approve') {
            layui.layer.confirm('Approve this item?', { title: 'Confirm Approve' }, function (idx) {
                layui.layer.close(idx);
                executeAction(function () { return RCApi.approveModeration(id); }, container);
            });
        } else if (action === 'reject') {
            layui.layer.open({
                type: 1,
                title: 'Reject Item',
                area: ['420px', '260px'],
                content: '<div style="padding:20px;">' +
                    '<label style="display:block;margin-bottom:8px;font-weight:500;">Rejection Reason</label>' +
                    '<textarea id="rc-mod-detail-reject-reason" class="layui-textarea" placeholder="Please provide a reason..." style="height:80px;"></textarea>' +
                    '</div>',
                btn: ['Reject', 'Cancel'],
                yes: function (layerIdx) {
                    var reasonEl = document.querySelector('#rc-mod-detail-reject-reason');
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
        container.querySelectorAll('.rc-mod-detail-action').forEach(function (b) {
            b.disabled = true;
            b.classList.add('layui-btn-disabled');
        });

        apiCall().then(function (res) {
            state.actionLoading = false;
            layui.layer.msg(res.message || 'Success', { icon: 1 });
            loadItem(container);
        }).catch(function (err) {
            state.actionLoading = false;
            layui.layer.msg((err && err.message) ? err.message : 'Action failed', { icon: 2 });
            container.querySelectorAll('.rc-mod-detail-action').forEach(function (b) {
                b.disabled = false;
                b.classList.remove('layui-btn-disabled');
            });
        });
    }

    function loadItem(container) {
        state.loading = true;
        state.error = null;
        renderPage(container);

        RCApi.getModerationQueue({ id: state.itemId }).then(function (res) {
            state.loading = false;
            // The API may return the item directly or in a list
            if (res.data && Array.isArray(res.data)) {
                state.item = res.data.length > 0 ? res.data[0] : null;
            } else {
                state.item = res.data || null;
            }
            if (!state.item) {
                state.error = 'Moderation item not found';
            }
            renderPage(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load moderation item';
            renderPage(container);
        });
    }

    return {
        render: function (container, params) {
            state.itemId = params && params.id ? params.id : null;
            state.item = null;
            state.error = null;

            if (!hasAccess()) {
                renderPage(container);
                return;
            }
            if (!state.itemId) {
                state.error = 'No moderation item ID provided';
                renderPage(container);
                return;
            }
            loadItem(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModerationDetailPage;
}
