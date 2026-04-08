var ListingVersionsPage = {
    render: function(container, params) {
        var listingId = params && params.id ? params.id : null;
        if (!listingId) {
            container.innerHTML = RCUtil.emptyState('Listing not found.', 'Browse Listings', '#/listings');
            return;
        }

        container.innerHTML =
            '<div id="versions-breadcrumb" style="margin-bottom:15px;font-size:13px;color:#999;">' +
            '  <a href="#/listings" style="color:#009688;">Listings</a> &gt; ' +
            '  <span id="breadcrumb-title">...</span> &gt; ' +
            '  <span>Version History</span>' +
            '</div>' +
            '<div class="rc-page-header" style="display:flex;justify-content:space-between;align-items:center;">' +
            '  <h2>Version History</h2>' +
            '  <button class="layui-btn layui-btn-sm layui-btn-normal" id="btn-compare" disabled>Compare Selected (0/2)</button>' +
            '</div>' +
            '<div id="versions-timeline">' + RCUtil.skeleton(4) + '</div>';

        var selectedVersions = [];
        var listingTitle = '';
        var versionsData = [];

        // Load listing info and versions in parallel
        Promise.all([
            RCApi.getListing(listingId),
            RCApi.getVersions(listingId)
        ]).then(function(results) {
            var listingEnvelope = results[0];
            var versionsEnvelope = results[1];
            var listingRes = listingEnvelope.data || listingEnvelope;
            var versionsRes = versionsEnvelope.data || versionsEnvelope;
            var listing = listingRes.listing || listingRes;
            listingTitle = listing.title || 'Untitled';
            versionsData = versionsRes.versions || versionsRes || [];

            document.getElementById('breadcrumb-title').innerHTML =
                '<a href="#/listings/' + listingId + '" style="color:#009688;">' + RCUtil.escapeHtml(RCUtil.truncate(listingTitle, 40)) + '</a>';

            renderTimeline(versionsData, listing.current_version);
        }).catch(function(err) {
            document.getElementById('versions-timeline').innerHTML =
                '<div class="rc-empty-state rc-error-state" style="text-align:center;padding:40px;color:#FF5722;">' +
                '<p>' + RCUtil.escapeHtml(err.message || 'Failed to load version history') + '</p>' +
                '<a href="#/listings/' + listingId + '" class="layui-btn layui-btn-sm">Back to Listing</a></div>';
        });

        function renderTimeline(versions, currentVersion) {
            var timelineDiv = document.getElementById('versions-timeline');

            if (!versions.length) {
                timelineDiv.innerHTML =
                    '<div class="rc-empty-state" style="text-align:center;padding:40px;color:#999;">' +
                    '<i class="layui-icon layui-icon-file" style="font-size:48px;display:block;margin-bottom:12px;"></i>' +
                    '<p>No version history available.</p></div>';
                return;
            }

            var html = '<div class="rc-timeline" style="position:relative;padding-left:40px;">';
            html += '<div style="position:absolute;left:18px;top:0;bottom:0;width:2px;background:#e6e6e6;"></div>';

            versions.forEach(function(v, idx) {
                var isCurrent = v.version === currentVersion || (idx === 0 && !currentVersion);
                var isChecked = selectedVersions.indexOf(v.version) > -1;
                var borderColor = isCurrent ? '#5cb85c' : '#e6e6e6';
                var bgColor = isCurrent ? '#f0fff0' : '#fff';

                var changeSummary = '';
                if (v.changes && v.changes.length) {
                    changeSummary = v.changes.map(function(c) {
                        return '<span style="display:inline-block;padding:2px 6px;margin:2px;background:#f0f0f0;border-radius:3px;font-size:11px;">' +
                            RCUtil.escapeHtml(c) + '</span>';
                    }).join('');
                } else if (v.change_summary) {
                    changeSummary = '<span style="font-size:12px;color:#666;">' + RCUtil.escapeHtml(v.change_summary) + '</span>';
                } else if (idx === versions.length - 1) {
                    changeSummary = '<span style="font-size:12px;color:#999;">Initial version</span>';
                }

                html +=
                    '<div class="rc-timeline-item" style="position:relative;margin-bottom:20px;">' +
                    '  <div style="position:absolute;left:-30px;top:8px;width:16px;height:16px;border-radius:50%;background:' + (isCurrent ? '#5cb85c' : '#009688') + ';border:3px solid #fff;box-shadow:0 0 0 2px ' + borderColor + ';"></div>' +
                    '  <div class="layui-card" style="border-left:3px solid ' + borderColor + ';background:' + bgColor + ';">' +
                    '    <div class="layui-card-body" style="padding:15px;">' +
                    '      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
                    '        <div>' +
                    '          <strong style="font-size:15px;">Version ' + v.version + '</strong>' +
                    (isCurrent ? ' <span style="background:#5cb85c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:6px;">Current</span>' : '') +
                    '        </div>' +
                    '        <div>' +
                    '          <input type="checkbox" class="version-check" data-version="' + v.version + '"' + (isChecked ? ' checked' : '') + ' style="margin-right:8px;cursor:pointer;">' +
                    '          <button class="layui-btn layui-btn-xs layui-btn-primary btn-view-version" data-version="' + v.version + '">View</button>' +
                    '        </div>' +
                    '      </div>' +
                    '      <div style="font-size:12px;color:#999;margin-bottom:6px;">' +
                    '        <span>' + RCUtil.formatDateTime(v.created_at || v.timestamp) + '</span>' +
                    '        <span style="margin-left:12px;">by ' + RCUtil.escapeHtml(v.author ? v.author.name : (v.user ? v.user.name : 'Unknown')) + '</span>' +
                    '      </div>' +
                    '      <div class="rc-version-changes">' + changeSummary + '</div>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';
            });

            html += '</div>';
            timelineDiv.innerHTML = html;

            // Bind checkbox handlers
            var checks = timelineDiv.querySelectorAll('.version-check');
            for (var c = 0; c < checks.length; c++) {
                checks[c].addEventListener('change', function() {
                    var ver = parseInt(this.getAttribute('data-version'), 10) || this.getAttribute('data-version');
                    if (this.checked) {
                        if (selectedVersions.length >= 2) {
                            this.checked = false;
                            layui.layer.msg('You can only select 2 versions to compare', { icon: 0 });
                            return;
                        }
                        selectedVersions.push(ver);
                    } else {
                        var idx = selectedVersions.indexOf(ver);
                        if (idx > -1) selectedVersions.splice(idx, 1);
                    }
                    updateCompareButton();
                });
            }

            // Bind view buttons
            var viewBtns = timelineDiv.querySelectorAll('.btn-view-version');
            for (var vb = 0; vb < viewBtns.length; vb++) {
                viewBtns[vb].addEventListener('click', function() {
                    var ver = this.getAttribute('data-version');
                    showVersionDetail(ver);
                });
            }
        }

        function updateCompareButton() {
            var btn = document.getElementById('btn-compare');
            btn.textContent = 'Compare Selected (' + selectedVersions.length + '/2)';
            btn.disabled = selectedVersions.length !== 2;
        }

        // Compare button handler
        document.getElementById('btn-compare').addEventListener('click', function() {
            if (selectedVersions.length !== 2) return;
            var v1 = selectedVersions[0];
            var v2 = selectedVersions[1];
            showDiffModal(v1, v2);
        });

        function showVersionDetail(version) {
            var loadIdx = layui.layer.load(1);
            RCApi.getVersion(listingId, version).then(function(envelope) {
                layui.layer.close(loadIdx);
                var data = envelope.data || envelope;
                var v = data.version || data;
                var content = '<table class="layui-table" style="margin:0;">';
                var fields = ['title', 'description', 'pickup_address', 'dropoff_address', 'rider_count', 'vehicle_type', 'baggage_notes', 'time_window_start', 'time_window_end', 'tags'];
                var labels = { title: 'Title', description: 'Description', pickup_address: 'Pickup', dropoff_address: 'Drop-off', rider_count: 'Riders', vehicle_type: 'Vehicle Type', baggage_notes: 'Baggage Notes', time_window_start: 'Time Start', time_window_end: 'Time End', tags: 'Tags' };
                fields.forEach(function(f) {
                    var val = v[f];
                    if (Array.isArray(val)) val = val.join(', ');
                    if (f === 'time_window_start' || f === 'time_window_end') val = RCUtil.formatDateTime(val);
                    content += '<tr><td style="width:140px;background:#fafafa;"><strong>' + labels[f] + '</strong></td><td>' + RCUtil.escapeHtml(String(val || '')) + '</td></tr>';
                });
                content += '</table>';
                layui.layer.open({
                    type: 1,
                    title: 'Version ' + version + ' Details',
                    area: ['600px', '500px'],
                    content: '<div style="padding:15px;">' + content + '</div>'
                });
            }).catch(function(err) {
                layui.layer.close(loadIdx);
                layui.layer.msg(err.message || 'Failed to load version', { icon: 2 });
            });
        }

        function showDiffModal(v1, v2) {
            var sorted = [v1, v2].sort(function(a, b) { return a - b; });
            var loadIdx = layui.layer.load(1);
            RCApi.diffVersions(listingId, sorted[0], sorted[1]).then(function(envelope) {
                layui.layer.close(loadIdx);
                var data = envelope.data || envelope;
                var diffs = data.diffs || data.changes || data || [];
                if (!Array.isArray(diffs)) {
                    // Convert object format to array
                    var arr = [];
                    for (var key in diffs) {
                        if (diffs.hasOwnProperty(key)) {
                            arr.push({ field: key, old_value: diffs[key].old, new_value: diffs[key].new });
                        }
                    }
                    diffs = arr;
                }

                if (diffs.length === 0) {
                    layui.layer.msg('No differences found between these versions', { icon: 0 });
                    return;
                }

                var content =
                    '<div style="padding:15px;">' +
                    '<p style="margin-bottom:15px;color:#666;">Comparing <strong>Version ' + sorted[0] + '</strong> with <strong>Version ' + sorted[1] + '</strong></p>' +
                    '<table class="layui-table" style="margin:0;">' +
                    '<thead><tr><th style="width:120px;">Field</th><th>Version ' + sorted[0] + '</th><th>Version ' + sorted[1] + '</th></tr></thead>' +
                    '<tbody>';

                diffs.forEach(function(d) {
                    var oldVal = d.old_value;
                    var newVal = d.new_value;
                    if (Array.isArray(oldVal)) oldVal = oldVal.join(', ');
                    if (Array.isArray(newVal)) newVal = newVal.join(', ');
                    var fieldLabel = (d.field || d.name || '').replace(/_/g, ' ');
                    fieldLabel = fieldLabel.charAt(0).toUpperCase() + fieldLabel.slice(1);

                    content += '<tr>' +
                        '<td><strong>' + RCUtil.escapeHtml(fieldLabel) + '</strong></td>' +
                        '<td style="background:#ffeaea;"><span style="color:#d9534f;text-decoration:line-through;">' + RCUtil.escapeHtml(String(oldVal || '(empty)')) + '</span></td>' +
                        '<td style="background:#eaffea;"><span style="color:#5cb85c;">' + RCUtil.escapeHtml(String(newVal || '(empty)')) + '</span></td>' +
                        '</tr>';
                });

                content += '</tbody></table></div>';

                layui.layer.open({
                    type: 1,
                    title: 'Version Diff: v' + sorted[0] + ' vs v' + sorted[1],
                    area: ['700px', '500px'],
                    content: content
                });
            }).catch(function(err) {
                layui.layer.close(loadIdx);
                layui.layer.msg(err.message || 'Failed to load diff', { icon: 2 });
            });
        }
    }
};
