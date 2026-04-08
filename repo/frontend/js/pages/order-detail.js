/**
 * order-detail.js - Order Detail Page
 * Full lifecycle UI with status header, progress bar, parties, actions, disputes, resolution.
 */
var OrderDetailPage = (function () {
    'use strict';

    var LIFECYCLE_STEPS = [
        { key: 'pending_match', label: 'Pending Match' },
        { key: 'accepted', label: 'Accepted' },
        { key: 'in_progress', label: 'In Progress' },
        { key: 'completed', label: 'Completed' }
    ];

    var CANCEL_REASONS = [
        { value: 'DRIVER_UNAVAILABLE', label: 'Driver Unavailable' },
        { value: 'PASSENGER_CHANGED_PLANS', label: 'Passenger Changed Plans' },
        { value: 'VEHICLE_ISSUE', label: 'Vehicle Issue' },
        { value: 'SCHEDULE_CONFLICT', label: 'Schedule Conflict' },
        { value: 'OTHER', label: 'Other' }
    ];

    var RESOLUTION_OUTCOMES = [
        { value: 'passenger_favor', label: 'Passenger Favor' },
        { value: 'driver_favor', label: 'Driver Favor' },
        { value: 'mutual', label: 'Mutual Resolution' },
        { value: 'dismissed', label: 'Dismissed' }
    ];

    var STATUS_COLORS = {
        pending_match: '#1E9FFF',
        accepted: '#FFB800',
        in_progress: '#FF6600',
        completed: '#16b777',
        canceled: '#999',
        expired: '#999',
        disputed: '#FF5722',
        resolved: '#7c4dff'
    };

    var state = {
        order: null,
        orderId: null,
        loading: false,
        error: null,
        actionLoading: false
    };

    function currentUserId() {
        var u = RCAuth.getUser();
        return u ? u.id : null;
    }

    function isParty() {
        if (!state.order) return false;
        var uid = currentUserId();
        return state.order.passenger_id === uid || state.order.driver_id === uid;
    }

    function userRole() {
        if (!state.order) return null;
        var uid = currentUserId();
        if (state.order.passenger_id === uid) return 'passenger';
        if (state.order.driver_id === uid) return 'driver';
        return null;
    }

    function relevantTimestamp(order) {
        var map = {
            pending_match: order.created_at,
            accepted: order.accepted_at,
            in_progress: order.started_at,
            completed: order.completed_at,
            canceled: order.canceled_at,
            expired: order.expires_at,
            disputed: order.disputed_at,
            resolved: order.resolved_at
        };
        return map[order.status] || order.created_at;
    }

    function stepIndex(status) {
        for (var i = 0; i < LIFECYCLE_STEPS.length; i++) {
            if (LIFECYCLE_STEPS[i].key === status) return i;
        }
        return -1;
    }

    function isTerminal(status) {
        return ['canceled', 'expired', 'disputed', 'resolved'].indexOf(status) !== -1;
    }

    function isCancelFree(order) {
        if (order.status === 'pending_match') return true;
        if (order.status === 'accepted' && order.cancel_free_until) {
            return new Date(order.cancel_free_until) > new Date();
        }
        return false;
    }

    function isWithin72h(completedAt) {
        if (!completedAt) return false;
        var completed = new Date(completedAt);
        var cutoff = new Date(completed.getTime() + 72 * 60 * 60 * 1000);
        return new Date() < cutoff;
    }

    function hasTransition(name) {
        if (!state.order || !state.order.allowed_transitions) return false;
        return state.order.allowed_transitions.indexOf(name) !== -1;
    }

    // ---------------------------------------------------------------
    // Render helpers
    // ---------------------------------------------------------------

    function renderStatusHeader(order) {
        var color = STATUS_COLORS[order.status] || '#999';
        var ts = relevantTimestamp(order);
        var label = order.status.replace(/_/g, ' ').replace(/\b\w/g, function (c) {
            return c.toUpperCase();
        });
        return '<div style="background:' + color + ';color:#fff;padding:20px 24px;border-radius:4px;margin-bottom:20px;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;">' +
            '<span style="font-size:22px;font-weight:600;">' + RCUtil.escapeHtml(label) + '</span>' +
            '<span style="font-size:14px;opacity:0.9;">' + (ts ? RCUtil.formatDateTime(ts) : '') + '</span>' +
            '</div></div>';
    }

    function renderProgressBar(order) {
        var current = stepIndex(order.status);
        var terminal = isTerminal(order.status);

        var html = '<div style="display:flex;align-items:center;margin-bottom:24px;padding:0 8px;">';
        for (var i = 0; i < LIFECYCLE_STEPS.length; i++) {
            var step = LIFECYCLE_STEPS[i];
            var filled = (!terminal && i <= current) || (terminal && i <= current);
            var isCurrent = !terminal && i === current;
            var bg, fg;
            if (terminal && current === -1) {
                bg = '#e0e0e0'; fg = '#999';
            } else if (i < current || (i === current && !terminal)) {
                bg = '#16b777'; fg = '#fff';
            } else if (terminal && i === current) {
                bg = STATUS_COLORS[order.status] || '#999'; fg = '#fff';
            } else {
                bg = '#e0e0e0'; fg = '#999';
            }

            html += '<div style="flex:1;text-align:center;">' +
                '<div style="width:36px;height:36px;border-radius:50%;background:' + bg +
                ';color:' + fg + ';display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:14px;">' +
                (i + 1) + '</div>' +
                '<div style="font-size:12px;margin-top:4px;color:' + (isCurrent ? '#333' : '#999') + ';">' +
                RCUtil.escapeHtml(step.label) + '</div></div>';
            if (i < LIFECYCLE_STEPS.length - 1) {
                var lineColor = (i < current) ? '#16b777' : '#e0e0e0';
                html += '<div style="flex:1;height:3px;background:' + lineColor + ';margin:0 4px;border-radius:2px;align-self:center;margin-bottom:18px;"></div>';
            }
        }

        if (terminal) {
            var tLabel = order.status.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            html += '<div style="flex:0;text-align:center;margin-left:12px;">' +
                '<div style="width:36px;height:36px;border-radius:50%;background:' +
                (STATUS_COLORS[order.status] || '#999') + ';color:#fff;display:inline-flex;align-items:center;justify-content:center;">' +
                '<i class="layui-icon layui-icon-close"></i></div>' +
                '<div style="font-size:12px;margin-top:4px;color:#999;">' + RCUtil.escapeHtml(tLabel) + '</div></div>';
        }

        html += '</div>';
        return html;
    }

    function renderPartiesCard(order) {
        var pName = order.passenger_info ? RCUtil.escapeHtml(order.passenger_info.name) : 'Passenger #' + order.passenger_id;
        var dName = order.driver_info ? RCUtil.escapeHtml(order.driver_info.name) : 'Driver #' + order.driver_id;
        var role = userRole();
        var roleLabel = role ? 'You are the <strong>' + role + '</strong>' : '';

        return '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Parties</div>' +
            '<div class="layui-card-body" style="padding:16px;">' +
            '<div style="display:flex;gap:24px;flex-wrap:wrap;">' +
            '<div><span style="color:#999;font-size:12px;">Passenger</span><div style="font-size:15px;font-weight:500;">' + pName + '</div></div>' +
            '<div><span style="color:#999;font-size:12px;">Driver</span><div style="font-size:15px;font-weight:500;">' + dName + '</div></div>' +
            '</div>' +
            (roleLabel ? '<div style="margin-top:10px;font-size:13px;color:#666;">' + roleLabel + '</div>' : '') +
            '</div></div>';
    }

    function renderListingSummary(order) {
        var ls = order.listing_summary;
        if (!ls) return '';
        return '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Listing</div>' +
            '<div class="layui-card-body" style="padding:16px;">' +
            '<h4 style="margin:0 0 8px 0;">' + RCUtil.escapeHtml(ls.title) + '</h4>' +
            '<div style="font-size:13px;color:#666;margin-bottom:4px;"><i class="layui-icon layui-icon-location"></i> From: ' +
            RCUtil.escapeHtml(ls.pickup_address) + '</div>' +
            '<div style="font-size:13px;color:#666;margin-bottom:8px;"><i class="layui-icon layui-icon-location"></i> To: ' +
            RCUtil.escapeHtml(ls.dropoff_address) + '</div>' +
            '<a href="#/listings/' + ls.id + '" class="layui-btn layui-btn-xs layui-btn-primary">View Listing</a>' +
            '</div></div>';
    }

    function renderBlockedMessage(msg) {
        return '<div style="background:#f8f8f8;border-left:3px solid #ccc;padding:12px 16px;margin-bottom:12px;color:#666;font-size:13px;">' +
            '<i class="layui-icon layui-icon-about" style="color:#999;margin-right:6px;"></i>' +
            RCUtil.escapeHtml(msg) + '</div>';
    }

    function renderActionPanel(order) {
        var html = '<div class="layui-card" style="margin-bottom:16px;">' +
            '<div class="layui-card-header">Actions</div>' +
            '<div class="layui-card-body" style="padding:16px;">';

        var s = order.status;
        var uid = currentUserId();
        var party = isParty();

        // Terminal states
        if (s === 'canceled') {
            html += renderBlockedMessage('This order has been canceled. No further actions are available.');
            if (order.cancel_reason_code) {
                html += '<div style="font-size:13px;color:#666;margin-top:8px;"><strong>Reason:</strong> ' +
                    RCUtil.escapeHtml(order.cancel_reason_code.replace(/_/g, ' ')) + '</div>';
            }
            if (order.cancel_reason_text) {
                html += '<div style="font-size:13px;color:#666;margin-top:4px;">' +
                    RCUtil.escapeHtml(order.cancel_reason_text) + '</div>';
            }
            html += '</div></div>';
            return html;
        }

        if (s === 'expired') {
            html += renderBlockedMessage('This match request expired. The listing has been returned to active status.');
            html += '</div></div>';
            return html;
        }

        if (s === 'resolved') {
            html += '<div style="margin-bottom:8px;"><strong>Resolution:</strong> ' +
                RCUtil.escapeHtml((order.resolution_outcome || '').replace(/_/g, ' ')) + '</div>';
            if (order.resolution_notes) {
                html += '<div style="font-size:13px;color:#666;">' +
                    RCUtil.escapeHtml(order.resolution_notes) + '</div>';
            }
            html += '</div></div>';
            return html;
        }

        // Accept Match
        if (hasTransition('accept') && s === 'pending_match') {
            html += '<button type="button" class="layui-btn layui-btn-normal rc-action-btn" data-action="accept" style="margin-right:8px;">' +
                'Accept Match</button>';
        }

        // Start Trip
        if (hasTransition('start') && s === 'accepted') {
            html += '<button type="button" class="layui-btn layui-btn-warm rc-action-btn" data-action="start" style="margin-right:8px;">' +
                'Start Trip</button>';
        } else if (s === 'pending_match' && party) {
            html += renderBlockedMessage('The trip cannot be started until the passenger accepts the match.');
        }

        // Complete Trip
        if (hasTransition('complete') && s === 'in_progress') {
            html += '<button type="button" class="layui-btn rc-action-btn" data-action="complete" style="margin-right:8px;">' +
                'Complete Trip</button>';
        }

        // Cancel
        html += renderCancelSection(order);

        // Dispute
        html += renderDisputeSection(order);

        // Resolution
        html += renderResolutionSection(order);

        // Review link
        if (s === 'completed' && party && !order.has_review) {
            html += '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #eee;">' +
                '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal rc-leave-review-btn">' +
                '<i class="layui-icon layui-icon-praise"></i> Leave a Review</button></div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderCancelSection(order) {
        var s = order.status;
        var html = '';

        if (s === 'in_progress') {
            html += renderBlockedMessage('Cancellation is not allowed once a trip is in progress.');
            return html;
        }

        if (!hasTransition('cancel')) return '';

        if (s === 'pending_match' || (s === 'accepted' && isCancelFree(order))) {
            html += '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary rc-action-btn" data-action="cancel-free" style="margin-right:8px;">' +
                'Cancel (free)</button>';
        } else if (s === 'accepted') {
            html += '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;">' +
                '<h4 style="margin:0 0 8px 0;font-size:14px;">Cancel Order</h4>' +
                '<div class="layui-form-item">' +
                '<label class="layui-form-label" style="width:auto;padding:0 0 6px 0;">Reason Code</label>' +
                '<div class="layui-input-block" style="margin-left:0;">' +
                '<select id="rc-cancel-reason-code" lay-ignore style="height:36px;width:100%;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;margin-bottom:8px;">' +
                '<option value="">-- Select Reason --</option>' +
                CANCEL_REASONS.map(function (r) {
                    return '<option value="' + r.value + '">' + RCUtil.escapeHtml(r.label) + '</option>';
                }).join('') +
                '</select></div></div>' +
                '<div class="layui-form-item" id="rc-cancel-text-group" style="display:none;">' +
                '<label class="layui-form-label" style="width:auto;padding:0 0 6px 0;">Reason Details</label>' +
                '<div class="layui-input-block" style="margin-left:0;">' +
                '<textarea id="rc-cancel-reason-text" class="layui-textarea" placeholder="Please explain..." maxlength="500" style="height:80px;"></textarea>' +
                '</div></div>' +
                '<button type="button" class="layui-btn layui-btn-sm layui-btn-danger rc-action-btn" data-action="cancel-with-reason">Cancel Order</button>' +
                '</div>';
        }
        return html;
    }

    function renderDisputeSection(order) {
        var s = order.status;
        if (s !== 'completed') return '';

        var html = '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;">';
        if (!isWithin72h(order.completed_at)) {
            html += renderBlockedMessage('The dispute window (72 hours) has closed for this trip.');
        } else if (hasTransition('dispute')) {
            html += '<h4 style="margin:0 0 8px 0;font-size:14px;">Open Dispute</h4>' +
                '<textarea id="rc-dispute-reason" class="layui-textarea" placeholder="Describe the issue..." maxlength="1000" style="height:80px;margin-bottom:8px;"></textarea>' +
                '<div style="font-size:12px;color:#999;margin-bottom:8px;"><span id="rc-dispute-count">0</span>/1000</div>' +
                '<button type="button" class="layui-btn layui-btn-sm layui-btn-danger rc-action-btn" data-action="dispute">Open Dispute</button>';
        }
        html += '</div>';
        return html;
    }

    function renderResolutionSection(order) {
        if (order.status !== 'disputed') return '';
        if (!RCAuth.isAdmin()) return '';

        var html = '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee;">' +
            '<h4 style="margin:0 0 8px 0;font-size:14px;">Resolve Dispute</h4>';

        if (order.dispute_reason) {
            html += '<div style="background:#fff8e1;border-left:3px solid #FFB800;padding:10px 14px;margin-bottom:12px;font-size:13px;">' +
                '<strong>Dispute reason:</strong> ' + RCUtil.escapeHtml(order.dispute_reason) + '</div>';
        }

        html += '<div class="layui-form-item">' +
            '<label class="layui-form-label" style="width:auto;padding:0 0 6px 0;">Outcome</label>' +
            '<div class="layui-input-block" style="margin-left:0;">' +
            '<select id="rc-resolve-outcome" lay-ignore style="height:36px;width:100%;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;margin-bottom:8px;">' +
            '<option value="">-- Select Outcome --</option>' +
            RESOLUTION_OUTCOMES.map(function (o) {
                return '<option value="' + o.value + '">' + RCUtil.escapeHtml(o.label) + '</option>';
            }).join('') +
            '</select></div></div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label" style="width:auto;padding:0 0 6px 0;">Resolution Notes</label>' +
            '<div class="layui-input-block" style="margin-left:0;">' +
            '<textarea id="rc-resolve-text" class="layui-textarea" placeholder="Resolution details..." maxlength="2000" style="height:100px;"></textarea>' +
            '</div></div>' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-warm rc-action-btn" data-action="resolve">Resolve</button>' +
            '</div>';
        return html;
    }

    // ---------------------------------------------------------------
    // Main render
    // ---------------------------------------------------------------

    function renderPage(container) {
        if (state.loading) {
            container.innerHTML = '<div style="max-width:960px;margin:0 auto;padding:20px;">' + RCUtil.skeleton(6) + '</div>';
            return;
        }
        if (state.error) {
            container.innerHTML = '<div style="max-width:960px;margin:0 auto;padding:20px;">' +
                '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>' +
                '<a href="#/orders" class="layui-btn layui-btn-primary layui-btn-sm">Back to Orders</a></div>';
            return;
        }

        var order = state.order;
        if (!order) return;

        var html = '<div style="max-width:960px;margin:0 auto;padding:20px;">' +
            '<a href="#/orders" style="display:inline-block;margin-bottom:16px;color:#666;text-decoration:none;">' +
            '<i class="layui-icon layui-icon-left"></i> Back to Orders</a>';

        html += renderStatusHeader(order);
        html += renderProgressBar(order);

        // Two-column layout
        html += '<div class="layui-row layui-col-space16">' +
            '<div class="layui-col-md6">' +
            renderPartiesCard(order) +
            renderListingSummary(order) +
            '</div>' +
            '<div class="layui-col-md6">' +
            renderActionPanel(order) +
            '</div></div></div>';

        container.innerHTML = html;
    }

    // ---------------------------------------------------------------
    // Event binding
    // ---------------------------------------------------------------

    function bindEvents(container) {
        // Cancel reason code toggle
        var reasonSelect = container.querySelector('#rc-cancel-reason-code');
        if (reasonSelect) {
            reasonSelect.addEventListener('change', function () {
                var textGroup = container.querySelector('#rc-cancel-text-group');
                if (textGroup) {
                    textGroup.style.display = this.value === 'OTHER' ? '' : 'none';
                }
            });
        }

        // Dispute character counter
        var disputeArea = container.querySelector('#rc-dispute-reason');
        if (disputeArea) {
            disputeArea.addEventListener('input', function () {
                var counter = container.querySelector('#rc-dispute-count');
                if (counter) counter.textContent = this.value.length;
            });
        }

        // Leave review
        var reviewBtn = container.querySelector('.rc-leave-review-btn');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function () {
                RCRouter.navigate('#/orders/' + state.orderId + '/review');
            });
        }

        // Action buttons
        container.querySelectorAll('.rc-action-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.getAttribute('data-action');
                handleAction(action, container);
            });
        });

        // Back link
        var backLink = container.querySelector('a[href="#/orders"]');
        if (backLink) {
            backLink.addEventListener('click', function (e) {
                e.preventDefault();
                RCRouter.navigate('#/orders');
            });
        }
    }

    function setActionLoading(container, loading) {
        state.actionLoading = loading;
        container.querySelectorAll('.rc-action-btn').forEach(function (btn) {
            btn.disabled = loading;
            if (loading) {
                btn.classList.add('layui-btn-disabled');
            } else {
                btn.classList.remove('layui-btn-disabled');
            }
        });
    }

    function handleAction(action, container) {
        if (state.actionLoading) return;

        switch (action) {
            case 'accept':
                layui.layer.confirm('Accept this match and confirm the ride?', { title: 'Confirm Accept' }, function (idx) {
                    layui.layer.close(idx);
                    executeAction(function () { return RCApi.acceptOrder(state.orderId); }, container);
                });
                break;

            case 'start':
                layui.layer.confirm('Start this trip now?', { title: 'Confirm Start' }, function (idx) {
                    layui.layer.close(idx);
                    executeAction(function () { return RCApi.startOrder(state.orderId); }, container);
                });
                break;

            case 'complete':
                layui.layer.confirm('Mark this trip as completed?', { title: 'Confirm Complete' }, function (idx) {
                    layui.layer.close(idx);
                    executeAction(function () { return RCApi.completeOrder(state.orderId); }, container);
                });
                break;

            case 'cancel-free':
                layui.layer.confirm('Cancel this order? This cancellation is free of charge.', { title: 'Confirm Cancel' }, function (idx) {
                    layui.layer.close(idx);
                    executeAction(function () { return RCApi.cancelOrder(state.orderId, {}); }, container);
                });
                break;

            case 'cancel-with-reason':
                handleCancelWithReason(container);
                break;

            case 'dispute':
                handleDispute(container);
                break;

            case 'resolve':
                handleResolve(container);
                break;
        }
    }

    function handleCancelWithReason(container) {
        var codeEl = container.querySelector('#rc-cancel-reason-code');
        var textEl = container.querySelector('#rc-cancel-reason-text');
        var code = codeEl ? codeEl.value : '';
        var text = textEl ? textEl.value.trim() : '';

        if (!code) {
            layui.layer.msg('Please select a cancellation reason.', { icon: 0 });
            return;
        }
        if (code === 'OTHER' && !text) {
            layui.layer.msg('Please provide details for your cancellation reason.', { icon: 0 });
            return;
        }

        layui.layer.confirm('Cancel this order?', { title: 'Confirm Cancel' }, function (idx) {
            layui.layer.close(idx);
            executeAction(function () {
                return RCApi.cancelOrder(state.orderId, { reason_code: code, reason_text: text });
            }, container);
        });
    }

    function handleDispute(container) {
        var reasonEl = container.querySelector('#rc-dispute-reason');
        var reason = reasonEl ? reasonEl.value.trim() : '';
        if (!reason) {
            layui.layer.msg('Please describe the dispute reason.', { icon: 0 });
            return;
        }
        if (reason.length > 1000) {
            layui.layer.msg('Dispute reason cannot exceed 1000 characters.', { icon: 0 });
            return;
        }

        layui.layer.confirm('Open a dispute for this order? This will notify the other party and an admin.', { title: 'Confirm Dispute' }, function (idx) {
            layui.layer.close(idx);
            executeAction(function () {
                return RCApi.disputeOrder(state.orderId, { reason: reason });
            }, container);
        });
    }

    function handleResolve(container) {
        var outcomeEl = container.querySelector('#rc-resolve-outcome');
        var textEl = container.querySelector('#rc-resolve-text');
        var outcome = outcomeEl ? outcomeEl.value : '';
        var resolution = textEl ? textEl.value.trim() : '';

        if (!outcome) {
            layui.layer.msg('Please select a resolution outcome.', { icon: 0 });
            return;
        }
        if (!resolution) {
            layui.layer.msg('Please provide resolution notes.', { icon: 0 });
            return;
        }
        if (resolution.length > 2000) {
            layui.layer.msg('Resolution notes cannot exceed 2000 characters.', { icon: 0 });
            return;
        }

        layui.layer.confirm('Resolve this dispute with outcome: ' + outcome.replace(/_/g, ' ') + '?', { title: 'Confirm Resolution' }, function (idx) {
            layui.layer.close(idx);
            executeAction(function () {
                return RCApi.resolveOrder(state.orderId, { outcome: outcome, resolution: resolution });
            }, container);
        });
    }

    function executeAction(apiCall, container) {
        setActionLoading(container, true);
        apiCall().then(function (envelope) {
            setActionLoading(container, false);
            layui.layer.msg(envelope.message || 'Success', { icon: 1 });
            loadOrder(container);
        }).catch(function (err) {
            setActionLoading(container, false);
            var msg = (err && err.message) ? err.message : 'Action failed';
            layui.layer.msg(msg, { icon: 2 });
        });
    }

    // ---------------------------------------------------------------
    // Data loading
    // ---------------------------------------------------------------

    function loadOrder(container) {
        state.loading = true;
        state.error = null;
        renderPage(container);

        RCApi.getOrder(state.orderId).then(function (envelope) {
            state.loading = false;
            state.order = envelope.data;
            renderPage(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load order';
            renderPage(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.orderId = params && params.id ? params.id : null;
            state.order = null;
            state.error = null;
            if (!state.orderId) {
                state.error = 'No order ID provided';
                renderPage(container);
                return;
            }
            loadOrder(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OrderDetailPage;
}
