/**
 * RideCircle Application Entry Point
 * Registers routes, builds navigation, and initializes auth.
 */
(function() {
    'use strict';

    // Register all routes
    RCRouter.register('/login', LoginPage.render);
    RCRouter.register('/register', RegisterPage.render);
    RCRouter.register('/', DashboardPage.render);
    RCRouter.register('/listings', ListingsPage.render);
    RCRouter.register('/listings/create', ListingFormPage.renderCreate);
    RCRouter.register('/listings/:id', ListingDetailPage.render);
    RCRouter.register('/listings/:id/edit', ListingFormPage.renderEdit);
    RCRouter.register('/listings/:id/versions', ListingVersionsPage.render);
    RCRouter.register('/my/listings', MyListingsPage.render);
    RCRouter.register('/orders', OrdersPage.render);
    RCRouter.register('/orders/:id', OrderDetailPage.render);
    RCRouter.register('/orders/:id/review', ReviewFormPage.render);
    RCRouter.register('/reviews', ReviewsPage.render);
    RCRouter.register('/profile', ProfilePage.render);
    RCRouter.register('/profile/tokens', TokensPage.render);
    RCRouter.register('/moderation', ModerationPage.render);
    RCRouter.register('/moderation/:id', ModerationDetailPage.render);
    RCRouter.register('/admin/users', AdminUsersPage.render);
    RCRouter.register('/admin/settings', AdminSettingsPage.render);
    RCRouter.register('/admin/governance', AdminGovernancePage.render);
    RCRouter.register('/admin/governance/lineage', AdminLineagePage.render);
    RCRouter.register('/admin/audit', AdminAuditPage.render);

    // Navigation links with role-based visibility
    var navLinks = [
        { text: 'Dashboard',  hash: '#/',                roles: ['user', 'moderator', 'admin'] },
        { text: 'Listings',   hash: '#/listings',        roles: ['user', 'moderator', 'admin'] },
        { text: 'My Listings', hash: '#/my/listings',    roles: ['user', 'moderator', 'admin'] },
        { text: 'Orders',     hash: '#/orders',          roles: ['user', 'moderator', 'admin'] },
        { text: 'Reviews',    hash: '#/reviews',         roles: ['user', 'moderator', 'admin'] },
        { text: 'Moderation', hash: '#/moderation',      roles: ['moderator', 'admin'] },
        { text: 'Users',      hash: '#/admin/users',     roles: ['admin'] },
        { text: 'Settings',   hash: '#/admin/settings',  roles: ['admin'] },
        { text: 'Governance',  hash: '#/admin/governance', roles: ['admin'] },
        { text: 'Audit Logs', hash: '#/admin/audit',     roles: ['admin'] }
    ];

    // Build navigation based on user role
    function buildNav() {
        var nav = document.getElementById('nav-menu');
        var user = RCAuth.getUser();
        if (!user || !nav) return;

        var userRoles = user.roles || ['user'];
        var currentHash = window.location.hash || '#/';

        var html = '';
        navLinks.forEach(function(link) {
            var hasRole = link.roles.some(function(r) {
                return userRoles.indexOf(r) !== -1;
            });
            if (hasRole) {
                // Check active state — match on base path
                var active = currentHash === link.hash ||
                    (link.hash !== '#/' && currentHash.indexOf(link.hash) === 0);
                html += '<li class="layui-nav-item' + (active ? ' layui-this' : '') + '">';
                html += '<a href="' + link.hash + '">' + link.text + '</a></li>';
            }
        });
        nav.innerHTML = html;

        // Update user menu name
        var nameEl = document.getElementById('user-menu-name');
        if (nameEl) nameEl.textContent = user.name || user.email || 'User';
    }

    // Logout handler
    var logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            layui.layer.confirm('Are you sure you want to log out?', {
                title: 'Logout',
                btn: ['Log Out', 'Cancel']
            }, function(index) {
                layui.layer.close(index);
                RCAuth.logout().then(function() {
                    layui.layer.msg('You have been logged out.', { icon: 1 });
                    RCRouter.navigate('#/login');
                }).catch(function() {
                    RCRouter.navigate('#/login');
                });
            });
        });
    }

    // Initialize: check auth state, then start router
    RCAuth.init().then(function(user) {
        if (user) {
            buildNav();
        }
        RCRouter.start();
    }).catch(function() {
        // Auth check failed — start router anyway (will redirect to login)
        RCRouter.start();
    });

    // Rebuild nav on hash change (to update active state)
    window.addEventListener('hashchange', function() {
        if (RCAuth.isLoggedIn()) {
            buildNav();
        }
    });
})();
