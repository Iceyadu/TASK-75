/**
 * Layui JavaScript Shim for RideCircle
 * Provides core Layui APIs: layer, form, table, laypage, laydate, upload, rate, element
 */
window.layui = (function() {
    'use strict';

    var _layerIndex = 0;
    var _formEvents = {};
    var _formVerifyRules = {};
    var _tableInstances = {};
    var _tableEvents = {};
    var _elementEvents = {};

    // Utility: create element with attributes
    function _el(tag, attrs, html) {
        var el = document.createElement(tag);
        if (attrs) {
            for (var k in attrs) {
                if (attrs.hasOwnProperty(k)) {
                    if (k === 'style' && typeof attrs[k] === 'object') {
                        for (var s in attrs[k]) el.style[s] = attrs[k][s];
                    } else {
                        el.setAttribute(k, attrs[k]);
                    }
                }
            }
        }
        if (html) el.innerHTML = html;
        return el;
    }

    // Utility: remove element
    function _remove(el) {
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    // ========== Layer Module ==========
    var layer = {
        msg: function(content, options, callback) {
            options = options || {};
            var time = options.time || 2000;
            var iconHtml = '';
            if (options.icon !== undefined) {
                var iconColors = { 1: '#5FB878', 2: '#FF5722', 5: '#FFB800', 6: '#1E9FFF' };
                var iconSymbols = { 1: '\u2713', 2: '\u2715', 5: '\u2639', 6: '\u263A' };
                var ic = options.icon;
                iconHtml = '<span class="layui-layer-msg-icon layui-layer-msg-icon-' + ic + '" style="background:' +
                    (iconColors[ic] || '#999') + '">' + (iconSymbols[ic] || '') + '</span> ';
            }
            var msgEl = _el('div', { 'class': 'layui-layer-msg' }, iconHtml + content);
            document.body.appendChild(msgEl);
            setTimeout(function() {
                msgEl.style.transition = 'opacity 0.3s';
                msgEl.style.opacity = '0';
                setTimeout(function() {
                    _remove(msgEl);
                    if (typeof callback === 'function') callback();
                    if (typeof options.end === 'function') options.end();
                }, 300);
            }, time);
            return ++_layerIndex;
        },

        alert: function(content, options, callback) {
            if (typeof options === 'function') { callback = options; options = {}; }
            options = options || {};
            var idx = ++_layerIndex;
            var shade = _el('div', { 'class': 'layui-layer-shade', 'data-layer-index': idx });
            var modal = _el('div', { 'class': 'layui-layer', 'data-layer-index': idx });
            var title = options.title !== false ? (options.title || 'Info') : null;
            var html = '';
            if (title) html += '<div class="layui-layer-title">' + title + '</div>';
            html += '<div class="layui-layer-content">' + content + '</div>';
            html += '<div class="layui-layer-btn"><button class="layui-layer-btn0">OK</button></div>';
            html += '<span class="layui-layer-close">\u00D7</span>';
            modal.innerHTML = html;
            _centerModal(modal);
            document.body.appendChild(shade);
            document.body.appendChild(modal);
            _centerModal(modal);

            var closeLayer = function() {
                _remove(shade);
                _remove(modal);
                if (typeof callback === 'function') callback(idx);
                if (typeof options.end === 'function') options.end();
            };
            modal.querySelector('.layui-layer-btn0').addEventListener('click', closeLayer);
            modal.querySelector('.layui-layer-close').addEventListener('click', closeLayer);
            shade.addEventListener('click', closeLayer);
            return idx;
        },

        confirm: function(content, options, yes, cancel) {
            if (typeof options === 'function') { cancel = yes; yes = options; options = {}; }
            options = options || {};
            var idx = ++_layerIndex;
            var shade = _el('div', { 'class': 'layui-layer-shade', 'data-layer-index': idx });
            var modal = _el('div', { 'class': 'layui-layer', 'data-layer-index': idx });
            var title = options.title || 'Confirm';
            var btn = options.btn || ['OK', 'Cancel'];
            var html = '<div class="layui-layer-title">' + title + '</div>';
            html += '<div class="layui-layer-content">' + content + '</div>';
            html += '<div class="layui-layer-btn">';
            html += '<button class="layui-layer-btn0">' + btn[0] + '</button>';
            html += '<button class="layui-layer-btn1">' + btn[1] + '</button>';
            html += '</div>';
            html += '<span class="layui-layer-close">\u00D7</span>';
            modal.innerHTML = html;
            document.body.appendChild(shade);
            document.body.appendChild(modal);
            _centerModal(modal);

            var closeThis = function() { _remove(shade); _remove(modal); };
            modal.querySelector('.layui-layer-btn0').addEventListener('click', function() {
                if (typeof yes === 'function') yes(idx);
                closeThis();
            });
            modal.querySelector('.layui-layer-btn1').addEventListener('click', function() {
                if (typeof cancel === 'function') cancel(idx);
                closeThis();
            });
            modal.querySelector('.layui-layer-close').addEventListener('click', closeThis);
            shade.addEventListener('click', closeThis);
            return idx;
        },

        open: function(options) {
            options = options || {};
            var idx = ++_layerIndex;
            var shade = _el('div', { 'class': 'layui-layer-shade', 'data-layer-index': idx });
            var modal = _el('div', { 'class': 'layui-layer', 'data-layer-index': idx });

            if (options.area) {
                var area = Array.isArray(options.area) ? options.area : [options.area];
                if (area[0]) modal.style.width = area[0];
                if (area[1]) modal.style.height = area[1];
            }

            var html = '';
            if (options.title !== false) {
                html += '<div class="layui-layer-title">' + (options.title || '') + '</div>';
            }
            html += '<div class="layui-layer-content">' + (options.content || '') + '</div>';

            if (options.btn && options.btn.length) {
                html += '<div class="layui-layer-btn">';
                options.btn.forEach(function(b, i) {
                    html += '<button class="layui-layer-btn' + i + '">' + b + '</button>';
                });
                html += '</div>';
            }
            html += '<span class="layui-layer-close">\u00D7</span>';
            modal.innerHTML = html;
            document.body.appendChild(shade);
            document.body.appendChild(modal);
            _centerModal(modal);

            var closeThis = function() {
                _remove(shade);
                _remove(modal);
                if (typeof options.end === 'function') options.end();
            };

            modal.querySelector('.layui-layer-close').addEventListener('click', closeThis);
            if (options.shadeClose !== false) {
                shade.addEventListener('click', closeThis);
            }

            if (options.btn) {
                options.btn.forEach(function(b, i) {
                    var btnEl = modal.querySelector('.layui-layer-btn' + i);
                    if (btnEl) {
                        btnEl.addEventListener('click', function() {
                            var cbName = i === 0 ? 'yes' : 'btn' + (i + 1);
                            if (typeof options[cbName] === 'function') {
                                options[cbName](idx);
                            }
                            if (i !== 0) closeThis();
                        });
                    }
                });
            }

            if (typeof options.success === 'function') {
                options.success(modal.querySelector('.layui-layer-content'), idx);
            }

            // Store close function
            modal._layerClose = closeThis;
            modal._layerIndex = idx;
            return idx;
        },

        close: function(index) {
            var shades = document.querySelectorAll('.layui-layer-shade[data-layer-index="' + index + '"]');
            var modals = document.querySelectorAll('.layui-layer[data-layer-index="' + index + '"]');
            shades.forEach(function(el) { _remove(el); });
            modals.forEach(function(el) { _remove(el); });
            // Also close loading layers
            var loadings = document.querySelectorAll('.layui-layer-loading[data-layer-index="' + index + '"]');
            loadings.forEach(function(el) { _remove(el); });
        },

        load: function(type) {
            var idx = ++_layerIndex;
            var loading = _el('div', { 'class': 'layui-layer-loading', 'data-layer-index': idx },
                '<div class="layui-layer-loading-icon"></div>');
            document.body.appendChild(loading);
            return idx;
        },

        closeAll: function(type) {
            var selectors = ['.layui-layer-shade', '.layui-layer', '.layui-layer-loading', '.layui-layer-msg'];
            selectors.forEach(function(sel) {
                document.querySelectorAll(sel).forEach(function(el) { _remove(el); });
            });
        }
    };

    function _centerModal(modal) {
        requestAnimationFrame(function() {
            var w = modal.offsetWidth;
            var h = modal.offsetHeight;
            modal.style.position = 'fixed';
            modal.style.left = Math.max(0, (window.innerWidth - w) / 2) + 'px';
            modal.style.top = Math.max(20, (window.innerHeight - h) / 2 - 40) + 'px';
        });
    }

    // ========== Form Module ==========
    var form = {
        render: function(type, filter) {
            // Re-process select elements to apply styling
            if (!type || type === 'select') {
                document.querySelectorAll('select.layui-input').forEach(function(sel) {
                    sel.style.height = '38px';
                });
            }
        },

        on: function(event, callback) {
            // event format: "submit(filter)" or "select(filter)" or "checkbox(filter)"
            var match = event.match(/^(\w+)\((\w+)\)$/);
            if (!match) return;
            var type = match[1];
            var filter = match[2];
            _formEvents[type + '_' + filter] = callback;

            if (type === 'submit') {
                // Bind to form with lay-filter matching
                setTimeout(function() {
                    var forms = document.querySelectorAll('[lay-filter="' + filter + '"]');
                    forms.forEach(function(formEl) {
                        // Find submit button
                        var btn = formEl.querySelector('[lay-submit]') || formEl.querySelector('button[type="submit"]');
                        var handler = function(e) {
                            e.preventDefault();
                            var formData = new FormData(formEl);
                            var data = {};
                            formData.forEach(function(val, key) { data[key] = val; });

                            // Run verify rules
                            var valid = true;
                            for (var key in data) {
                                var input = formEl.querySelector('[name="' + key + '"]');
                                if (input) {
                                    var verify = input.getAttribute('lay-verify');
                                    if (verify) {
                                        var rules = verify.split('|');
                                        for (var r = 0; r < rules.length; r++) {
                                            var rule = _formVerifyRules[rules[r]];
                                            if (rule) {
                                                var result = rule(data[key], input);
                                                if (typeof result === 'string') {
                                                    layer.msg(result, { icon: 2 });
                                                    input.focus();
                                                    valid = false;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    if (!valid) break;
                                }
                            }

                            if (valid && typeof callback === 'function') {
                                callback({ field: data, form: formEl, elem: btn });
                            }
                            return false;
                        };

                        if (btn) btn.addEventListener('click', handler);
                        formEl.addEventListener('submit', handler);
                    });
                }, 0);
            }
        },

        verify: function(rules) {
            for (var name in rules) {
                if (rules.hasOwnProperty(name)) {
                    var rule = rules[name];
                    if (Array.isArray(rule)) {
                        // [regex, message]
                        _formVerifyRules[name] = (function(r) {
                            return function(val) {
                                if (!r[0].test(val)) return r[1];
                            };
                        })(rule);
                    } else if (typeof rule === 'function') {
                        _formVerifyRules[name] = rule;
                    }
                }
            }
        },

        val: function(filter, data) {
            var formEl = document.querySelector('[lay-filter="' + filter + '"]');
            if (!formEl) return;
            if (data) {
                for (var key in data) {
                    var input = formEl.querySelector('[name="' + key + '"]');
                    if (input) input.value = data[key];
                }
            } else {
                var result = {};
                var fd = new FormData(formEl);
                fd.forEach(function(v, k) { result[k] = v; });
                return result;
            }
        }
    };

    // ========== Table Module ==========
    var table = {
        render: function(options) {
            if (!options || !options.elem) return;
            var container = typeof options.elem === 'string' ? document.querySelector(options.elem) : options.elem;
            if (!container) return;

            var id = options.id || ('table_' + Date.now());
            _tableInstances[id] = options;

            var html = '<table class="layui-table" lay-filter="' + (options.filter || id) + '">';
            if (options.cols && options.cols[0]) {
                html += '<thead><tr>';
                options.cols[0].forEach(function(col) {
                    html += '<th style="' + (col.width ? 'width:' + col.width + 'px;' : '') + '">' + (col.title || '') + '</th>';
                });
                html += '</tr></thead>';
            }
            html += '<tbody id="table-body-' + id + '"></tbody></table>';
            container.innerHTML = html;

            if (options.url) {
                // Fetch data from URL
                var params = options.where || {};
                params.page = params.page || 1;
                params.limit = options.limit || 10;
                _tableLoadData(id, options, params);
            } else if (options.data) {
                _tableRenderRows(id, options, options.data);
            }

            return { config: options, reload: function(opts) { table.reload(id, opts); } };
        },

        reload: function(id, options) {
            var original = _tableInstances[id];
            if (!original) return;
            if (options) {
                if (options.where) original.where = Object.assign(original.where || {}, options.where);
                if (options.page) original.page = options.page;
                if (options.data) original.data = options.data;
            }
            if (original.url) {
                var params = original.where || {};
                params.page = (original.page && original.page.curr) || 1;
                params.limit = original.limit || 10;
                _tableLoadData(id, original, params);
            } else if (original.data) {
                _tableRenderRows(id, original, original.data);
            }
        },

        on: function(event, callback) {
            _tableEvents[event] = callback;
        }
    };

    function _tableLoadData(id, options, params) {
        var url = options.url;
        var query = Object.keys(params).map(function(k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
        fetch(url + (url.indexOf('?') !== -1 ? '&' : '?') + query, { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var data = (options.parseData ? options.parseData(res) : res) || {};
                _tableRenderRows(id, options, data.data || []);
                if (typeof options.done === 'function') options.done(data, params.page, data.count);
            })
            .catch(function(err) { console.error('Table load error:', err); });
    }

    function _tableRenderRows(id, options, data) {
        var tbody = document.getElementById('table-body-' + id);
        if (!tbody) return;
        if (!data || !data.length) {
            tbody.innerHTML = '<tr><td colspan="' + ((options.cols && options.cols[0] ? options.cols[0].length : 1)) +
                '" style="text-align:center;padding:30px;color:#999;">No data available</td></tr>';
            return;
        }
        var html = '';
        data.forEach(function(row, idx) {
            html += '<tr>';
            if (options.cols && options.cols[0]) {
                options.cols[0].forEach(function(col) {
                    var val = col.field ? row[col.field] : '';
                    if (typeof col.templet === 'function') {
                        val = col.templet(row);
                    } else if (typeof col.templet === 'string') {
                        val = col.templet.replace(/{{d\.(\w+)}}/g, function(m, f) { return row[f] || ''; });
                    }
                    html += '<td>' + (val !== undefined && val !== null ? val : '') + '</td>';
                });
            }
            html += '</tr>';
        });
        tbody.innerHTML = html;
    }

    // ========== Laypage (Pagination) ==========
    var laypage = {
        render: function(options) {
            var container = typeof options.elem === 'string' ? document.getElementById(options.elem) : options.elem;
            if (!container) return;

            var count = options.count || 0;
            var limit = options.limit || 10;
            var curr = options.curr || 1;
            var totalPages = Math.ceil(count / limit) || 1;
            curr = Math.max(1, Math.min(curr, totalPages));

            var html = '<div class="layui-laypage">';
            // Previous
            if (curr > 1) {
                html += '<a data-page="' + (curr - 1) + '">&laquo; Prev</a>';
            } else {
                html += '<span class="layui-disabled">&laquo; Prev</span>';
            }

            // Page numbers with ellipsis
            var start = Math.max(1, curr - 2);
            var end = Math.min(totalPages, curr + 2);
            if (start > 1) {
                html += '<a data-page="1">1</a>';
                if (start > 2) html += '<span class="layui-disabled">...</span>';
            }
            for (var i = start; i <= end; i++) {
                if (i === curr) {
                    html += '<span class="layui-laypage-curr">' + i + '</span>';
                } else {
                    html += '<a data-page="' + i + '">' + i + '</a>';
                }
            }
            if (end < totalPages) {
                if (end < totalPages - 1) html += '<span class="layui-disabled">...</span>';
                html += '<a data-page="' + totalPages + '">' + totalPages + '</a>';
            }

            // Next
            if (curr < totalPages) {
                html += '<a data-page="' + (curr + 1) + '">Next &raquo;</a>';
            } else {
                html += '<span class="layui-disabled">Next &raquo;</span>';
            }

            if (options.layout && options.layout.indexOf('count') !== -1) {
                html += '<span class="layui-laypage-count">Total ' + count + '</span>';
            }
            html += '</div>';
            container.innerHTML = html;

            // Bind clicks
            container.querySelectorAll('a[data-page]').forEach(function(a) {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    var page = parseInt(this.getAttribute('data-page'), 10);
                    if (typeof options.jump === 'function') {
                        options.jump({ curr: page, limit: limit }, false);
                    }
                });
            });

            // First load callback
            if (options.jump && options.first !== false) {
                options.jump({ curr: curr, limit: limit }, true);
            }
        }
    };

    // ========== Laydate (Date Picker) ==========
    var laydate = {
        render: function(options) {
            var input = typeof options.elem === 'string' ? document.querySelector(options.elem) : options.elem;
            if (!input) return;

            var type = options.type || 'date'; // date, datetime, time
            input.setAttribute('readonly', 'readonly');
            input.style.cursor = 'pointer';
            input.style.backgroundColor = '#fff';

            input.addEventListener('click', function(e) {
                e.stopPropagation();
                // Remove any existing picker
                var existing = document.querySelector('.layui-laydate');
                if (existing) _remove(existing);

                var picker = _el('div', { 'class': 'layui-laydate' });
                var now = input.value ? new Date(input.value) : new Date();
                if (isNaN(now.getTime())) now = new Date();
                var viewYear = now.getFullYear();
                var viewMonth = now.getMonth();

                function renderCalendar() {
                    var html = '<div class="layui-laydate-header">';
                    html += '<button type="button" class="layui-btn layui-btn-xs layui-btn-primary ld-prev-month">&lt;</button>';
                    html += '<span class="ld-title">' + viewYear + '-' + String(viewMonth + 1).padStart(2, '0') + '</span>';
                    html += '<button type="button" class="layui-btn layui-btn-xs layui-btn-primary ld-next-month">&gt;</button>';
                    html += '</div>';
                    html += '<div class="layui-laydate-content"><table>';
                    html += '<tr><th>Su</th><th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th></tr>';

                    var firstDay = new Date(viewYear, viewMonth, 1).getDay();
                    var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
                    var day = 1;
                    for (var w = 0; w < 6; w++) {
                        html += '<tr>';
                        for (var d = 0; d < 7; d++) {
                            var idx = w * 7 + d;
                            if (idx < firstDay || day > daysInMonth) {
                                html += '<td></td>';
                            } else {
                                var dateStr = viewYear + '-' + String(viewMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                                var cls = (day === now.getDate() && viewMonth === now.getMonth() && viewYear === now.getFullYear()) ? ' class="layui-this"' : '';
                                html += '<td' + cls + ' data-date="' + dateStr + '">' + day + '</td>';
                                day++;
                            }
                        }
                        html += '</tr>';
                        if (day > daysInMonth) break;
                    }
                    html += '</table></div>';

                    if (type === 'datetime') {
                        var hh = String(now.getHours()).padStart(2, '0');
                        var mm = String(now.getMinutes()).padStart(2, '0');
                        html += '<div class="layui-laydate-footer" style="justify-content:center;gap:5px;">';
                        html += '<input type="time" class="layui-input ld-time" value="' + hh + ':' + mm + '" style="width:120px;height:30px;">';
                        html += '</div>';
                    }

                    html += '<div class="layui-laydate-footer">';
                    html += '<a class="ld-today" style="cursor:pointer;color:#1E9FFF;">Today</a>';
                    html += '<button type="button" class="layui-btn layui-btn-xs layui-btn-normal ld-confirm">OK</button>';
                    html += '</div>';

                    picker.innerHTML = html;
                }

                renderCalendar();

                // Position below input
                var rect = input.getBoundingClientRect();
                picker.style.position = 'absolute';
                picker.style.top = (rect.bottom + window.scrollY + 2) + 'px';
                picker.style.left = rect.left + 'px';
                document.body.appendChild(picker);

                var selectedDate = null;

                picker.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    var target = ev.target;

                    if (target.classList.contains('ld-prev-month')) {
                        viewMonth--;
                        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                        renderCalendar();
                    } else if (target.classList.contains('ld-next-month')) {
                        viewMonth++;
                        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
                        renderCalendar();
                    } else if (target.getAttribute('data-date')) {
                        selectedDate = target.getAttribute('data-date');
                        picker.querySelectorAll('td.layui-this').forEach(function(td) { td.classList.remove('layui-this'); });
                        target.classList.add('layui-this');
                    } else if (target.classList.contains('ld-today')) {
                        var t = new Date();
                        selectedDate = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
                        _setAndClose();
                    } else if (target.classList.contains('ld-confirm')) {
                        _setAndClose();
                    }
                });

                function _setAndClose() {
                    var val = selectedDate || (viewYear + '-' + String(viewMonth + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0'));
                    if (type === 'datetime') {
                        var timeInput = picker.querySelector('.ld-time');
                        val += ' ' + (timeInput ? timeInput.value : '00:00') + ':00';
                    }
                    input.value = val;
                    _remove(picker);
                    if (typeof options.done === 'function') options.done(val);
                    // Trigger change event
                    input.dispatchEvent(new Event('change'));
                }

                // Close on outside click
                var closeHandler = function() {
                    _remove(picker);
                    document.removeEventListener('click', closeHandler);
                };
                setTimeout(function() {
                    document.addEventListener('click', closeHandler);
                }, 10);
            });

            // Set initial value
            if (options.value) {
                input.value = options.value;
            }
        }
    };

    // ========== Upload Module ==========
    var upload = {
        render: function(options) {
            var elem = typeof options.elem === 'string' ? document.querySelector(options.elem) : options.elem;
            if (!elem) return;

            var fileInput = _el('input', { type: 'file', style: 'display:none' });
            if (options.accept === 'images') fileInput.setAttribute('accept', 'image/*');
            else if (options.acceptMime) fileInput.setAttribute('accept', options.acceptMime);
            if (options.multiple) fileInput.setAttribute('multiple', 'multiple');

            document.body.appendChild(fileInput);

            elem.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });

            // Drag and drop support
            if (options.drag !== false) {
                elem.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    elem.classList.add('layui-upload-drag-over');
                });
                elem.addEventListener('dragleave', function() {
                    elem.classList.remove('layui-upload-drag-over');
                });
                elem.addEventListener('drop', function(e) {
                    e.preventDefault();
                    elem.classList.remove('layui-upload-drag-over');
                    var files = e.dataTransfer.files;
                    _handleFiles(files);
                });
            }

            fileInput.addEventListener('change', function() {
                _handleFiles(this.files);
                this.value = '';
            });

            function _handleFiles(files) {
                if (!files || !files.length) return;
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    // Check size
                    if (options.size && file.size > options.size * 1024) {
                        if (typeof options.error === 'function') options.error(i, 'File exceeds size limit');
                        layer.msg('File exceeds ' + (options.size / 1024) + 'MB limit', { icon: 2 });
                        continue;
                    }
                    // Check type
                    if (options.exts) {
                        var ext = file.name.split('.').pop().toLowerCase();
                        if (options.exts.split('|').indexOf(ext) === -1) {
                            layer.msg('File type not allowed', { icon: 2 });
                            continue;
                        }
                    }
                    if (typeof options.choose === 'function') options.choose(file);
                    if (options.auto !== false && options.url) {
                        var fd = new FormData();
                        fd.append(options.field || 'file', file);
                        fetch(options.url, { method: 'POST', body: fd, credentials: 'include' })
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                if (typeof options.done === 'function') options.done(res, i);
                            })
                            .catch(function(err) {
                                if (typeof options.error === 'function') options.error(i, err);
                            });
                    } else if (typeof options.done === 'function') {
                        options.done({ code: 0, data: { src: URL.createObjectURL(file), name: file.name, size: file.size } }, i);
                    }
                }
                if (typeof options.allDone === 'function') options.allDone({ total: files.length });
            }
        }
    };

    // ========== Rate (Stars) Module ==========
    var rate = {
        render: function(options) {
            var container = typeof options.elem === 'string' ? document.querySelector(options.elem) : options.elem;
            if (!container) return;

            var length = options.length || 5;
            var value = options.value || 0;
            var readonly = options.readonly || false;
            var half = options.half || false;
            var setText = options.setText;

            function renderStars(val) {
                var html = '';
                for (var i = 1; i <= length; i++) {
                    var filled = i <= val ? ' layui-rate-star-full' : '';
                    html += '<span class="layui-rate-star' + filled + '" data-val="' + i + '">\u2605</span>';
                }
                container.innerHTML = '<div class="layui-rate">' + html + '</div>';
                if (typeof setText === 'function') setText(val);
            }

            renderStars(value);

            if (!readonly) {
                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('layui-rate-star')) {
                        value = parseInt(e.target.getAttribute('data-val'), 10);
                        renderStars(value);
                        if (typeof options.choose === 'function') options.choose(value);
                    }
                });
                container.addEventListener('mouseover', function(e) {
                    if (e.target.classList.contains('layui-rate-star')) {
                        var hoverVal = parseInt(e.target.getAttribute('data-val'), 10);
                        container.querySelectorAll('.layui-rate-star').forEach(function(star) {
                            var sv = parseInt(star.getAttribute('data-val'), 10);
                            star.classList.toggle('layui-rate-star-full', sv <= hoverVal);
                        });
                    }
                });
                container.addEventListener('mouseleave', function() {
                    renderStars(value);
                });
            }

            return { config: { value: value }, setvalue: function(v) { value = v; renderStars(v); } };
        }
    };

    // ========== Element Module (tabs, etc) ==========
    var element = {
        init: function() { this.render(); },

        render: function(type, filter) {
            // Process tabs
            document.querySelectorAll('.layui-tab').forEach(function(tab) {
                var titles = tab.querySelectorAll('.layui-tab-title li');
                var items = tab.querySelectorAll('.layui-tab-item');
                titles.forEach(function(li, idx) {
                    li.addEventListener('click', function() {
                        titles.forEach(function(t) { t.classList.remove('layui-this'); });
                        items.forEach(function(it) { it.classList.remove('layui-show'); });
                        li.classList.add('layui-this');
                        if (items[idx]) items[idx].classList.add('layui-show');
                        // Trigger event
                        var tabFilter = tab.getAttribute('lay-filter');
                        if (tabFilter && _elementEvents['tab_' + tabFilter]) {
                            _elementEvents['tab_' + tabFilter]({ index: idx, elem: li });
                        }
                    });
                });
            });
        },

        on: function(event, callback) {
            var match = event.match(/^(\w+)\((\w+)\)$/);
            if (match) {
                _elementEvents[match[1] + '_' + match[2]] = callback;
            }
        }
    };

    // ========== Module System ==========
    var modules = {
        layer: layer,
        form: form,
        table: table,
        laypage: laypage,
        laydate: laydate,
        upload: upload,
        rate: rate,
        element: element
    };

    var layui = {
        v: '2.9.0-shim',
        modules: modules,
        use: function(mods, callback) {
            if (typeof mods === 'string') mods = [mods];
            var resolved = [];
            mods.forEach(function(name) {
                resolved.push(modules[name] || {});
            });
            if (typeof callback === 'function') {
                callback.apply(null, resolved);
            }
            return this;
        },
        define: function(name, fn) {
            if (typeof fn === 'function') {
                var exports = {};
                fn(exports);
                modules[name] = exports;
            }
        },
        layer: layer,
        form: form,
        table: table,
        laypage: laypage,
        laydate: laydate,
        upload: upload,
        rate: rate,
        element: element,
        $: function(selector) {
            return document.querySelector(selector);
        }
    };

    return layui;
})();
