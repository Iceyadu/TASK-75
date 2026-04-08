/**
 * RideCircle Auth State Management
 */
var RCAuth = (function() {
    'use strict';

    var currentUser = null;

    // Permission map: role -> permissions
    var rolePermissions = {
        admin: [
            'manage_users', 'manage_settings', 'manage_governance', 'view_audit',
            'moderate_content', 'manage_listings', 'manage_orders', 'manage_reviews',
            'view_lineage', 'view_metrics', 'bulk_actions'
        ],
        moderator: [
            'moderate_content', 'view_listings', 'view_orders', 'view_reviews',
            'view_metrics'
        ],
        user: [
            'create_listing', 'view_listings', 'create_order', 'view_orders',
            'create_review', 'view_reviews', 'manage_own_listings', 'manage_own_orders'
        ]
    };

    return {
        getUser: function() { return currentUser; },

        setUser: function(user) { currentUser = user; },

        isLoggedIn: function() { return currentUser !== null; },

        hasRole: function(role) {
            return currentUser && currentUser.roles && currentUser.roles.indexOf(role) !== -1;
        },

        isAdmin: function() { return this.hasRole('admin'); },

        isModerator: function() { return this.hasRole('moderator') || this.isAdmin(); },

        hasPermission: function(perm) {
            if (!currentUser || !currentUser.roles) return false;
            for (var i = 0; i < currentUser.roles.length; i++) {
                var perms = rolePermissions[currentUser.roles[i]];
                if (perms && perms.indexOf(perm) !== -1) return true;
            }
            return false;
        },

        // Check auth on app start
        init: function() {
            return RCApi.me().then(function(envelope) {
                currentUser = (envelope.data && envelope.data.user) || envelope.data || envelope;
                return currentUser;
            }).catch(function() {
                currentUser = null;
                return null;
            });
        },

        login: function(email, password, organizationCode) {
            return RCApi.login(email, password, organizationCode).then(function(envelope) {
                var data = envelope.data || envelope;
                currentUser = data.user || data;
                if (!currentUser.roles && data.roles) {
                    currentUser.roles = data.roles;
                }
                // Store token if returned
                if (data.token) {
                    localStorage.setItem('rc_api_token', data.token);
                }
                return currentUser;
            });
        },

        logout: function() {
            return RCApi.logout().then(function() {
                currentUser = null;
                localStorage.removeItem('rc_api_token');
            }).catch(function() {
                // Clear even on error
                currentUser = null;
                localStorage.removeItem('rc_api_token');
            });
        }
    };
})();
