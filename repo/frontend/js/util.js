/**
 * RideCircle Utility Functions
 */
var RCUtil = (function() {
    'use strict';

    var statusColors = {
        draft: '#999', active: '#5FB878', published: '#5FB878',
        matched: '#1E9FFF', in_progress: '#FFB800', completed: '#009688',
        canceled: '#FF5722', disputed: '#e65100', pending_match: '#03A9F4',
        pending: '#FFB800', expired: '#999', resolved: '#9C27B0',
        hidden: '#FF5722', rejected: '#FF5722', approved: '#5FB878',
        escalated: '#e65100', disabled: '#FF5722'
    };

    var statusLabels = {
        draft: 'Draft', active: 'Active', published: 'Published',
        matched: 'Matched', in_progress: 'In Progress', completed: 'Completed',
        canceled: 'Canceled', disputed: 'Disputed', pending_match: 'Pending Match',
        pending: 'Pending', expired: 'Expired', resolved: 'Resolved',
        hidden: 'Hidden', rejected: 'Rejected', approved: 'Approved',
        escalated: 'Escalated', disabled: 'Disabled'
    };

    return {
        // Status badge HTML
        statusBadge: function(status) {
            if (!status) return '';
            var label = statusLabels[status] || status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
            var color = statusColors[status] || '#999';
            return '<span class="layui-badge rc-badge-' + status + '" style="background:' + color + ';">' + this.escapeHtml(label) + '</span>';
        },

        // Format ISO date to readable (YYYY-MM-DD)
        formatDate: function(isoStr) {
            if (!isoStr) return '—';
            var d = new Date(isoStr);
            if (isNaN(d.getTime())) return '—';
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        },

        // Format ISO date+time
        formatDateTime: function(isoStr) {
            if (!isoStr) return '—';
            var d = new Date(isoStr);
            if (isNaN(d.getTime())) return '—';
            var y = d.getFullYear();
            var mo = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var h = String(d.getHours()).padStart(2, '0');
            var mi = String(d.getMinutes()).padStart(2, '0');
            return y + '-' + mo + '-' + day + ' ' + h + ':' + mi;
        },

        // Format 12-hour time
        formatTime12h: function(isoStr) {
            if (!isoStr) return '—';
            var d = new Date(isoStr);
            if (isNaN(d.getTime())) return '—';
            var h = d.getHours();
            var m = String(d.getMinutes()).padStart(2, '0');
            var ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + m + ' ' + ampm;
        },

        // Relative time ("2 hours ago")
        formatRelative: function(isoStr) {
            if (!isoStr) return '—';
            var d = new Date(isoStr);
            if (isNaN(d.getTime())) return '—';
            var now = new Date();
            var diff = Math.floor((now - d) / 1000);
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
            return this.formatDate(isoStr);
        },

        // Stars HTML
        starsHtml: function(rating, max) {
            max = max || 5;
            rating = Math.round(rating) || 0;
            var html = '<span class="rc-stars">';
            for (var i = 1; i <= max; i++) {
                html += '<span class="' + (i <= rating ? 'star-filled' : 'star-empty') + '">\u2605</span>';
            }
            html += '</span>';
            return html;
        },

        // Truncate text
        truncate: function(text, len) {
            if (!text) return '';
            len = len || 100;
            if (text.length <= len) return text;
            return text.substring(0, len) + '...';
        },

        // Escape HTML
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        // Parse hash route
        parseHash: function(hash) {
            if (!hash) return { page: '/', params: {}, path: '/' };
            hash = hash.replace(/^#/, '');
            var qIndex = hash.indexOf('?');
            var path = qIndex !== -1 ? hash.substring(0, qIndex) : hash;
            var queryStr = qIndex !== -1 ? hash.substring(qIndex + 1) : '';
            var params = {};
            if (queryStr) {
                queryStr.split('&').forEach(function(pair) {
                    var kv = pair.split('=');
                    params[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || '');
                });
            }
            var parts = path.split('/').filter(Boolean);
            return {
                path: path || '/',
                page: parts[0] || '',
                id: parts[1] || null,
                sub: parts[2] || null,
                subId: parts[3] || null,
                parts: parts,
                params: params
            };
        },

        // Build query string from object
        buildQuery: function(params) {
            if (!params) return '';
            var parts = [];
            for (var key in params) {
                if (params.hasOwnProperty(key) && params[key] !== undefined && params[key] !== null && params[key] !== '') {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
                }
            }
            return parts.length ? '?' + parts.join('&') : '';
        },

        // Loading skeleton HTML
        skeleton: function(count) {
            count = count || 3;
            var html = '';
            for (var i = 0; i < count; i++) {
                html += '<div class="rc-skeleton rc-skeleton-card"></div>';
            }
            return html;
        },

        // Empty state HTML
        emptyState: function(message, actionText, actionHash) {
            var html = '<div class="rc-empty-state">';
            html += '<div class="rc-empty-icon">\u2205</div>';
            html += '<div class="rc-empty-text">' + this.escapeHtml(message) + '</div>';
            if (actionText && actionHash) {
                html += '<div class="rc-empty-action"><a href="' + actionHash + '" class="layui-btn layui-btn-normal">' + this.escapeHtml(actionText) + '</a></div>';
            }
            html += '</div>';
            return html;
        },

        // Blocked action message HTML
        blockedMessage: function(message) {
            return '<div class="rc-blocked-msg"><span style="font-size:18px;">\u26A0</span> ' + this.escapeHtml(message) + '</div>';
        },

        // Pagination render helper
        renderPagination: function(containerId, total, page, perPage, callback) {
            if (!total || total <= perPage) {
                var c = document.getElementById(containerId);
                if (c) c.innerHTML = '';
                return;
            }
            layui.laypage.render({
                elem: containerId,
                count: total,
                limit: perPage,
                curr: page,
                layout: ['prev', 'page', 'next', 'count'],
                jump: function(obj, first) {
                    if (!first && typeof callback === 'function') {
                        callback(obj.curr);
                    }
                }
            });
        },

        // Form error display
        showFieldErrors: function(errors) {
            this.clearFieldErrors();
            if (!errors) return;
            for (var field in errors) {
                if (errors.hasOwnProperty(field)) {
                    var input = document.querySelector('[name="' + field + '"]');
                    if (input) {
                        input.classList.add('rc-field-error-input');
                        input.classList.add('layui-form-danger');
                        var msg = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
                        var errEl = document.createElement('span');
                        errEl.className = 'rc-field-error';
                        errEl.textContent = msg;
                        input.parentNode.appendChild(errEl);
                    }
                }
            }
        },

        clearFieldErrors: function() {
            document.querySelectorAll('.rc-field-error').forEach(function(el) { el.remove(); });
            document.querySelectorAll('.rc-field-error-input').forEach(function(el) {
                el.classList.remove('rc-field-error-input');
                el.classList.remove('layui-form-danger');
            });
        },

        // Search history (localStorage)
        getSearchHistory: function() {
            try { return JSON.parse(localStorage.getItem('rc_search_history') || '[]'); }
            catch(e) { return []; }
        },

        addSearchHistory: function(term) {
            if (!term || !term.trim()) return;
            var history = this.getSearchHistory();
            history = history.filter(function(h) { return h !== term; });
            history.unshift(term);
            if (history.length > 10) history = history.slice(0, 10);
            localStorage.setItem('rc_search_history', JSON.stringify(history));
        },

        clearSearchHistory: function() {
            localStorage.removeItem('rc_search_history');
        },

        // File size formatting
        formatFileSize: function(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            i = Math.min(i, units.length - 1);
            return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
        },

        // Breadcrumb HTML
        breadcrumb: function(items) {
            var html = '<div class="rc-breadcrumb">';
            items.forEach(function(item, idx) {
                if (idx > 0) html += '<span class="rc-breadcrumb-sep">/</span>';
                if (item.hash) {
                    html += '<a href="' + item.hash + '">' + item.text + '</a>';
                } else {
                    html += '<span class="rc-breadcrumb-current">' + item.text + '</span>';
                }
            });
            html += '</div>';
            return html;
        }
    };
})();
