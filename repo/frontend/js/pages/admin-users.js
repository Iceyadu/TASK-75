/**
 * admin-users.js - User Management Page
 * Admin only. User table with roles, status, search, edit roles, enable/disable.
 */
var AdminUsersPage = (function () {
    'use strict';

    var ROLE_COLORS = {
        admin: '#FF5722',
        moderator: '#FF9800',
        user: '#1E9FFF'
    };

    var state = {
        users: [],
        search: '',
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
        loading: false,
        error: null,
        actionLoading: false
    };

    function renderRoleBadges(user) {
        var roles = user.badges || user.roles || [];
        if (!Array.isArray(roles)) roles = [];
        if (roles.length === 0) return '<span style="color:#999;font-size:12px;">No roles</span>';
        return roles.map(function (r) {
            var name = typeof r === 'object' ? (r.name || r.slug || '') : r;
            var color = ROLE_COLORS[name] || '#999';
            return '<span style="background:' + color + ';color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-right:4px;">' +
                RCUtil.escapeHtml(name) + '</span>';
        }).join('');
    }

    function renderStatusBadge(status) {
        if (status === 'active') {
            return '<span style="background:#e8f5e9;color:#2e7d32;font-size:11px;padding:2px 8px;border-radius:10px;">Active</span>';
        }
        return '<span style="background:#ffebee;color:#c62828;font-size:11px;padding:2px 8px;border-radius:10px;">Disabled</span>';
    }

    function renderRow(user) {
        var toggleLabel = user.status === 'active' ? 'Disable' : 'Enable';
        var toggleCls = user.status === 'active' ? 'layui-btn-danger' : 'layui-btn-normal';

        return '<tr>' +
            '<td>' + RCUtil.escapeHtml(user.name || '') + '</td>' +
            '<td style="font-size:13px;">' + RCUtil.escapeHtml(user.email || '') + '</td>' +
            '<td>' + renderRoleBadges(user) + '</td>' +
            '<td>' + renderStatusBadge(user.status) + '</td>' +
            '<td style="font-size:12px;color:#999;">' + (user.created_at ? RCUtil.formatDate(user.created_at) : '') + '</td>' +
            '<td style="white-space:nowrap;">' +
            '<button type="button" class="layui-btn layui-btn-xs layui-btn-primary rc-edit-roles" data-user-id="' + user.id + '">Edit Roles</button> ' +
            '<button type="button" class="layui-btn layui-btn-xs ' + toggleCls + ' rc-toggle-status" data-user-id="' + user.id + '" data-status="' + user.status + '">' + toggleLabel + '</button>' +
            '</td></tr>';
    }

    function renderContent(container) {
        if (!RCAuth.isAdmin()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">Only administrators can manage users.</p></div>';
            return;
        }

        var html = '<div style="max-width:1100px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">User Management</h2>';

        // Search bar
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;">' +
            '<input type="text" id="rc-user-search" class="layui-input" placeholder="Search by name or email..." ' +
            'value="' + RCUtil.escapeHtml(state.search) + '" style="height:36px;max-width:400px;">' +
            '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="rc-user-search-btn">Search</button>' +
            '</div>';

        // Table
        html += '<div style="overflow-x:auto;">';
        if (state.loading) {
            html += RCUtil.skeleton(5);
        } else if (state.error) {
            html += '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>';
        } else if (state.users.length === 0) {
            html += RCUtil.emptyState('No users found.');
        } else {
            html += '<table class="layui-table" style="margin:0;">' +
                '<thead><tr>' +
                '<th>Name</th>' +
                '<th>Email</th>' +
                '<th>Roles</th>' +
                '<th>Status</th>' +
                '<th>Registered</th>' +
                '<th>Actions</th>' +
                '</tr></thead><tbody>';
            state.users.forEach(function (user) {
                html += renderRow(user);
            });
            html += '</tbody></table>';
        }
        html += '</div>';

        // Pagination
        html += '<div id="rc-users-pagination" style="margin-top:16px;text-align:center;"></div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Search
        var searchBtn = container.querySelector('#rc-user-search-btn');
        var searchInput = container.querySelector('#rc-user-search');
        if (searchBtn) {
            searchBtn.addEventListener('click', function () {
                state.search = searchInput ? searchInput.value.trim() : '';
                state.page = 1;
                loadUsers(container);
            });
        }
        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    state.search = this.value.trim();
                    state.page = 1;
                    loadUsers(container);
                }
            });
        }

        // Edit roles
        container.querySelectorAll('.rc-edit-roles').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var userId = this.getAttribute('data-user-id');
                openEditRolesModal(userId, container);
            });
        });

        // Toggle status
        container.querySelectorAll('.rc-toggle-status').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var userId = this.getAttribute('data-user-id');
                var currentStatus = this.getAttribute('data-status');
                handleToggleStatus(userId, currentStatus, container);
            });
        });

        // Pagination
        if (state.lastPage > 1) {
            RCUtil.renderPagination('rc-users-pagination', {
                total: state.total,
                page: state.page,
                perPage: state.perPage,
                onChange: function (page) {
                    state.page = page;
                    loadUsers(container);
                }
            });
        }
    }

    function openEditRolesModal(userId, container) {
        var user = null;
        state.users.forEach(function (u) {
            if (String(u.id) === String(userId)) user = u;
        });
        if (!user) return;

        var currentRoles = (user.badges || user.roles || []).map(function (r) {
            return typeof r === 'object' ? (r.name || r.slug || '') : r;
        });

        var allRoles = ['user', 'moderator', 'admin'];
        var checkboxes = allRoles.map(function (role) {
            var checked = currentRoles.indexOf(role) !== -1 ? ' checked' : '';
            return '<div style="margin-bottom:8px;">' +
                '<label style="cursor:pointer;">' +
                '<input type="checkbox" class="rc-role-checkbox" value="' + role + '"' + checked + '> ' +
                '<span style="margin-left:4px;">' + role.charAt(0).toUpperCase() + role.slice(1) + '</span>' +
                '</label></div>';
        }).join('');

        layui.layer.open({
            type: 1,
            title: 'Edit Roles - ' + RCUtil.escapeHtml(user.name),
            area: ['360px', '260px'],
            content: '<div style="padding:20px;">' + checkboxes + '</div>',
            btn: ['Save', 'Cancel'],
            yes: function (layerIdx) {
                var selected = [];
                document.querySelectorAll('.rc-role-checkbox').forEach(function (cb) {
                    if (cb.checked) selected.push(cb.value);
                });
                layui.layer.close(layerIdx);
                saveRoles(userId, selected, container);
            }
        });
    }

    function saveRoles(userId, roles, container) {
        state.actionLoading = true;
        RCApi.updateUserRoles(userId, { roles: roles }).then(function (res) {
            state.actionLoading = false;
            layui.layer.msg(res.message || 'Roles updated', { icon: 1 });
            loadUsers(container);
        }).catch(function (err) {
            state.actionLoading = false;
            layui.layer.msg((err && err.message) ? err.message : 'Failed to update roles', { icon: 2 });
        });
    }

    function handleToggleStatus(userId, currentStatus, container) {
        var action = currentStatus === 'active' ? 'disable' : 'enable';
        var msg = currentStatus === 'active'
            ? 'Disable this user? They will not be able to log in.'
            : 'Enable this user? They will regain access.';

        layui.layer.confirm(msg, { title: 'Confirm ' + action.charAt(0).toUpperCase() + action.slice(1) }, function (idx) {
            layui.layer.close(idx);
            state.actionLoading = true;

            var apiCall = action === 'disable' ? RCApi.disableUser(userId) : RCApi.enableUser(userId);
            apiCall.then(function (res) {
                state.actionLoading = false;
                layui.layer.msg(res.message || 'Success', { icon: 1 });
                loadUsers(container);
            }).catch(function (err) {
                state.actionLoading = false;
                layui.layer.msg((err && err.message) ? err.message : 'Action failed', { icon: 2 });
            });
        });
    }

    function loadUsers(container) {
        state.loading = true;
        state.error = null;
        renderContent(container);

        var params = { page: state.page, per_page: state.perPage };
        if (state.search) params.search = state.search;

        RCApi.getUsers(params).then(function (res) {
            state.loading = false;
            state.users = res.data || [];
            var meta = res.meta || {};
            state.total = meta.total || 0;
            state.page = meta.page || 1;
            state.lastPage = meta.last_page || 1;
            renderContent(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load users';
            renderContent(container);
            bindEvents(container);
        });
    }

    return {
        render: function (container, params) {
            state.search = '';
            state.page = 1;
            state.users = [];
            state.error = null;

            if (!RCAuth.isAdmin()) {
                renderContent(container);
                return;
            }
            loadUsers(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminUsersPage;
}
