/**
 * RideCircle Login Page
 */
var LoginPage = {
    render: function(container) {
        container.innerHTML =
            '<div class="rc-auth-card">' +
            '  <div class="rc-auth-logo"><h1>RideCircle</h1><p>Carpool Marketplace</p></div>' +
            '  <form class="layui-form" id="login-form">' +
            '    <div class="rc-form-error" id="login-error" style="display:none;"></div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Email</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="email" name="email" required placeholder="you@company.local" class="layui-input" autocomplete="email">' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Org Code</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="text" name="organization_code" required placeholder="Organization code" class="layui-input" autocomplete="organization">' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Password</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="password" name="password" required placeholder="Enter your password" class="layui-input" autocomplete="current-password">' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <div class="layui-input-block">' +
            '        <button type="submit" class="layui-btn layui-btn-normal layui-btn-fluid" id="login-btn">Log In</button>' +
            '      </div>' +
            '    </div>' +
            '    <div class="rc-auth-footer">' +
            '      <span>Don\'t have an account? <a href="#/register">Register</a></span>' +
            '    </div>' +
            '  </form>' +
            '</div>';

        var form = document.getElementById('login-form');
        var errorDiv = document.getElementById('login-error');
        var btn = document.getElementById('login-btn');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            errorDiv.style.display = 'none';
            RCUtil.clearFieldErrors();

            var email = form.email.value.trim();
            var organizationCode = form.organization_code.value.trim();
            var password = form.password.value;

            // Basic validation
            if (!email) {
                errorDiv.textContent = 'Please enter your email address.';
                errorDiv.style.display = 'block';
                form.email.focus();
                return;
            }
            if (!organizationCode) {
                errorDiv.textContent = 'Please enter your organization code.';
                errorDiv.style.display = 'block';
                form.organization_code.focus();
                return;
            }
            if (!password) {
                errorDiv.textContent = 'Please enter your password.';
                errorDiv.style.display = 'block';
                form.password.focus();
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '\u21BB Logging in...';

            RCAuth.login(email, password, organizationCode).then(function(user) {
                layui.layer.msg('Welcome back, ' + (user.name || 'User') + '!', { icon: 1 });
                RCRouter.navigate('#/');
            }).catch(function(err) {
                if (err.errors) {
                    RCUtil.showFieldErrors(err.errors);
                }
                errorDiv.textContent = err.message || 'Invalid email or password';
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Log In';
            });
        });
    }
};
