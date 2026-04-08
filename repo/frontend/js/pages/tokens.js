/**
 * RideCircle API Tokens Management Page
 */
var TokensPage = {
    render: function(container) {
        container.innerHTML =
            '<div class="rc-animate-in">' +
            RCUtil.breadcrumb([{ text: 'Dashboard', hash: '#/' }, { text: 'Profile', hash: '#/profile' }, { text: 'API Tokens' }]) +
            '<div class="rc-page-header">' +
            '  <h2>API Tokens</h2>' +
            '  <button class="layui-btn layui-btn-normal" id="create-token-btn">+ Create Token</button>' +
            '</div>' +
            '<div class="layui-card">' +
            '  <div class="layui-card-body" id="tokens-list">' + RCUtil.skeleton(3) + '</div>' +
            '</div>' +
            '</div>';

        TokensPage._loadTokens();

        document.getElementById('create-token-btn').addEventListener('click', function() {
            TokensPage._showCreateModal();
        });
    },

    _loadTokens: function() {
        var listEl = document.getElementById('tokens-list');
        if (!listEl) return;

        RCApi.getTokens().then(function(envelope) {
            var data = envelope.data || envelope;
            var tokens = data.tokens || data.items || data.list || data || [];
            if (!Array.isArray(tokens)) tokens = [];

            if (tokens.length === 0) {
                listEl.innerHTML = RCUtil.emptyState('No API tokens yet. Create one to get started.', null, null);
                return;
            }

            var html = '<table class="layui-table">';
            html += '<thead><tr><th>Name</th><th>Created</th><th>Last Used</th><th>Expires</th><th>Status</th><th style="width:200px;">Actions</th></tr></thead>';
            html += '<tbody>';
            tokens.forEach(function(token) {
                var status = token.revoked_at ? 'revoked' : (token.expires_at && new Date(token.expires_at) < new Date() ? 'expired' : 'active');
                html += '<tr>';
                html += '<td><strong>' + RCUtil.escapeHtml(token.name || 'Unnamed') + '</strong></td>';
                html += '<td>' + RCUtil.formatDate(token.created_at) + '</td>';
                html += '<td>' + (token.last_used_at ? RCUtil.formatRelative(token.last_used_at) : 'Never') + '</td>';
                html += '<td>' + (token.expires_at ? RCUtil.formatDate(token.expires_at) : 'Never') + '</td>';
                html += '<td>' + RCUtil.statusBadge(status) + '</td>';
                html += '<td>';
                if (status === 'active') {
                    html += '<button class="layui-btn layui-btn-xs layui-btn-warm token-rotate-btn" data-id="' + token.id + '">Rotate</button> ';
                    html += '<button class="layui-btn layui-btn-xs layui-btn-danger token-revoke-btn" data-id="' + token.id + '" data-name="' + RCUtil.escapeHtml(token.name || '') + '">Revoke</button>';
                } else {
                    html += '<span class="layui-word-aux">—</span>';
                }
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            listEl.innerHTML = html;

            // Bind actions
            listEl.querySelectorAll('.token-revoke-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    var name = this.getAttribute('data-name');
                    TokensPage._confirmRevoke(id, name);
                });
            });

            listEl.querySelectorAll('.token-rotate-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    TokensPage._rotateToken(id);
                });
            });
        }).catch(function(err) {
            listEl.innerHTML = '<div class="rc-form-error">Failed to load tokens: ' + RCUtil.escapeHtml(err.message || 'Unknown error') + '</div>';
        });
    },

    _showCreateModal: function() {
        layui.layer.open({
            title: 'Create API Token',
            area: ['420px'],
            content:
                '<form id="token-create-form" style="padding:10px 0;">' +
                '<div class="layui-form-item">' +
                '  <label class="layui-form-label">Name</label>' +
                '  <div class="layui-input-block">' +
                '    <input type="text" name="name" class="layui-input" placeholder="e.g., CI/CD Integration" required>' +
                '  </div>' +
                '</div>' +
                '<div class="layui-form-item">' +
                '  <label class="layui-form-label">Expires In</label>' +
                '  <div class="layui-input-block">' +
                '    <select name="expires_in" class="layui-input">' +
                '      <option value="30">30 days</option>' +
                '      <option value="90">90 days</option>' +
                '      <option value="180">180 days</option>' +
                '      <option value="365">1 year</option>' +
                '      <option value="">Never</option>' +
                '    </select>' +
                '  </div>' +
                '</div>' +
                '</form>',
            btn: ['Create', 'Cancel'],
            yes: function(index) {
                var form = document.getElementById('token-create-form');
                var name = form.querySelector('[name="name"]').value.trim();
                var expiresIn = form.querySelector('[name="expires_in"]').value;

                if (!name) {
                    layui.layer.msg('Please enter a token name.', { icon: 2 });
                    return;
                }

                var data = { name: name };
                if (expiresIn) data.expires_in_days = parseInt(expiresIn, 10);

                RCApi.createToken(data).then(function(envelope) {
                    layui.layer.close(index);
                    var result = envelope.data || envelope;
                    var plainToken = result.plain_text_token || result.token || result.access_token || '';
                    TokensPage._showNewTokenModal(plainToken);
                    TokensPage._loadTokens();
                }).catch(function(err) {
                    layui.layer.msg(err.message || 'Failed to create token.', { icon: 2 });
                });
            }
        });
    },

    _showNewTokenModal: function(token) {
        layui.layer.open({
            title: 'Token Created',
            area: ['520px'],
            shadeClose: false,
            content:
                '<div class="rc-token-warning">' +
                '<span style="font-size:18px;">\u26A0</span> ' +
                'Copy this token now. It will not be shown again!' +
                '</div>' +
                '<div class="rc-token-display">' +
                '<code id="new-token-value">' + RCUtil.escapeHtml(token) + '</code>' +
                '<button class="rc-copy-btn" id="copy-token-btn">Copy</button>' +
                '</div>',
            btn: ['Done'],
            yes: function(index) {
                layui.layer.close(index);
            },
            success: function(content) {
                var copyBtn = document.getElementById('copy-token-btn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function() {
                        var tokenText = document.getElementById('new-token-value').textContent;
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(tokenText).then(function() {
                                copyBtn.textContent = 'Copied!';
                                setTimeout(function() { copyBtn.textContent = 'Copy'; }, 2000);
                            });
                        } else {
                            // Fallback
                            var textarea = document.createElement('textarea');
                            textarea.value = tokenText;
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                            copyBtn.textContent = 'Copied!';
                            setTimeout(function() { copyBtn.textContent = 'Copy'; }, 2000);
                        }
                    });
                }
            }
        });
    },

    _confirmRevoke: function(id, name) {
        layui.layer.confirm(
            'Are you sure you want to revoke the token <strong>"' + RCUtil.escapeHtml(name) + '"</strong>? This action cannot be undone.',
            { title: 'Revoke Token', btn: ['Revoke', 'Cancel'] },
            function(index) {
                layui.layer.close(index);
                RCApi.revokeToken(id).then(function() {
                    layui.layer.msg('Token revoked.', { icon: 1 });
                    TokensPage._loadTokens();
                }).catch(function(err) {
                    layui.layer.msg(err.message || 'Failed to revoke token.', { icon: 2 });
                });
            }
        );
    },

    _rotateToken: function(id) {
        layui.layer.confirm(
            'Rotate this token? The current token will be invalidated and a new one will be generated.',
            { title: 'Rotate Token', btn: ['Rotate', 'Cancel'] },
            function(index) {
                layui.layer.close(index);
                RCApi.rotateToken(id).then(function(envelope) {
                    var result = envelope.data || envelope;
                    var newToken = result.plain_text_token || result.token || result.access_token || '';
                    TokensPage._showNewTokenModal(newToken);
                    TokensPage._loadTokens();
                }).catch(function(err) {
                    layui.layer.msg(err.message || 'Failed to rotate token.', { icon: 2 });
                });
            }
        );
    }
};
