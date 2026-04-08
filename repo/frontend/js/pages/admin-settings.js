/**
 * admin-settings.js - Organization Settings Page
 * Admin only. Form for org name, hotlink domains, moderation, media settings.
 */
var AdminSettingsPage = (function () {
    'use strict';

    var state = {
        settings: null,
        loading: false,
        saving: false,
        error: null,
        saveError: null,
        saveSuccess: false
    };

    function renderPage(container) {
        if (!RCAuth.isAdmin()) {
            container.innerHTML = '<div style="max-width:800px;margin:0 auto;padding:40px;text-align:center;">' +
                '<i class="layui-icon layui-icon-face-surprised" style="font-size:48px;color:#FF5722;display:block;margin-bottom:16px;"></i>' +
                '<h3>Access Denied</h3><p style="color:#666;">Only administrators can manage organization settings.</p></div>';
            return;
        }

        if (state.loading) {
            container.innerHTML = '<div style="max-width:700px;margin:0 auto;padding:20px;">' + RCUtil.skeleton(5) + '</div>';
            return;
        }
        if (state.error) {
            container.innerHTML = '<div style="max-width:700px;margin:0 auto;padding:20px;">' +
                '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div></div>';
            return;
        }

        var s = state.settings || {};
        var settings = s.settings || {};

        var html = '<div style="max-width:700px;margin:0 auto;padding:20px;">' +
            '<h2 style="margin-bottom:20px;">Organization Settings</h2>' +
            '<div class="layui-card"><div class="layui-card-body" style="padding:24px;">';

        // Org name
        html += '<div class="layui-form-item" style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:6px;font-weight:500;">Organization Name</label>' +
            '<input type="text" id="rc-setting-name" class="layui-input" value="' +
            RCUtil.escapeHtml(s.name || '') + '" placeholder="Organization name"></div>';

        // Hotlink domains
        html += '<div class="layui-form-item" style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:6px;font-weight:500;">Hotlink Allowed Domains</label>' +
            '<input type="text" id="rc-setting-hotlink" class="layui-input" value="' +
            RCUtil.escapeHtml(settings.hotlink_allowed_domains || '') + '" placeholder="example.com, cdn.example.com">' +
            '<div style="font-size:12px;color:#999;margin-top:4px;">Comma-separated list of domains allowed for hotlinking</div></div>';

        // Duplicate threshold
        var threshold = settings.moderation_duplicate_threshold !== undefined ? settings.moderation_duplicate_threshold : 0.8;
        html += '<div class="layui-form-item" style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:6px;font-weight:500;">Moderation: Duplicate Threshold</label>' +
            '<div style="display:flex;align-items:center;gap:12px;">' +
            '<input type="range" id="rc-setting-dup-threshold" min="0" max="1" step="0.05" value="' + threshold + '" ' +
            'style="flex:1;cursor:pointer;">' +
            '<span id="rc-dup-threshold-value" style="font-weight:600;min-width:40px;text-align:center;">' +
            parseFloat(threshold).toFixed(2) + '</span>' +
            '</div>' +
            '<div style="font-size:12px;color:#999;margin-top:4px;">Content similarity threshold for duplicate detection (0 = no matching, 1 = exact match only)</div></div>';

        // Watermark
        var watermark = settings.media_watermark_enabled ? true : false;
        html += '<div class="layui-form-item" style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:6px;font-weight:500;">Media: Watermark</label>' +
            '<label style="cursor:pointer;display:flex;align-items:center;gap:8px;">' +
            '<input type="checkbox" id="rc-setting-watermark"' + (watermark ? ' checked' : '') + ' lay-ignore> ' +
            '<span>Enable watermarking on uploaded photos</span></label></div>';

        // URL expiry
        var expiry = settings.media_url_expiry_minutes !== undefined ? settings.media_url_expiry_minutes : 60;
        html += '<div class="layui-form-item" style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:6px;font-weight:500;">Media: URL Expiry (minutes)</label>' +
            '<input type="number" id="rc-setting-url-expiry" class="layui-input" value="' + expiry + '" ' +
            'min="1" max="1440" placeholder="60" style="max-width:200px;">' +
            '<div style="font-size:12px;color:#999;margin-top:4px;">Signed URL validity period in minutes (1-1440)</div></div>';

        // Save messages
        if (state.saveError) {
            html += '<div style="color:#FF5722;padding:10px 0;font-size:13px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.saveError) + '</div>';
        }
        if (state.saveSuccess) {
            html += '<div style="color:#16b777;padding:10px 0;font-size:13px;"><i class="layui-icon layui-icon-ok-circle"></i> Settings saved successfully.</div>';
        }

        // Save button
        var disabled = state.saving;
        html += '<button type="button" id="rc-settings-save" class="layui-btn' +
            (disabled ? ' layui-btn-disabled' : '') + '"' + (disabled ? ' disabled' : '') + '>' +
            (state.saving ? '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Saving...' : 'Save Settings') +
            '</button>';

        html += '</div></div></div>';
        container.innerHTML = html;
    }

    function bindEvents(container) {
        // Threshold slider
        var slider = container.querySelector('#rc-setting-dup-threshold');
        var sliderValue = container.querySelector('#rc-dup-threshold-value');
        if (slider && sliderValue) {
            slider.addEventListener('input', function () {
                sliderValue.textContent = parseFloat(this.value).toFixed(2);
            });
        }

        // Save
        var saveBtn = container.querySelector('#rc-settings-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                handleSave(container);
            });
        }
    }

    function handleSave(container) {
        if (state.saving) return;

        var nameEl = container.querySelector('#rc-setting-name');
        var hotlinkEl = container.querySelector('#rc-setting-hotlink');
        var thresholdEl = container.querySelector('#rc-setting-dup-threshold');
        var watermarkEl = container.querySelector('#rc-setting-watermark');
        var expiryEl = container.querySelector('#rc-setting-url-expiry');

        var data = {
            name: nameEl ? nameEl.value.trim() : '',
            hotlink_allowed_domains: hotlinkEl ? hotlinkEl.value.trim() : '',
            moderation_duplicate_threshold: thresholdEl ? parseFloat(thresholdEl.value) : 0.8,
            media_watermark_enabled: watermarkEl ? watermarkEl.checked : false,
            media_url_expiry_minutes: expiryEl ? parseInt(expiryEl.value, 10) || 60 : 60
        };

        if (!data.name) {
            layui.layer.msg('Organization name is required.', { icon: 0 });
            return;
        }

        state.saving = true;
        state.saveError = null;
        state.saveSuccess = false;
        renderPage(container);
        bindEvents(container);

        RCApi.updateOrgSettings(data).then(function (res) {
            state.saving = false;
            state.saveSuccess = true;
            state.settings = res.data || state.settings;
            layui.layer.msg(res.message || 'Settings saved', { icon: 1 });
            renderPage(container);
            bindEvents(container);
        }).catch(function (err) {
            state.saving = false;
            state.saveError = (err && err.message) ? err.message : 'Failed to save settings';
            renderPage(container);
            bindEvents(container);
        });
    }

    function loadSettings(container) {
        state.loading = true;
        renderPage(container);

        RCApi.getOrgSettings().then(function (res) {
            state.loading = false;
            state.settings = res.data || {};
            renderPage(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load settings';
            renderPage(container);
        });
    }

    return {
        render: function (container, params) {
            state.settings = null;
            state.error = null;
            state.saveError = null;
            state.saveSuccess = false;
            state.saving = false;

            if (!RCAuth.isAdmin()) {
                renderPage(container);
                return;
            }
            loadSettings(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminSettingsPage;
}
