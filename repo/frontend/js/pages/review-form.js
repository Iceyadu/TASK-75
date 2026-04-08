/**
 * review-form.js - Review Create Page
 * Order summary, star rating, review text with counter, media upload, submit.
 */
var ReviewFormPage = (function () {
    'use strict';

    var MAX_FILES = 5;
    var PHOTO_MAX_BYTES = 5 * 1024 * 1024;
    var VIDEO_MAX_BYTES = 50 * 1024 * 1024;
    var ACCEPT_TYPES = '.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm';
    var MAX_TEXT = 1000;

    var state = {
        orderId: null,
        order: null,
        rating: 0,
        text: '',
        files: [],
        loading: false,
        submitting: false,
        error: null,
        submitError: null
    };

    function isImage(file) {
        return /\.(jpg|jpeg|png|gif|webp)$/i.test(file.name);
    }

    function isVideo(file) {
        return /\.(mp4|webm)$/i.test(file.name);
    }

    function fileSizeOk(file) {
        if (isImage(file)) return file.size <= PHOTO_MAX_BYTES;
        if (isVideo(file)) return file.size <= VIDEO_MAX_BYTES;
        return false;
    }

    function fileMaxLabel(file) {
        if (isImage(file)) return '5 MB';
        if (isVideo(file)) return '50 MB';
        return '';
    }

    function renderOrderSummary(order) {
        if (!order) return '';
        var title = order.listing_summary ? RCUtil.escapeHtml(order.listing_summary.title) : 'Order #' + order.id;
        var pName = order.passenger_info ? RCUtil.escapeHtml(order.passenger_info.name) : '';
        var dName = order.driver_info ? RCUtil.escapeHtml(order.driver_info.name) : '';

        return '<div class="layui-card" style="margin-bottom:20px;">' +
            '<div class="layui-card-header">Trip Summary</div>' +
            '<div class="layui-card-body" style="padding:16px;">' +
            '<h4 style="margin:0 0 8px 0;">' + title + '</h4>' +
            '<div style="font-size:13px;color:#666;">' +
            (pName ? '<span style="margin-right:16px;">Passenger: <strong>' + pName + '</strong></span>' : '') +
            (dName ? '<span>Driver: <strong>' + dName + '</strong></span>' : '') +
            '</div>' +
            (order.completed_at ? '<div style="font-size:12px;color:#999;margin-top:4px;">Completed: ' + RCUtil.formatDate(order.completed_at) + '</div>' : '') +
            '</div></div>';
    }

    function renderStars() {
        var html = '<div class="rc-star-rating" style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:8px;font-weight:500;">Rating <span style="color:#FF5722;">*</span></label>' +
            '<div id="rc-review-stars" style="font-size:28px;cursor:pointer;">';
        for (var i = 1; i <= 5; i++) {
            var filled = i <= state.rating;
            html += '<span class="rc-star" data-value="' + i + '" style="color:' +
                (filled ? '#FFB800' : '#d2d2d2') + ';margin-right:4px;user-select:none;">' +
                (filled ? '\u2605' : '\u2606') + '</span>';
        }
        html += '</div>';
        if (state.rating > 0) {
            html += '<span style="font-size:13px;color:#666;margin-left:4px;">' + state.rating + ' of 5</span>';
        }
        html += '</div>';
        return html;
    }

    function renderTextArea() {
        var len = state.text.length;
        var nearLimit = len > MAX_TEXT * 0.9;
        var overLimit = len > MAX_TEXT;
        var counterColor = overLimit ? '#FF5722' : (nearLimit ? '#FF9800' : '#999');

        return '<div style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:8px;font-weight:500;">Review <span style="color:#FF5722;">*</span></label>' +
            '<textarea id="rc-review-text" class="layui-textarea" placeholder="Share your experience..." ' +
            'maxlength="' + (MAX_TEXT + 50) + '" style="height:120px;">' + RCUtil.escapeHtml(state.text) + '</textarea>' +
            '<div style="text-align:right;font-size:12px;color:' + counterColor + ';margin-top:4px;">' +
            '<span id="rc-review-char-count">' + len + '</span>/' + MAX_TEXT + '</div></div>';
    }

    function renderFileUpload() {
        var html = '<div style="margin-bottom:20px;">' +
            '<label style="display:block;margin-bottom:8px;font-weight:500;">Media (' + state.files.length + ' of ' + MAX_FILES + ' files)</label>';

        // Drop zone
        html += '<div id="rc-drop-zone" style="border:2px dashed #d2d2d2;border-radius:4px;padding:24px;text-align:center;' +
            'color:#999;cursor:pointer;margin-bottom:12px;transition:border-color 0.2s;">' +
            '<i class="layui-icon layui-icon-upload" style="font-size:32px;display:block;margin-bottom:6px;"></i>' +
            'Drag files here or <span style="color:#1E9FFF;">click to browse</span>' +
            '<div style="font-size:12px;margin-top:6px;">Photos (max 5 MB), Videos (max 50 MB)</div>' +
            '<input type="file" id="rc-file-input" multiple accept="' + ACCEPT_TYPES + '" style="display:none;">' +
            '</div>';

        // File list
        if (state.files.length > 0) {
            html += '<div id="rc-file-list">';
            state.files.forEach(function (f, idx) {
                var ok = fileSizeOk(f);
                var preview = '';
                if (f._preview && isImage(f)) {
                    preview = '<img src="' + f._preview + '" style="width:48px;height:48px;object-fit:cover;border-radius:4px;margin-right:10px;">';
                } else if (isVideo(f)) {
                    preview = '<div style="width:48px;height:48px;background:#eee;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;">' +
                        '<i class="layui-icon layui-icon-play" style="font-size:20px;color:#666;"></i></div>';
                }

                html += '<div style="display:flex;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f0;" data-file-idx="' + idx + '">' +
                    preview +
                    '<div style="flex:1;min-width:0;">' +
                    '<div style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + RCUtil.escapeHtml(f.name) + '</div>' +
                    '<div style="font-size:12px;color:' + (ok ? '#999' : '#FF5722') + ';">' +
                    RCUtil.formatFileSize(f.size) +
                    (!ok ? ' (exceeds ' + fileMaxLabel(f) + ' limit)' : '') +
                    '</div></div>' +
                    '<button type="button" class="layui-btn layui-btn-xs layui-btn-danger rc-remove-file" data-idx="' + idx + '" style="margin-left:8px;">' +
                    '<i class="layui-icon layui-icon-close"></i></button></div>';
            });
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    function canSubmit() {
        if (state.rating === 0) return false;
        if (state.text.trim().length === 0) return false;
        if (state.text.length > MAX_TEXT) return false;
        if (state.submitting) return false;
        // Check all files pass size validation
        for (var i = 0; i < state.files.length; i++) {
            if (!fileSizeOk(state.files[i])) return false;
        }
        return true;
    }

    function renderPage(container) {
        if (state.loading) {
            container.innerHTML = '<div style="max-width:700px;margin:0 auto;padding:20px;">' + RCUtil.skeleton(4) + '</div>';
            return;
        }
        if (state.error) {
            container.innerHTML = '<div style="max-width:700px;margin:0 auto;padding:20px;">' +
                '<div style="color:#FF5722;padding:20px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.error) + '</div>' +
                '<a href="#/orders" class="layui-btn layui-btn-primary layui-btn-sm">Back to Orders</a></div>';
            return;
        }

        var html = '<div style="max-width:700px;margin:0 auto;padding:20px;">' +
            '<a href="#/orders/' + state.orderId + '" style="display:inline-block;margin-bottom:16px;color:#666;text-decoration:none;">' +
            '<i class="layui-icon layui-icon-left"></i> Back to Order</a>' +
            '<h2 style="margin-bottom:20px;">Leave a Review</h2>';

        html += renderOrderSummary(state.order);
        html += '<div class="layui-card"><div class="layui-card-body" style="padding:20px;">';
        html += renderStars();
        html += renderTextArea();
        html += renderFileUpload();

        // Submit error
        if (state.submitError) {
            html += '<div style="color:#FF5722;padding:10px 0;font-size:13px;"><i class="layui-icon layui-icon-close-fill"></i> ' +
                RCUtil.escapeHtml(state.submitError) + '</div>';
        }

        var disabled = !canSubmit() || state.submitting;
        html += '<button type="button" id="rc-review-submit" class="layui-btn' +
            (disabled ? ' layui-btn-disabled' : '') + '" ' + (disabled ? 'disabled' : '') + '>' +
            (state.submitting ? '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Submitting...' : 'Submit Review') +
            '</button>';

        html += '</div></div></div>';
        container.innerHTML = html;
    }

    function addFiles(newFiles) {
        var remaining = MAX_FILES - state.files.length;
        if (remaining <= 0) {
            layui.layer.msg('Maximum ' + MAX_FILES + ' files allowed.', { icon: 0 });
            return;
        }

        var toAdd = Array.prototype.slice.call(newFiles, 0, remaining);
        var loadCount = 0;
        toAdd.forEach(function (file) {
            if (isImage(file)) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    file._preview = e.target.result;
                    loadCount++;
                    if (loadCount === toAdd.length) {
                        state.files = state.files.concat(toAdd);
                    }
                };
                reader.readAsDataURL(file);
            } else {
                loadCount++;
            }
        });

        // Fallback: if no images need preview loading, add immediately
        var hasImages = toAdd.some(function (f) { return isImage(f); });
        if (!hasImages) {
            state.files = state.files.concat(toAdd);
        } else {
            // Wait a bit for FileReader
            setTimeout(function () {
                if (state.files.indexOf(toAdd[0]) === -1) {
                    state.files = state.files.concat(toAdd);
                }
            }, 200);
        }
    }

    function bindEvents(container) {
        // Stars
        container.querySelectorAll('.rc-star').forEach(function (star) {
            star.addEventListener('click', function () {
                state.rating = parseInt(this.getAttribute('data-value'), 10);
                renderPage(container);
                bindEvents(container);
            });
            star.addEventListener('mouseenter', function () {
                var val = parseInt(this.getAttribute('data-value'), 10);
                container.querySelectorAll('.rc-star').forEach(function (s) {
                    var sv = parseInt(s.getAttribute('data-value'), 10);
                    s.style.color = sv <= val ? '#FFB800' : '#d2d2d2';
                    s.textContent = sv <= val ? '\u2605' : '\u2606';
                });
            });
            star.addEventListener('mouseleave', function () {
                container.querySelectorAll('.rc-star').forEach(function (s) {
                    var sv = parseInt(s.getAttribute('data-value'), 10);
                    s.style.color = sv <= state.rating ? '#FFB800' : '#d2d2d2';
                    s.textContent = sv <= state.rating ? '\u2605' : '\u2606';
                });
            });
        });

        // Text area
        var textArea = container.querySelector('#rc-review-text');
        if (textArea) {
            textArea.addEventListener('input', function () {
                state.text = this.value;
                var counter = container.querySelector('#rc-review-char-count');
                if (counter) {
                    counter.textContent = state.text.length;
                    var nearLimit = state.text.length > MAX_TEXT * 0.9;
                    var overLimit = state.text.length > MAX_TEXT;
                    counter.parentElement.style.color = overLimit ? '#FF5722' : (nearLimit ? '#FF9800' : '#999');
                }
                // Update submit button
                var submitBtn = container.querySelector('#rc-review-submit');
                if (submitBtn) {
                    var ok = canSubmit();
                    submitBtn.disabled = !ok;
                    if (ok) submitBtn.classList.remove('layui-btn-disabled');
                    else submitBtn.classList.add('layui-btn-disabled');
                }
            });
        }

        // File input
        var dropZone = container.querySelector('#rc-drop-zone');
        var fileInput = container.querySelector('#rc-file-input');
        if (dropZone && fileInput) {
            dropZone.addEventListener('click', function () {
                fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                if (this.files && this.files.length) {
                    addFiles(this.files);
                    setTimeout(function () {
                        renderPage(container);
                        bindEvents(container);
                    }, 300);
                }
            });
            dropZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                this.style.borderColor = '#1E9FFF';
            });
            dropZone.addEventListener('dragleave', function () {
                this.style.borderColor = '#d2d2d2';
            });
            dropZone.addEventListener('drop', function (e) {
                e.preventDefault();
                this.style.borderColor = '#d2d2d2';
                if (e.dataTransfer && e.dataTransfer.files.length) {
                    addFiles(e.dataTransfer.files);
                    setTimeout(function () {
                        renderPage(container);
                        bindEvents(container);
                    }, 300);
                }
            });
        }

        // Remove file
        container.querySelectorAll('.rc-remove-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-idx'), 10);
                state.files.splice(idx, 1);
                renderPage(container);
                bindEvents(container);
            });
        });

        // Submit
        var submitBtn = container.querySelector('#rc-review-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                handleSubmit(container);
            });
        }
    }

    function handleSubmit(container) {
        if (!canSubmit() || state.submitting) return;

        state.submitting = true;
        state.submitError = null;
        renderPage(container);
        bindEvents(container);

        var formData = new FormData();
        formData.append('order_id', state.orderId);
        formData.append('rating', state.rating);
        formData.append('text', state.text.trim());
        state.files.forEach(function (f) {
            formData.append('files[]', f);
        });

        RCApi.createReview(formData).then(function (envelope) {
            state.submitting = false;
            layui.layer.msg(envelope.message || 'Review submitted!', { icon: 1 });

            // Check for duplicate detection
            if (envelope.data && envelope.data.status === 'pending') {
                layui.layer.msg('This review appears to be similar to one you recently submitted. It has been sent for review.', {
                    icon: 0, time: 4000
                });
            }

            RCRouter.navigate('#/orders/' + state.orderId);
        }).catch(function (err) {
            state.submitting = false;
            var msg = (err && err.message) ? err.message : 'Failed to submit review';

            // Rate limit error detection
            if (err && err.status === 429) {
                var retryAfter = err.retry_after || '';
                msg = "You've submitted 3 reviews in the last hour. Please wait " +
                    (retryAfter ? retryAfter : 'a while') + ' before submitting another.';
            }

            state.submitError = msg;
            renderPage(container);
            bindEvents(container);
        });
    }

    function loadOrder(container) {
        state.loading = true;
        renderPage(container);

        RCApi.getOrder(state.orderId).then(function (envelope) {
            state.loading = false;
            state.order = envelope.data;
            renderPage(container);
            bindEvents(container);
        }).catch(function (err) {
            state.loading = false;
            state.error = (err && err.message) ? err.message : 'Failed to load order';
            renderPage(container);
        });
    }

    return {
        render: function (container, params) {
            state.orderId = params && params.id ? params.id : null;
            state.order = null;
            state.rating = 0;
            state.text = '';
            state.files = [];
            state.error = null;
            state.submitError = null;
            state.submitting = false;

            if (!state.orderId) {
                state.error = 'No order ID provided';
                renderPage(container);
                return;
            }
            loadOrder(container);
        }
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReviewFormPage;
}
