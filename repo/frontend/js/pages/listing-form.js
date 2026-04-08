var ListingFormPage = {
    renderCreate: function(container) {
        this._render(container, null);
    },

    renderEdit: function(container, params) {
        var listingId = params && params.id ? params.id : null;
        if (!listingId) {
            container.innerHTML = RCUtil.emptyState('Listing not found.', 'Browse Listings', '#/listings');
            return;
        }
        container.innerHTML = '<div id="listing-form-loading">' + RCUtil.skeleton(3) + '</div>';
        var self = this;
        RCApi.getListing(listingId).then(function(envelope) {
            var data = envelope.data || envelope;
            self._render(container, data.listing || data);
        }).catch(function(err) {
            container.innerHTML =
                '<div class="rc-empty-state rc-error-state" style="text-align:center;padding:40px;color:#FF5722;">' +
                '<p>' + RCUtil.escapeHtml(err.message || 'Failed to load listing') + '</p>' +
                '<a href="#/my-listings" class="layui-btn layui-btn-sm">My Listings</a></div>';
        });
    },

    _render: function(container, existingListing) {
        var isEdit = !!existingListing;
        var l = existingListing || {};
        var originalData = isEdit ? JSON.parse(JSON.stringify(l)) : null;

        container.innerHTML =
            '<div class="rc-page-header">' +
            '  <a href="' + (isEdit ? '#/listings/' + l.id : '#/listings') + '" style="color:#009688;text-decoration:none;font-size:13px;"><i class="layui-icon layui-icon-left"></i> Back</a>' +
            '  <h2>' + (isEdit ? 'Edit Listing' : 'Create New Listing') + '</h2>' +
            '</div>' +
            '<div class="layui-row layui-col-space20">' +
            '  <div class="layui-col-md8">' +
            '    <div class="layui-card">' +
            '      <div class="layui-card-body">' +
            '        <form class="layui-form" id="listing-form" lay-filter="listing-form">' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Title <span style="color:red;">*</span></label>' +
            '            <div class="layui-input-block">' +
            '              <input type="text" name="title" id="field-title" class="layui-input" placeholder="Enter a descriptive title (5-200 chars)" maxlength="200" value="' + RCUtil.escapeHtml(l.title || '') + '">' +
            '              <div class="rc-field-hint" style="font-size:12px;color:#999;margin-top:4px;"><span id="title-count">' + (l.title || '').length + '</span>/200</div>' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item layui-form-text">' +
            '            <label class="layui-form-label">Description</label>' +
            '            <div class="layui-input-block">' +
            '              <textarea name="description" id="field-description" class="layui-textarea" placeholder="Describe your trip (max 2000 chars)" maxlength="2000" style="min-height:120px;">' + RCUtil.escapeHtml(l.description || '') + '</textarea>' +
            '              <div class="rc-field-hint" style="font-size:12px;color:#999;margin-top:4px;"><span id="desc-count">' + (l.description || '').length + '</span>/2000</div>' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Pickup <span style="color:red;">*</span></label>' +
            '            <div class="layui-input-block">' +
            '              <input type="text" name="pickup_address" id="field-pickup" class="layui-input" placeholder="Pickup address" value="' + RCUtil.escapeHtml(l.pickup_address || '') + '">' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Drop-off <span style="color:red;">*</span></label>' +
            '            <div class="layui-input-block">' +
            '              <input type="text" name="dropoff_address" id="field-dropoff" class="layui-input" placeholder="Drop-off address" value="' + RCUtil.escapeHtml(l.dropoff_address || '') + '">' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Riders <span style="color:red;">*</span></label>' +
            '            <div class="layui-input-block" style="display:flex;align-items:center;gap:10px;">' +
            '              <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="rider-dec">-</button>' +
            '              <input type="number" name="rider_count" id="field-rider-count" class="layui-input" style="width:80px;text-align:center;" min="1" max="6" value="' + (l.rider_count || 1) + '" readonly>' +
            '              <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="rider-inc">+</button>' +
            '              <span style="color:#999;font-size:12px;">1 - 6 riders</span>' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Vehicle Type <span style="color:red;">*</span></label>' +
            '            <div class="layui-input-block">' +
            '              <input type="radio" name="vehicle_type" value="sedan" title="Sedan"' + (l.vehicle_type === 'sedan' || !l.vehicle_type ? ' checked' : '') + '>' +
            '              <input type="radio" name="vehicle_type" value="suv" title="SUV"' + (l.vehicle_type === 'suv' ? ' checked' : '') + '>' +
            '              <input type="radio" name="vehicle_type" value="van" title="Van"' + (l.vehicle_type === 'van' ? ' checked' : '') + '>' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item layui-form-text">' +
            '            <label class="layui-form-label">Baggage Notes</label>' +
            '            <div class="layui-input-block">' +
            '              <textarea name="baggage_notes" id="field-baggage" class="layui-textarea" placeholder="Any baggage details (max 500 chars)" maxlength="500" style="min-height:60px;">' + RCUtil.escapeHtml(l.baggage_notes || '') + '</textarea>' +
            '              <div class="rc-field-hint" style="font-size:12px;color:#999;margin-top:4px;"><span id="baggage-count">' + (l.baggage_notes || '').length + '</span>/500</div>' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Time Window <span style="color:red;">*</span></label>' +
            '            <div class="layui-input-inline" style="width:200px;">' +
            '              <input type="text" name="time_window_start" id="field-time-start" class="layui-input" placeholder="Start time" readonly>' +
            '            </div>' +
            '            <div class="layui-form-mid">to</div>' +
            '            <div class="layui-input-inline" style="width:200px;">' +
            '              <input type="text" name="time_window_end" id="field-time-end" class="layui-input" placeholder="End time" readonly>' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item">' +
            '            <label class="layui-form-label">Tags</label>' +
            '            <div class="layui-input-block">' +
            '              <input type="text" id="field-tags-input" class="layui-input" placeholder="Type a tag and press Enter or comma (max 10)">' +
            '              <div id="tags-preview" style="margin-top:8px;"></div>' +
            '              <input type="hidden" name="tags" id="field-tags-hidden" value="' + RCUtil.escapeHtml((l.tags || []).join(',')) + '">' +
            '            </div>' +
            '          </div>' +
            '          <div class="layui-form-item" style="margin-top:20px;">' +
            '            <div class="layui-input-block">' +
            '              <button type="button" class="layui-btn layui-btn-primary" id="btn-save-draft">Save Draft</button>' +
            '              <button type="button" class="layui-btn" id="btn-publish">Publish</button>' +
            '            </div>' +
            '          </div>' +
            '        </form>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="layui-col-md4">' +
            '    <div id="change-preview-panel"></div>' +
            '  </div>' +
            '</div>';

        layui.form.render(null, 'listing-form');

        // Date pickers with 12-hour format
        layui.laydate.render({
            elem: '#field-time-start',
            type: 'datetime',
            format: 'yyyy-MM-dd hh:mm A',
            value: l.time_window_start ? formatForPicker(l.time_window_start) : '',
            done: function() { updateChangePreview(); }
        });
        layui.laydate.render({
            elem: '#field-time-end',
            type: 'datetime',
            format: 'yyyy-MM-dd hh:mm A',
            value: l.time_window_end ? formatForPicker(l.time_window_end) : '',
            done: function() { updateChangePreview(); }
        });

        function formatForPicker(iso) {
            if (!iso) return '';
            return RCUtil.formatDateTime(iso);
        }

        // Character counters
        var titleField = document.getElementById('field-title');
        var descField = document.getElementById('field-description');
        var baggageField = document.getElementById('field-baggage');

        titleField.addEventListener('input', function() {
            document.getElementById('title-count').textContent = this.value.length;
            updateChangePreview();
        });
        descField.addEventListener('input', function() {
            document.getElementById('desc-count').textContent = this.value.length;
            updateChangePreview();
        });
        baggageField.addEventListener('input', function() {
            document.getElementById('baggage-count').textContent = this.value.length;
            updateChangePreview();
        });

        // Rider count stepper
        var riderField = document.getElementById('field-rider-count');
        document.getElementById('rider-dec').addEventListener('click', function() {
            var val = parseInt(riderField.value, 10);
            if (val > 1) { riderField.value = val - 1; updateChangePreview(); }
        });
        document.getElementById('rider-inc').addEventListener('click', function() {
            var val = parseInt(riderField.value, 10);
            if (val < 6) { riderField.value = val + 1; updateChangePreview(); }
        });

        // Tags management
        var tags = l.tags ? l.tags.slice() : [];
        var tagsInput = document.getElementById('field-tags-input');
        var tagsPreview = document.getElementById('tags-preview');
        var tagsHidden = document.getElementById('field-tags-hidden');

        function renderTagChips() {
            tagsPreview.innerHTML = tags.map(function(t, idx) {
                return '<span class="rc-tag-chip" style="display:inline-block;padding:3px 8px;margin:3px;background:#e8f5e9;color:#388e3c;border-radius:12px;font-size:12px;">' +
                    RCUtil.escapeHtml(t) +
                    ' <i class="layui-icon layui-icon-close" data-idx="' + idx + '" style="cursor:pointer;font-size:10px;margin-left:4px;"></i></span>';
            }).join('');
            tagsHidden.value = tags.join(',');
            var closeIcons = tagsPreview.querySelectorAll('.layui-icon-close');
            for (var ci = 0; ci < closeIcons.length; ci++) {
                closeIcons[ci].addEventListener('click', function() {
                    var removeIdx = parseInt(this.getAttribute('data-idx'), 10);
                    tags.splice(removeIdx, 1);
                    renderTagChips();
                    updateChangePreview();
                });
            }
        }

        tagsInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                var val = this.value.replace(/,/g, '').trim();
                if (val && tags.length < 10 && tags.indexOf(val) === -1) {
                    tags.push(val);
                    renderTagChips();
                    updateChangePreview();
                } else if (tags.length >= 10) {
                    layui.layer.msg('Maximum 10 tags allowed', { icon: 0 });
                }
                this.value = '';
            }
        });

        renderTagChips();

        // Change preview for edit mode
        function updateChangePreview() {
            if (!isEdit) return;
            var panel = document.getElementById('change-preview-panel');
            var formData = gatherFormData();
            var changes = [];

            if (formData.title !== (originalData.title || '')) changes.push({ field: 'Title', oldVal: originalData.title || '', newVal: formData.title });
            if (formData.description !== (originalData.description || '')) changes.push({ field: 'Description', oldVal: RCUtil.truncate(originalData.description || '', 60), newVal: RCUtil.truncate(formData.description, 60) });
            if (formData.pickup_address !== (originalData.pickup_address || '')) changes.push({ field: 'Pickup', oldVal: originalData.pickup_address || '', newVal: formData.pickup_address });
            if (formData.dropoff_address !== (originalData.dropoff_address || '')) changes.push({ field: 'Drop-off', oldVal: originalData.dropoff_address || '', newVal: formData.dropoff_address });
            if (formData.rider_count !== (originalData.rider_count || 1)) changes.push({ field: 'Riders', oldVal: String(originalData.rider_count || 1), newVal: String(formData.rider_count) });
            if (formData.vehicle_type !== (originalData.vehicle_type || '')) changes.push({ field: 'Vehicle Type', oldVal: originalData.vehicle_type || '', newVal: formData.vehicle_type });
            if (formData.baggage_notes !== (originalData.baggage_notes || '')) changes.push({ field: 'Baggage Notes', oldVal: RCUtil.truncate(originalData.baggage_notes || '', 60), newVal: RCUtil.truncate(formData.baggage_notes, 60) });
            if ((formData.tags || []).join(',') !== (originalData.tags || []).join(',')) changes.push({ field: 'Tags', oldVal: (originalData.tags || []).join(', '), newVal: (formData.tags || []).join(', ') });

            if (changes.length === 0) {
                panel.innerHTML = '';
                return;
            }

            panel.innerHTML =
                '<div class="layui-card">' +
                '  <div class="layui-card-header">Changes Preview</div>' +
                '  <div class="layui-card-body">' +
                changes.map(function(c) {
                    return '<div style="margin-bottom:10px;">' +
                        '<strong style="font-size:12px;color:#666;">' + RCUtil.escapeHtml(c.field) + '</strong>' +
                        '<div style="font-size:12px;color:#d9534f;text-decoration:line-through;background:#ffeaea;padding:2px 6px;border-radius:3px;margin:2px 0;">' + RCUtil.escapeHtml(c.oldVal || '(empty)') + '</div>' +
                        '<div style="font-size:12px;color:#5cb85c;background:#eaffea;padding:2px 6px;border-radius:3px;">' + RCUtil.escapeHtml(c.newVal || '(empty)') + '</div>' +
                        '</div>';
                }).join('') +
                '    <button type="button" class="layui-btn layui-btn-xs layui-btn-primary" id="btn-view-full-diff">View Full Diff</button>' +
                '  </div>' +
                '</div>';

            var diffBtn = document.getElementById('btn-view-full-diff');
            if (diffBtn) {
                diffBtn.addEventListener('click', function() {
                    showFullDiffModal(changes);
                });
            }
        }

        function showFullDiffModal(changes) {
            var content = '<table class="layui-table"><thead><tr><th>Field</th><th>Old Value</th><th>New Value</th></tr></thead><tbody>' +
                changes.map(function(c) {
                    return '<tr><td><strong>' + RCUtil.escapeHtml(c.field) + '</strong></td>' +
                        '<td style="background:#ffeaea;color:#d9534f;text-decoration:line-through;">' + RCUtil.escapeHtml(c.oldVal || '(empty)') + '</td>' +
                        '<td style="background:#eaffea;color:#5cb85c;">' + RCUtil.escapeHtml(c.newVal || '(empty)') + '</td></tr>';
                }).join('') +
                '</tbody></table>';
            layui.layer.open({
                type: 1,
                title: 'Full Change Diff',
                area: ['600px', '400px'],
                content: '<div style="padding:15px;">' + content + '</div>'
            });
        }

        // Gather form data
        function gatherFormData() {
            var vehicleRadios = document.querySelectorAll('input[name="vehicle_type"]');
            var vehicleType = 'sedan';
            for (var v = 0; v < vehicleRadios.length; v++) {
                if (vehicleRadios[v].checked) { vehicleType = vehicleRadios[v].value; break; }
            }
            return {
                title: titleField.value.trim(),
                description: descField.value.trim(),
                pickup_address: document.getElementById('field-pickup').value.trim(),
                dropoff_address: document.getElementById('field-dropoff').value.trim(),
                rider_count: parseInt(riderField.value, 10) || 1,
                vehicle_type: vehicleType,
                baggage_notes: baggageField.value.trim(),
                time_window_start: document.getElementById('field-time-start').value,
                time_window_end: document.getElementById('field-time-end').value,
                tags: tags.slice()
            };
        }

        // Validation
        function validate(data) {
            RCUtil.clearFieldErrors();
            var errors = [];
            if (!data.title || data.title.length < 5) errors.push({ field: 'title', message: 'Title must be at least 5 characters' });
            if (data.title && data.title.length > 200) errors.push({ field: 'title', message: 'Title cannot exceed 200 characters' });
            if (data.description && data.description.length > 2000) errors.push({ field: 'description', message: 'Description cannot exceed 2000 characters' });
            if (!data.pickup_address) errors.push({ field: 'pickup_address', message: 'Pickup address is required' });
            if (!data.dropoff_address) errors.push({ field: 'dropoff_address', message: 'Drop-off address is required' });
            if (!data.rider_count || data.rider_count < 1 || data.rider_count > 6) errors.push({ field: 'rider_count', message: 'Rider count must be 1-6' });
            if (!data.time_window_start) errors.push({ field: 'time_window_start', message: 'Start time is required' });
            if (!data.time_window_end) errors.push({ field: 'time_window_end', message: 'End time is required' });
            if (data.time_window_start && data.time_window_end && data.time_window_end <= data.time_window_start) {
                errors.push({ field: 'time_window_end', message: 'End time must be after start time' });
            }
            if (data.baggage_notes && data.baggage_notes.length > 500) errors.push({ field: 'baggage_notes', message: 'Baggage notes cannot exceed 500 characters' });
            if (data.tags && data.tags.length > 10) errors.push({ field: 'tags', message: 'Maximum 10 tags allowed' });
            return errors;
        }

        function submitForm(publish) {
            var data = gatherFormData();
            var errors = validate(data);
            if (errors.length > 0) {
                RCUtil.showFieldErrors(errors);
                layui.layer.msg(errors[0].message, { icon: 2 });
                return;
            }

            var btnDraft = document.getElementById('btn-save-draft');
            var btnPub = document.getElementById('btn-publish');
            btnDraft.disabled = true;
            btnPub.disabled = true;

            var submitBtn = publish ? btnPub : btnDraft;
            var origText = submitBtn.textContent;
            submitBtn.innerHTML = '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Saving...';

            var apiCall;
            if (isEdit) {
                apiCall = RCApi.updateListing(l.id, data).then(function(envelope) {
                    var res = envelope.data || envelope;
                    if (publish) {
                        return RCApi.publishListing(l.id).then(function() { return res; });
                    }
                    return res;
                });
            } else {
                apiCall = RCApi.createListing(data).then(function(envelope) {
                    var res = envelope.data || envelope;
                    if (publish) {
                        var newId = res.listing ? res.listing.id : res.id;
                        return RCApi.publishListing(newId).then(function() { return res; });
                    }
                    return res;
                });
            }

            apiCall.then(function(res) {
                var newId = res.listing ? res.listing.id : (res.id || l.id);
                layui.layer.msg(publish ? 'Listing published!' : 'Draft saved!', { icon: 1 });
                setTimeout(function() {
                    RCRouter.navigate('#/listings/' + newId);
                }, 800);
            }).catch(function(err) {
                btnDraft.disabled = false;
                btnPub.disabled = false;
                submitBtn.textContent = origText;
                if (err.errors) {
                    RCUtil.showFieldErrors(err.errors);
                    layui.layer.msg('Please fix the errors below', { icon: 2 });
                } else {
                    layui.layer.msg(err.message || 'Failed to save listing', { icon: 2 });
                }
            });
        }

        // Button handlers
        document.getElementById('btn-save-draft').addEventListener('click', function() { submitForm(false); });
        document.getElementById('btn-publish').addEventListener('click', function() { submitForm(true); });

        // Listen for field changes in edit mode
        if (isEdit) {
            var inputs = container.querySelectorAll('input, textarea, select');
            for (var fi = 0; fi < inputs.length; fi++) {
                inputs[fi].addEventListener('change', function() { updateChangePreview(); });
            }
            layui.form.on('radio()', function() { updateChangePreview(); });
        }
    }
};
