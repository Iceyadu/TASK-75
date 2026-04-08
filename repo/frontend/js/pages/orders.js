/**
 * orders.js - Order List Page
 * Displays user's orders with role/status filters and pagination.
 */
var OrdersPage = (function () {
    'use strict';

    var STATUSES = [
        { value: '', label: 'All Statuses' },
        { value: 'pending_match', label: 'Pending Match' },
        { value: 'accepted', label: 'Accepted' },
        { value: 'in_progress', label: 'In Progress' },
        { value: 'completed', label: 'Completed' },
        { value: 'canceled', label: 'Canceled' },
        { value: 'expired', label: 'Expired' },
        { value: 'disputed', label: 'Disputed' },
        { value: 'resolved', label: 'Resolved' }
    ];

    var ROLES = [
        { value: '', label: 'All' },
        { value: 'passenger', label: 'As Passenger' },
        { value: 'driver', label: 'As Driver' }
    ];

    var state = {
        role: '',
        status: '',
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        orders: [],
        loading: false,
        error: null
    };

    function renderSkeleton() {
        var cards = '';
        for (var i = 0; i < 3; i++) {
            cards += '<div class="layui-card" style="margin-bottom:12px;">' +
                '<div class="layui-card-body">' + RCUtil.skeleton(3) + '</div></div>';
        }
        return cards;
    }

    function buildStatusOption() {
        return STATUSES.map(function (s) {
            var sel = s.value === state.status ? ' selected' : '';
            return '<option value="' + s.value + '"' + sel + '>' +
                RCUtil.escapeHtml(s.label) + '</option>';
        }).join('');
    }

    function buildRoleTabs() {
        return '<div class="layui-btn-group rc-role-tabs">' +
            ROLES.map(function (r) {
                var cls = r.value === state.role ? ' layui-btn-normal' : ' layui-btn-primary';
                return '<button type="button" class="layui-btn layui-btn-sm' + cls +
                    '" data-role="' + r.value + '">' + RCUtil.escapeHtml(r.label) + '</button>';
            }).join('') + '</div>';
    }

    function primaryAction(order) {
        var user = RCAuth.getUser();
        var uid = user ? user.id : null;
        var s = order.status;
        if (s === 'pending_match' && order.passenger_id === uid) {
            return { label: 'Accept Match', cls: 'layui-btn-normal' };
        }
        if (s === 'accepted') {
            return { label: 'Start Trip', cls: 'layui-btn-warm' };
        }
        if (s === 'in_progress') {
            return { label: 'Complete Trip', cls: 'layui-btn' };
        }
        if (s === 'completed') {
            return { label: 'View Details', cls: 'layui-btn-primary' };
        }
        return { label: 'View', cls: 'layui-btn-primary' };
    }

    function otherPartyName(order) {
        var user = RCAuth.getUser();
        if (!user) return '';
        if (user.id === order.passenger_id) {
            return order.driver_info ? order.driver_info.name : 'Driver #' + order.driver_id;
        }
        return order.passenger_info ? order.passenger_info.name : 'Passenger #' + order.passenger_id;
    }

    function renderOrderCard(order) {
        var action = primaryAction(order);
        var title = order.listing_summary ? RCUtil.escapeHtml(order.listing_summary.title) : 'Order #' + order.id;
        var party = otherPartyName(order);

        var timestamps = '';
        if (order.created_at) {
            timestamps += '<span style="margin-right:16px;color:#999;font-size:12px;">Created: ' +
                RCUtil.formatRelative(order.created_at) + '</span>';
        }
        if (order.accepted_at) {
            timestamps += '<span style="margin-right:16px;color:#999;font-size:12px;">Accepted: ' +
                RCUtil.formatRelative(order.accepted_at) + '</span>';
        }
        if (order.completed_at) {
            timestamps += '<span style="color:#999;font-size:12px;">Completed: ' +
                RCUtil.formatRelative(order.completed_at) + '</span>';
        }

        return '<div class="layui-card rc-order-card" style="margin-bottom:12px;cursor:pointer;" data-order-id="' + order.id + '">' +
            '<div class="layui-card-body" style="padding:16px;">' +
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;">' +
            '<div style="flex:1;">' +
            '<h3 style="margin:0 0 6px 0;font-size:16px;">' + title + '</h3>' +
            '<div style="margin-bottom:6px;">' +
            '<span style="color:#666;">with <strong>' + RCUtil.escapeHtml(party) + '</strong></span>' +
            '<span style="margin-left:12px;">' + RCUtil.statusBadge(order.status) + '</span>' +
            '</div>' +
            '<div>' + timestamps + '</div>' +
            '</div>' +
            '<div style="flex-shrink:0;margin-left:16px;">' +
            '<button type="button" class="layui-btn layui-btn-sm ' + action.cls +
            ' rc-order-view-btn" data-order-id="' + order.id + '">' + action.label + '</button>' +
            '</div></div></div></div>';
    }

    function renderContent(container) {
        var html = '<div class="rc-orders-page" style="max-width:900px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">My Orders</h2>';

        // Filter bar
        html += '<div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap;">' +
            buildRoleTabs() +
            '<select id="rc-order-status-filter" lay-ignore style="height:32px;border:1px solid #e6e6e6;border-radius:2px;padding:0 8px;">' +
            buildStatusOption() +
            '</select></div>';

        // Order list
        html += '<div id="rc-orders-list">';
        if (state.loading) {
            html += renderSkeleton();
        } else if (state.error) {
            html += '<div class="layui-card"><div class="layui-card-body" style="padding:20px;color:#FF5722;">' +
                '<i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div></div>';
        } else if (state.orders.length === 0) {
            html += RCUtil.emptyState('No orders yet. Browse listings to find a ride or post your own trip request.');
        } else {
            state.orders.forEach(function (order) {
                html += renderOrderCard(order);
            });
        }
        html += '</div>';

        // Pagination
        html += '<div id="rc-orders-pagination" style="margin-top:16px;text-align:center;"></div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Role tabs
        var tabs = container.querySelectorAll('.rc-role-tabs button');
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.role = this.getAttribute('data-role');
                state.page = 1;
                loadOrders(container);
            });
        });

        // Status filter
        var statusSelect = container.querySelector('#rc-order-status-filter');
        if (statusSelect) {
            statusSelect.addEventListener('change', function () {
                state.status = this.value;
                state.page = 1;
                loadOrders(container);
            });
        }

        // Card click / View button
        container.querySelectorAll('.rc-order-card').forEach(function (card) {
            card.addEventListener('click', function (e) {
                var id = this.getAttribute('data-order-id');
                if (id) RCRouter.navigate('#/orders/' + id);
            });
        });

        // Pagination
        if (state.lastPage > 1) {
            RCUtil.renderPagination('rc-orders-pagination', {
                total: state.total,
                page: state.page,
                perPage: state.perPage,
                onChange: function (page) {
                    state.page = page;
                    loadOrders(container);
                }
            });
        }
    }

    function loadOrders(container) {
        state.loading = true;
        state.error = null;
        renderContent(container);

        var params = {
            page: state.page,
            per_page: state.perPage
        };
        if (state.role) params.role = state.role;
        if (state.status) params.status = state.status;

        RCApi.getOrders(params).then(function (envelope) {
            state.loading = false;
            state.orders = envelope.data || [];
            var meta = envelope.meta || {};
            state.total = meta.total || 0;
            state.page = meta.page || 1;
            state.lastPage = meta.last_page || 1;
            renderContent(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load orders';
            renderContent(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.role = (params && params.role) || '';
            state.status = (params && params.status) || '';
            state.page = 1;
            state.orders = [];
            state.error = null;
            loadOrders(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OrdersPage;
}
