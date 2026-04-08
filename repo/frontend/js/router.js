/**
 * RideCircle Hash-based Router
 */
var RCRouter = (function() {
    'use strict';

    var routes = {};
    var currentPage = null;

    // Public routes (no auth required)
    var publicRoutes = ['/login', '/register'];

    function matchRoute(path) {
        // Try exact match first
        if (routes[path]) {
            return { handler: routes[path], params: {} };
        }

        // Try pattern matching with :param placeholders
        for (var pattern in routes) {
            if (!routes.hasOwnProperty(pattern)) continue;
            if (pattern.indexOf(':') === -1) continue;

            var patternParts = pattern.split('/').filter(Boolean);
            var pathParts = path.split('/').filter(Boolean);

            if (patternParts.length !== pathParts.length) continue;

            var params = {};
            var matched = true;

            for (var i = 0; i < patternParts.length; i++) {
                if (patternParts[i].charAt(0) === ':') {
                    // Parameter placeholder
                    params[patternParts[i].substring(1)] = pathParts[i];
                } else if (patternParts[i] !== pathParts[i]) {
                    matched = false;
                    break;
                }
            }

            if (matched) {
                return { handler: routes[pattern], params: params };
            }
        }

        return null;
    }

    return {
        register: function(pattern, handler) {
            routes[pattern] = handler;
        },

        navigate: function(hash) {
            window.location.hash = hash;
        },

        resolve: function() {
            var hash = window.location.hash.slice(1) || '/';
            var container = document.getElementById('app-content');
            var header = document.getElementById('app-header');

            if (!container || !header) return;

            // Split path and query
            var qIndex = hash.indexOf('?');
            var path = qIndex !== -1 ? hash.substring(0, qIndex) : hash;
            var queryStr = qIndex !== -1 ? hash.substring(qIndex + 1) : '';
            var queryParams = {};
            if (queryStr) {
                queryStr.split('&').forEach(function(pair) {
                    var kv = pair.split('=');
                    queryParams[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || '');
                });
            }

            // Check if public route
            var isPublic = publicRoutes.indexOf(path) !== -1;

            // Auth guard
            if (!isPublic && !RCAuth.isLoggedIn()) {
                window.location.hash = '#/login';
                return;
            }

            // If logged in and visiting login/register, redirect to dashboard
            if (isPublic && RCAuth.isLoggedIn()) {
                window.location.hash = '#/';
                return;
            }

            // Show/hide header for auth pages
            header.style.display = isPublic ? 'none' : '';
            var body = document.getElementById('app-body');
            if (body) {
                body.className = isPublic ? 'rc-body rc-body-auth' : 'rc-body rc-body-app';
            }

            // Match route
            var match = matchRoute(path);

            if (match) {
                // Merge query params with route params
                var allParams = Object.assign({}, match.params, queryParams);
                currentPage = path;

                // Scroll to top
                window.scrollTo(0, 0);

                // Clear field errors
                if (typeof RCUtil !== 'undefined' && RCUtil.clearFieldErrors) {
                    RCUtil.clearFieldErrors();
                }

                try {
                    match.handler(container, allParams);
                } catch (err) {
                    console.error('Route handler error:', err);
                    container.innerHTML =
                        '<div class="rc-empty-state">' +
                        '<div class="rc-empty-icon" style="color:#FF5722;">\u26A0</div>' +
                        '<div class="rc-empty-text">Something went wrong loading this page.</div>' +
                        '<div class="rc-empty-action"><a href="#/" class="layui-btn layui-btn-normal">Go to Dashboard</a></div>' +
                        '</div>';
                }
            } else {
                // 404
                container.innerHTML =
                    '<div class="rc-empty-state">' +
                    '<div class="rc-empty-icon">404</div>' +
                    '<div class="rc-empty-text">Page not found</div>' +
                    '<div class="rc-empty-action"><a href="#/" class="layui-btn layui-btn-normal">Go to Dashboard</a></div>' +
                    '</div>';
            }
        },

        start: function() {
            window.addEventListener('hashchange', function() {
                RCRouter.resolve();
            });
            RCRouter.resolve();
        },

        getCurrentPage: function() {
            return currentPage;
        }
    };
})();
