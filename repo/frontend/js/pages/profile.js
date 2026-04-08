/**
 * RideCircle Profile Page
 */
var ProfilePage = {
    render: function(container) {
        var user = RCAuth.getUser();
        if (!user) {
            container.innerHTML = RCUtil.emptyState('Unable to load profile.', 'Go to Dashboard', '#/');
            return;
        }

        var initials = (user.name || 'U').split(' ').map(function(n) { return n.charAt(0).toUpperCase(); }).join('').substring(0, 2);
        var rolesHtml = '';
        if (user.roles && user.roles.length) {
            user.roles.forEach(function(r) {
                rolesHtml += '<span class="rc-tag-chip">' + RCUtil.escapeHtml(r) + '</span>';
            });
        }

        container.innerHTML =
            '<div class="rc-animate-in">' +
            RCUtil.breadcrumb([{ text: 'Dashboard', hash: '#/' }, { text: 'Profile' }]) +
            '<div class="layui-card">' +
            '  <div class="layui-card-header">My Profile</div>' +
            '  <div class="layui-card-body">' +
            '    <div class="rc-profile-header">' +
            '      <div class="rc-profile-avatar">' + initials + '</div>' +
            '      <div>' +
            '        <div class="rc-profile-name">' + RCUtil.escapeHtml(user.name || '') + '</div>' +
            '        <div class="rc-profile-email">' + RCUtil.escapeHtml(user.email || '') + '</div>' +
            '        <div style="margin-top:6px;">' + rolesHtml + '</div>' +
            '      </div>' +
            '    </div>' +
            '    <hr>' +
            '    <div class="layui-row">' +
            '      <div class="layui-col-md6">' +
            '        <p><strong>Organization:</strong> ' + RCUtil.escapeHtml(user.organization || user.org_name || '—') + '</p>' +
            '        <p><strong>Member Since:</strong> ' + RCUtil.formatDate(user.created_at || user.joined_at) + '</p>' +
            '      </div>' +
            '      <div class="layui-col-md6" id="profile-stats">' +
            '        <p><strong>Trips as Passenger:</strong> <span id="stat-passenger">—</span></p>' +
            '        <p><strong>Trips as Driver:</strong> <span id="stat-driver">—</span></p>' +
            '        <p><strong>Average Rating:</strong> <span id="stat-rating">—</span></p>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>' +
            '<div class="layui-card" style="margin-top:20px;">' +
            '  <div class="layui-card-header">Edit Name</div>' +
            '  <div class="layui-card-body">' +
            '    <form id="profile-edit-form" class="layui-form">' +
            '      <div class="layui-form-item">' +
            '        <label class="layui-form-label">Name</label>' +
            '        <div class="layui-input-block">' +
            '          <input type="text" name="name" class="layui-input" value="' + RCUtil.escapeHtml(user.name || '') + '" required>' +
            '        </div>' +
            '      </div>' +
            '      <div class="layui-form-item">' +
            '        <div class="layui-input-block">' +
            '          <button type="submit" class="layui-btn layui-btn-normal" id="profile-save-btn">Save Changes</button>' +
            '        </div>' +
            '      </div>' +
            '      <div class="rc-form-error" id="profile-error" style="display:none;"></div>' +
            '    </form>' +
            '  </div>' +
            '</div>' +
            '</div>';

        // Load stats
        ProfilePage._loadStats();

        // Handle edit form
        var editForm = document.getElementById('profile-edit-form');
        var saveBtn = document.getElementById('profile-save-btn');
        var errorDiv = document.getElementById('profile-error');

        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            errorDiv.style.display = 'none';
            var newName = editForm.name.value.trim();
            if (!newName) {
                errorDiv.textContent = 'Name cannot be empty.';
                errorDiv.style.display = 'block';
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            RCApi.put('/auth/profile', { name: newName }).then(function(envelope) {
                var updatedUser = (envelope.data && envelope.data.user) || envelope.data || envelope;
                if (updatedUser.name) {
                    var current = RCAuth.getUser();
                    current.name = updatedUser.name;
                    RCAuth.setUser(current);
                    // Update header
                    var nameEl = document.getElementById('user-menu-name');
                    if (nameEl) nameEl.textContent = updatedUser.name;
                }
                layui.layer.msg('Profile updated!', { icon: 1 });
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }).catch(function(err) {
                errorDiv.textContent = err.message || 'Failed to update profile.';
                errorDiv.style.display = 'block';
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            });
        });
    },

    _loadStats: function() {
        // Fetch user stats
        RCApi.get('/auth/me/stats').then(function(envelope) {
            var stats = (envelope.data && envelope.data.stats) || envelope.data || envelope;
            var passengerEl = document.getElementById('stat-passenger');
            var driverEl = document.getElementById('stat-driver');
            var ratingEl = document.getElementById('stat-rating');
            if (passengerEl) passengerEl.textContent = stats.trips_as_passenger || 0;
            if (driverEl) driverEl.textContent = stats.trips_as_driver || 0;
            if (ratingEl) {
                var avg = stats.average_rating || 0;
                ratingEl.innerHTML = avg > 0 ? RCUtil.starsHtml(avg) + ' (' + avg.toFixed(1) + ')' : 'No ratings yet';
            }
        }).catch(function() {
            // Leave defaults
        });
    }
};
