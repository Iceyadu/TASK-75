/**
 * RideCircle API Client
 * Fetch-based REST client for the ThinkPHP backend
 */
var RCApi = (function() {
    'use strict';

    var BASE_URL = '/api';

    function request(method, path, data, options) {
        options = options || {};
        var url = BASE_URL + path;
        var fetchOpts = {
            method: method.toUpperCase(),
            credentials: 'include',
            headers: {}
        };

        // Add auth token if stored
        var token = localStorage.getItem('rc_api_token');
        if (token) {
            fetchOpts.headers['Authorization'] = 'Bearer ' + token;
        }

        // Handle query params for GET
        if (method === 'GET' && data) {
            var qs = RCUtil.buildQuery(data);
            url += qs;
        }

        // Handle body for POST/PUT
        if ((method === 'POST' || method === 'PUT' || method === 'PATCH') && data) {
            if (data instanceof FormData) {
                // Don't set Content-Type for FormData — browser sets boundary
                fetchOpts.body = data;
            } else {
                fetchOpts.headers['Content-Type'] = 'application/json';
                fetchOpts.body = JSON.stringify(data);
            }
        }

        return fetch(url, fetchOpts).then(function(response) {
            // Handle non-JSON responses
            var contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                if (!response.ok) {
                    throw { status: response.status, message: 'Server error (' + response.status + ')' };
                }
                return response.text().then(function(text) {
                    return { data: text };
                });
            }

            return response.json().then(function(json) {
                // ThinkPHP convention: code === 0 means success
                // Return full envelope so callers can access data, meta, and message
                if (json.code === 0 || json.code === 200 || response.ok) {
                    return json;
                }

                // Validation errors
                if (json.code === 40001 || json.code === 422) {
                    var err = new Error(json.msg || json.message || 'Validation error');
                    err.errors = json.errors || json.data || {};
                    err.code = json.code;
                    throw err;
                }

                // Auth errors
                if (response.status === 401 || json.code === 401) {
                    localStorage.removeItem('rc_api_token');
                    if (window.location.hash !== '#/login') {
                        window.location.hash = '#/login';
                    }
                    throw { status: 401, message: 'Session expired. Please log in again.' };
                }

                // Rate limiting
                if (response.status === 429 || json.code === 429) {
                    layui.layer.msg('Too many requests. Please wait a moment.', { icon: 2, time: 3000 });
                    throw { status: 429, message: 'Rate limited' };
                }

                // Forbidden
                if (response.status === 403 || json.code === 403) {
                    throw { status: 403, message: json.msg || json.message || 'You do not have permission for this action.' };
                }

                // Not found
                if (response.status === 404 || json.code === 404) {
                    throw { status: 404, message: json.msg || json.message || 'Resource not found.' };
                }

                // General error
                throw { status: response.status, code: json.code, message: json.msg || json.message || 'An error occurred.' };
            });
        }).catch(function(err) {
            // Network errors
            if (err instanceof TypeError && err.message.includes('fetch')) {
                layui.layer.msg('Network error. Please check your connection.', { icon: 2, time: 3000 });
                throw { status: 0, message: 'Network error' };
            }
            throw err;
        });
    }

    return {
        get: function(path, params) { return request('GET', path, params); },
        post: function(path, data) { return request('POST', path, data); },
        put: function(path, data) { return request('PUT', path, data); },
        del: function(path) { return request('DELETE', path); },
        upload: function(path, formData) { return request('POST', path, formData); },

        // ---- Auth ----
        login: function(email, password, organizationCode) {
            return this.post('/auth/login', { email: email, password: password, organization_code: organizationCode });
        },
        register: function(data) { return this.post('/auth/register', data); },
        logout: function() { return this.post('/auth/logout'); },
        me: function() { return this.get('/auth/me'); },

        // ---- Tokens ----
        getTokens: function() { return this.get('/auth/tokens'); },
        createToken: function(data) { return this.post('/auth/tokens', data); },
        revokeToken: function(id) { return this.del('/auth/tokens/' + id); },
        rotateToken: function(id) { return this.post('/auth/tokens/' + id + '/rotate'); },

        // ---- Listings ----
        getListings: function(params) { return this.get('/listings', params); },
        getListing: function(id) { return this.get('/listings/' + id); },
        createListing: function(data) { return this.post('/listings', data); },
        updateListing: function(id, data) { return this.put('/listings/' + id, data); },
        deleteListing: function(id) { return this.del('/listings/' + id); },
        publishListing: function(id) { return this.post('/listings/' + id + '/publish'); },
        unpublishListing: function(id, data) { return this.post('/listings/' + id + '/unpublish', data); },
        bulkClose: function(ids, reason) { return this.post('/listings/bulk-close', { listing_ids: ids, reason: reason }); },
        getVersions: function(id) { return this.get('/listings/' + id + '/versions'); },
        getVersion: function(id, v) { return this.get('/listings/' + id + '/versions/' + v); },
        diffVersions: function(id, v1, v2) { return this.get('/listings/' + id + '/versions/' + v1 + '/diff/' + v2); },

        acceptListing: function(id) { return this.post('/orders', { listing_id: id }); },
        flagListing: function(id, data) { return this.post('/listings/' + id + '/flag', data); },

        // ---- Search ----
        getSuggestions: function(q) { return this.get('/search/suggestions', { q: q }); },
        getDidYouMean: function(q) { return this.get('/search/did-you-mean', { q: q }); },

        // ---- Orders ----
        getOrders: function(params) { return this.get('/orders', params); },
        getOrder: function(id) { return this.get('/orders/' + id); },
        createOrder: function(data) { return this.post('/orders', data); },
        acceptOrder: function(id) { return this.post('/orders/' + id + '/accept'); },
        startOrder: function(id) { return this.post('/orders/' + id + '/start'); },
        completeOrder: function(id) { return this.post('/orders/' + id + '/complete'); },
        cancelOrder: function(id, data) { return this.post('/orders/' + id + '/cancel', data); },
        disputeOrder: function(id, data) { return this.post('/orders/' + id + '/dispute', data); },
        resolveOrder: function(id, data) { return this.post('/orders/' + id + '/resolve', data); },

        // ---- Reviews ----
        getReviews: function(params) { return this.get('/reviews', params); },
        createReview: function(formData) { return this.upload('/reviews', formData); },
        updateReview: function(id, data) { return this.put('/reviews/' + id, data); },
        deleteReview: function(id) { return this.del('/reviews/' + id); },

        // ---- Moderation ----
        getModerationQueue: function(params) { return this.get('/moderation/queue', params); },
        approveModeration: function(id) { return this.post('/moderation/queue/' + id + '/approve'); },
        rejectModeration: function(id, reason) { return this.post('/moderation/queue/' + id + '/reject', { reason: reason }); },
        escalateModeration: function(id) { return this.post('/moderation/queue/' + id + '/escalate'); },

        // ---- Governance ----
        getQualityMetrics: function(params) { return this.get('/governance/quality-metrics', params); },
        getLineage: function(params) { return this.get('/governance/lineage', params); },
        getEvents: function(params) { return this.get('/governance/events', params); },

        // ---- Audit ----
        getAuditLogs: function(params) { return this.get('/audit/logs', params); },

        // ---- Org ----
        getOrgSettings: function() { return this.get('/org/settings'); },
        updateOrgSettings: function(data) { return this.put('/org/settings', data); },

        // ---- Users ----
        getUsers: function(params) { return this.get('/users', params); },
        getUser: function(id) { return this.get('/users/' + id); },
        updateUserRoles: function(id, roles) { return this.put('/users/' + id + '/roles', { roles: roles }); },
        disableUser: function(id) { return this.post('/users/' + id + '/disable'); },
        enableUser: function(id) { return this.post('/users/' + id + '/enable'); }
    };
})();
