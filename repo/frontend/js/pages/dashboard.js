/**
 * RideCircle Dashboard Page
 */
var DashboardPage = {
    render: function(container) {
        var user = RCAuth.getUser();
        var userName = user ? (user.name || 'User') : 'User';

        // Show loading state
        container.innerHTML =
            '<div class="rc-animate-in">' +
            '<h2 style="margin-bottom:5px;">Welcome back, ' + RCUtil.escapeHtml(userName) + '</h2>' +
            '<p class="layui-word-aux" style="margin-bottom:20px;">Here\'s what\'s happening in your carpool network.</p>' +
            '<div class="layui-row" id="dash-stats">' + RCUtil.skeleton(4) + '</div>' +
            '<div class="layui-row" style="margin-top:20px;">' +
            '  <div class="layui-col-md8">' +
            '    <div class="layui-card"><div class="layui-card-header">Recent Activity</div>' +
            '    <div class="layui-card-body" id="dash-activity">' + RCUtil.skeleton(3) + '</div></div>' +
            '  </div>' +
            '  <div class="layui-col-md4">' +
            '    <div class="layui-card"><div class="layui-card-header">Quick Actions</div>' +
            '    <div class="layui-card-body" id="dash-actions"></div></div>' +
            '  </div>' +
            '</div>' +
            '</div>';

        // Render quick actions immediately
        var actionsHtml = '<div class="rc-quick-actions" style="flex-direction:column;">';
        actionsHtml += '<a href="#/listings/create" class="layui-btn layui-btn-normal" style="margin:0 0 8px 0;text-align:center;">+ Create Listing</a>';
        actionsHtml += '<a href="#/listings" class="layui-btn layui-btn-primary" style="margin:0 0 8px 0;text-align:center;">Browse Listings</a>';
        actionsHtml += '<a href="#/orders" class="layui-btn layui-btn-primary" style="margin:0 0 8px 0;text-align:center;">My Orders</a>';
        if (RCAuth.isModerator()) {
            actionsHtml += '<a href="#/moderation" class="layui-btn layui-btn-warm" style="margin:0 0 8px 0;text-align:center;">Moderation Queue</a>';
        }
        if (RCAuth.isAdmin()) {
            actionsHtml += '<a href="#/admin/governance" class="layui-btn" style="margin:0;text-align:center;">Governance</a>';
        }
        actionsHtml += '</div>';
        var actionsEl = document.getElementById('dash-actions');
        if (actionsEl) actionsEl.innerHTML = actionsHtml;

        // Fetch stats
        DashboardPage._loadStats();
        DashboardPage._loadActivity();
    },

    _loadStats: function() {
        var statsContainer = document.getElementById('dash-stats');
        if (!statsContainer) return;

        // Fetch multiple endpoints in parallel
        var promises = [
            RCApi.getListings({ status: 'active', limit: 0 }).catch(function() { return { total: 0 }; }),
            RCApi.getOrders({ status: 'pending', limit: 0 }).catch(function() { return { total: 0 }; }),
            RCApi.getOrders({ status: 'completed', limit: 0 }).catch(function() { return { total: 0 }; })
        ];

        if (RCAuth.isModerator()) {
            promises.push(RCApi.getModerationQueue({ status: 'pending', limit: 0 }).catch(function() { return { total: 0 }; }));
        }
        if (RCAuth.isAdmin()) {
            promises.push(RCApi.getUsers({ limit: 0 }).catch(function() { return { total: 0 }; }));
            promises.push(RCApi.getQualityMetrics({}).catch(function() { return { quality_score: 0 }; }));
        }

        Promise.all(promises).then(function(results) {
            var r0 = results[0] && results[0].meta ? results[0].meta : results[0] || {};
            var r1 = results[1] && results[1].meta ? results[1].meta : results[1] || {};
            var r2 = results[2] && results[2].meta ? results[2].meta : results[2] || {};
            var activeListings = r0.total || 0;
            var pendingOrders = r1.total || 0;
            var completedTrips = r2.total || 0;

            var html = '';
            html += DashboardPage._statCard('blue', '\u{1F697}', activeListings, 'Active Listings');
            html += DashboardPage._statCard('orange', '\u231B', pendingOrders, 'Pending Orders');
            html += DashboardPage._statCard('green', '\u2713', completedTrips, 'Completed Trips');

            var idx = 3;
            if (RCAuth.isModerator() && results[idx]) {
                var modMeta = results[idx].meta ? results[idx].meta : results[idx];
                var modCount = modMeta.total || 0;
                html += DashboardPage._statCard('red', '\u2691', modCount, 'Pending Moderation');
                idx++;
            }
            if (RCAuth.isAdmin()) {
                if (results[idx]) {
                    var usersMeta = results[idx].meta ? results[idx].meta : results[idx];
                    html += DashboardPage._statCard('purple', '\u263A', usersMeta.total || 0, 'Total Users');
                    idx++;
                }
                if (results[idx]) {
                    var qualityData = results[idx].data ? results[idx].data : results[idx];
                    var score = qualityData.quality_score || qualityData.score || 0;
                    html += DashboardPage._statCard('teal', '\u2605', score, 'Quality Score');
                }
            }

            statsContainer.innerHTML = html;
        }).catch(function() {
            statsContainer.innerHTML = DashboardPage._statCard('blue', '\u{1F697}', '—', 'Active Listings') +
                DashboardPage._statCard('orange', '\u231B', '—', 'Pending Orders') +
                DashboardPage._statCard('green', '\u2713', '—', 'Completed Trips');
        });
    },

    _statCard: function(color, icon, number, label) {
        return '<div class="layui-col-md3 layui-col-xs6" style="margin-bottom:15px;">' +
            '<div class="rc-stat-card">' +
            '<div class="rc-stat-icon ' + color + '">' + icon + '</div>' +
            '<div class="rc-stat-info">' +
            '<div class="rc-stat-number">' + number + '</div>' +
            '<div class="rc-stat-label">' + label + '</div>' +
            '</div></div></div>';
    },

    _loadActivity: function() {
        var activityEl = document.getElementById('dash-activity');
        if (!activityEl) return;

        // Try fetching recent events
        RCApi.getEvents({ limit: 5 }).then(function(envelope) {
            var data = envelope.data || envelope;
            var events = data.items || data.list || data || [];
            if (!Array.isArray(events) || events.length === 0) {
                activityEl.innerHTML = DashboardPage._emptyActivity();
                return;
            }
            var html = '';
            events.forEach(function(evt) {
                html += '<div class="rc-activity-item">';
                html += '<div class="rc-activity-dot"></div>';
                html += '<div class="rc-activity-text">' + RCUtil.escapeHtml(evt.description || evt.message || evt.action || 'Activity') + '</div>';
                html += '<div class="rc-activity-time">' + RCUtil.formatRelative(evt.created_at || evt.timestamp) + '</div>';
                html += '</div>';
            });
            activityEl.innerHTML = html;
        }).catch(function() {
            activityEl.innerHTML = DashboardPage._emptyActivity();
        });
    },

    _emptyActivity: function() {
        return '<div class="rc-empty-state" style="padding:30px 10px;">' +
            '<div class="rc-empty-icon" style="font-size:36px;">\u{1F4DD}</div>' +
            '<div class="rc-empty-text" style="font-size:14px;">No recent activity yet.<br>Start by creating a listing or browsing available rides!</div>' +
            '</div>';
    }
};
