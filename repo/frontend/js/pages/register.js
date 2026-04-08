/**
 * RideCircle Registration Page
 */
var RegisterPage = {
    render: function(container) {
        container.innerHTML =
            '<div class="rc-auth-card">' +
            '  <div class="rc-auth-logo"><h1>RideCircle</h1><p>Create Your Account</p></div>' +
            '  <form class="layui-form" id="register-form">' +
            '    <div class="rc-form-error" id="register-error" style="display:none;"></div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Full Name</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="text" name="name" required placeholder="Your full name" class="layui-input" autocomplete="name">' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Email</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="email" name="email" required placeholder="you@company.local" class="layui-input" autocomplete="email" id="reg-email">' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Password</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="password" name="password" required placeholder="Min 8 characters" class="layui-input" autocomplete="new-password" id="reg-password">' +
            '        <div class="rc-password-strength" id="pw-strength" style="display:none;">' +
            '          <div class="rc-password-bar" id="pw-bar-1"></div>' +
            '          <div class="rc-password-bar" id="pw-bar-2"></div>' +
            '          <div class="rc-password-bar" id="pw-bar-3"></div>' +
            '          <span class="rc-password-label" id="pw-label"></span>' +
            '        </div>' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Confirm Password</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="password" name="password_confirmation" required placeholder="Repeat your password" class="layui-input" autocomplete="new-password" id="reg-password-confirm">' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <label class="layui-form-label">Organization Code</label>' +
            '      <div class="layui-input-block">' +
            '        <input type="text" name="organization_code" placeholder="Organization code" class="layui-input" required>' +
            '      </div>' +
            '    </div>' +
            '    <div class="layui-form-item">' +
            '      <div class="layui-input-block">' +
            '        <button type="submit" class="layui-btn layui-btn-normal layui-btn-fluid" id="register-btn">Create Account</button>' +
            '      </div>' +
            '    </div>' +
            '    <div class="rc-auth-footer">' +
            '      <span>Already have an account? <a href="#/login">Log In</a></span>' +
            '    </div>' +
            '  </form>' +
            '</div>';

        var form = document.getElementById('register-form');
        var errorDiv = document.getElementById('register-error');
        var btn = document.getElementById('register-btn');
        var pwInput = document.getElementById('reg-password');
        var pwConfirmInput = document.getElementById('reg-password-confirm');
        var pwStrength = document.getElementById('pw-strength');
        var pwBar1 = document.getElementById('pw-bar-1');
        var pwBar2 = document.getElementById('pw-bar-2');
        var pwBar3 = document.getElementById('pw-bar-3');
        var pwLabel = document.getElementById('pw-label');

        // Password strength indicator
        function checkPasswordStrength(password) {
            var score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;

            if (score <= 2) return { level: 'weak', label: 'Weak' };
            if (score <= 3) return { level: 'medium', label: 'Medium' };
            return { level: 'strong', label: 'Strong' };
        }

        pwInput.addEventListener('input', function() {
            var pw = this.value;
            if (!pw) {
                pwStrength.style.display = 'none';
                return;
            }
            pwStrength.style.display = 'flex';
            var result = checkPasswordStrength(pw);
            var level = result.level;

            // Reset bars
            pwBar1.className = 'rc-password-bar';
            pwBar2.className = 'rc-password-bar';
            pwBar3.className = 'rc-password-bar';

            if (level === 'weak') {
                pwBar1.classList.add('weak');
            } else if (level === 'medium') {
                pwBar1.classList.add('medium');
                pwBar2.classList.add('medium');
            } else {
                pwBar1.classList.add('strong');
                pwBar2.classList.add('strong');
                pwBar3.classList.add('strong');
            }
            pwLabel.textContent = result.label;
            pwLabel.className = 'rc-password-label ' + level;
        });

        // Inline validation on blur
        var emailInput = document.getElementById('reg-email');

        emailInput.addEventListener('blur', function() {
            var email = this.value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.classList.add('layui-form-danger');
                _showInlineError(this, 'Please enter a valid email address.');
            } else {
                this.classList.remove('layui-form-danger');
                _clearInlineError(this);
            }
        });

        pwConfirmInput.addEventListener('blur', function() {
            if (this.value && this.value !== pwInput.value) {
                this.classList.add('layui-form-danger');
                _showInlineError(this, 'Passwords do not match.');
            } else {
                this.classList.remove('layui-form-danger');
                _clearInlineError(this);
            }
        });

        function _showInlineError(input, msg) {
            _clearInlineError(input);
            var errEl = document.createElement('span');
            errEl.className = 'rc-field-error';
            errEl.textContent = msg;
            input.parentNode.appendChild(errEl);
        }

        function _clearInlineError(input) {
            var existing = input.parentNode.querySelector('.rc-field-error');
            if (existing) existing.remove();
        }

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            errorDiv.style.display = 'none';
            RCUtil.clearFieldErrors();

            var name = form.name.value.trim();
            var email = form.email.value.trim();
            var password = form.password.value;
            var passwordConfirm = form.password_confirmation.value;
            var inviteCode = form.organization_code.value.trim();

            // Client-side validation
            if (!name) {
                errorDiv.textContent = 'Please enter your full name.';
                errorDiv.style.display = 'block';
                form.name.focus();
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errorDiv.textContent = 'Please enter a valid email address.';
                errorDiv.style.display = 'block';
                form.email.focus();
                return;
            }
            if (password.length < 8) {
                errorDiv.textContent = 'Password must be at least 8 characters.';
                errorDiv.style.display = 'block';
                form.password.focus();
                return;
            }
            if (password !== passwordConfirm) {
                errorDiv.textContent = 'Passwords do not match.';
                errorDiv.style.display = 'block';
                form.password_confirmation.focus();
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '\u21BB Creating account...';

            var data = {
                name: name,
                email: email,
                password: password,
                password_confirmation: passwordConfirm
            };
            if (!inviteCode) {
                errorDiv.textContent = 'Please enter your organization code.';
                errorDiv.style.display = 'block';
                form.organization_code.focus();
                return;
            }
            data.organization_code = inviteCode;

            RCApi.register(data).then(function(result) {
                layui.layer.msg('Account created! Please log in.', { icon: 1 });
                RCRouter.navigate('#/login');
            }).catch(function(err) {
                if (err.errors) {
                    RCUtil.showFieldErrors(err.errors);
                }
                errorDiv.textContent = err.message || 'Registration failed. Please try again.';
                errorDiv.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Create Account';
            });
        });
    }
};
