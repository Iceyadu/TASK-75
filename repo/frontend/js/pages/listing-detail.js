var ListingDetailPage = {
    render: function(container, params) {
        var listingId = params && params.id ? params.id : null;
        if (!listingId) {
            container.innerHTML = RCUtil.emptyState('Listing not found.', 'Browse Listings', '#/listings');
            return;
        }

        container.innerHTML =
            '<div class="rc-page-header">' +
            '  <a href="#/listings" style="color:#009688;text-decoration:none;font-size:13px;"><i class="layui-icon layui-icon-left"></i> Back to Listings</a>' +
            '</div>' +
            '<div id="listing-detail-content">' + RCUtil.skeleton(3) + '</div>';

        RCApi.getListing(listingId).then(function(envelope) {
            var l = envelope.data;
            renderDetail(l);
        }).catch(function(err) {
            document.getElementById('listing-detail-content').innerHTML =
                '<div class="rc-empty-state rc-error-state" style="text-align:center;padding:40px;color:#FF5722;">' +
                '<i class="layui-icon layui-icon-face-cry" style="font-size:48px;display:block;margin-bottom:12px;"></i>' +
                '<p>' + RCUtil.escapeHtml(err.message || 'Failed to load listing') + '</p>' +
                '<a href="#/listings" class="layui-btn layui-btn-sm">Browse Listings</a></div>';
        });

        function renderDetail(l) {
            var user = RCAuth.getUser();
            var isOwner = user && l.user && (user.id === l.user.id);
            var isMod = RCAuth.isModerator();
            var isAdmin = RCAuth.isAdmin();
            var vehicleLabel = (l.vehicle_type || '').charAt(0).toUpperCase() + (l.vehicle_type || '').slice(1);
            var tags = (l.tags || []).map(function(t) {
                return '<span class="rc-tag-chip" style="display:inline-block;padding:3px 10px;margin:3px;background:#e8f5e9;color:#388e3c;border-radius:12px;font-size:12px;">' +
                    RCUtil.escapeHtml(t) + '</span>';
            }).join('');

            var actionsHtml = buildActionPanel(l, isOwner, isMod, isAdmin, user);

            var orderHtml = '';
            if (l.order && (l.status === 'matched' || l.status === 'in_progress')) {
                orderHtml =
                    '<div class="layui-card" style="margin-top:20px;">' +
                    '  <div class="layui-card-header"><i class="layui-icon layui-icon-form"></i> Associated Order</div>' +
                    '  <div class="layui-card-body">' +
                    '    <table class="layui-table" style="margin:0;">' +
                    '      <tr><td style="width:120px;"><strong>Order ID</strong></td><td><a href="#/orders/' + l.order.id + '" style="color:#009688;">#' + l.order.id + '</a></td></tr>' +
                    '      <tr><td><strong>Driver</strong></td><td>' + RCUtil.escapeHtml(l.order.driver ? l.order.driver.name : 'N/A') + '</td></tr>' +
                    '      <tr><td><strong>Status</strong></td><td>' + RCUtil.statusBadge(l.order.status) + '</td></tr>' +
                    '      <tr><td><strong>Created</strong></td><td>' + RCUtil.formatDateTime(l.order.created_at) + '</td></tr>' +
                    '    </table>' +
                    '  </div>' +
                    '</div>';
            }

            var reviewsHtml = '';
            if (l.status === 'completed' && l.reviews && l.reviews.length > 0) {
                reviewsHtml =
                    '<div class="layui-card" style="margin-top:20px;">' +
                    '  <div class="layui-card-header"><i class="layui-icon layui-icon-star"></i> Reviews</div>' +
                    '  <div class="layui-card-body">' +
                    l.reviews.map(function(r) {
                        return '<div style="border-bottom:1px solid #f0f0f0;padding:12px 0;">' +
                            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">' +
                            '  <strong>' + RCUtil.escapeHtml(r.reviewer ? r.reviewer.name : 'Anonymous') + '</strong>' +
                            '  <span>' + RCUtil.starsHtml(r.rating, 5) + '</span>' +
                            '</div>' +
                            '<p style="color:#555;margin:0;">' + RCUtil.escapeHtml(r.comment || '') + '</p>' +
                            '<span class="layui-word-aux" style="font-size:11px;">' + RCUtil.formatDate(r.created_at) + '</span>' +
                            '</div>';
                    }).join('') +
                    '  </div>' +
                    '</div>';
            } else if (l.status === 'completed') {
                reviewsHtml =
                    '<div class="layui-card" style="margin-top:20px;">' +
                    '  <div class="layui-card-header"><i class="layui-icon layui-icon-star"></i> Reviews</div>' +
                    '  <div class="layui-card-body"><p style="color:#999;text-align:center;">No reviews yet.</p></div>' +
                    '</div>';
            }

            document.getElementById('listing-detail-content').innerHTML =
                '<div class="layui-row layui-col-space20">' +
                '  <div class="layui-col-md8">' +
                '    <div class="layui-card">' +
                '      <div class="layui-card-header" style="display:flex;justify-content:space-between;align-items:center;font-size:18px;">' +
                '        <span>' + RCUtil.escapeHtml(l.title) + '</span>' +
                '        ' + RCUtil.statusBadge(l.status) +
                '      </div>' +
                '      <div class="layui-card-body">' +
                '        <div style="margin-bottom:10px;color:#999;font-size:13px;">' +
                '          Posted by <strong>' + RCUtil.escapeHtml(l.user ? l.user.name : 'Unknown') + '</strong> on ' + RCUtil.formatDateTime(l.created_at) +
                '          &middot; <a href="#/listings/' + l.id + '/versions" style="color:#009688;">Version History</a>' +
                '        </div>' +
                '        <table class="layui-table" style="margin:0;">' +
                '          <tr><td style="width:140px;background:#fafafa;"><strong>Pickup Address</strong></td><td>' + RCUtil.escapeHtml(l.pickup_address || 'N/A') + '</td></tr>' +
                '          <tr><td style="background:#fafafa;"><strong>Drop-off Address</strong></td><td>' + RCUtil.escapeHtml(l.dropoff_address || 'N/A') + '</td></tr>' +
                '          <tr><td style="background:#fafafa;"><strong>Rider Count</strong></td><td>' + (l.rider_count || 0) + '</td></tr>' +
                '          <tr><td style="background:#fafafa;"><strong>Vehicle Type</strong></td><td>' + RCUtil.escapeHtml(vehicleLabel) + '</td></tr>' +
                '          <tr><td style="background:#fafafa;"><strong>Baggage Notes</strong></td><td>' + RCUtil.escapeHtml(l.baggage_notes || 'None') + '</td></tr>' +
                '          <tr><td style="background:#fafafa;"><strong>Time Window</strong></td><td>' + RCUtil.formatTime12h(l.time_window_start) + ' - ' + RCUtil.formatTime12h(l.time_window_end) + '</td></tr>' +
                '          <tr><td style="background:#fafafa;"><strong>Tags</strong></td><td>' + (tags || '<span style="color:#999;">None</span>') + '</td></tr>' +
                '        </table>' +
                '        <div style="margin-top:16px;">' +
                '          <h4 style="margin-bottom:8px;">Description</h4>' +
                '          <div style="line-height:1.8;color:#555;white-space:pre-wrap;">' + RCUtil.escapeHtml(l.description || 'No description provided.') + '</div>' +
                '        </div>' +
                '        <div style="margin-top:12px;color:#999;font-size:12px;">' +
                '          ' + (l.view_count || 0) + ' views &middot; ' + (l.favorite_count || 0) + ' favorites' +
                '        </div>' +
                '      </div>' +
                '    </div>' +
                orderHtml +
                reviewsHtml +
                '  </div>' +
                '  <div class="layui-col-md4">' +
                actionsHtml +
                '  </div>' +
                '</div>';

            bindActions(l, isOwner, isMod, isAdmin, user);
        }

        function buildActionPanel(l, isOwner, isMod, isAdmin, user) {
            var html = '<div class="layui-card"><div class="layui-card-header">Actions</div><div class="layui-card-body" style="text-align:center;">';

            // Accept button for drivers (not owner, listing is active)
            if (!isOwner && l.status === 'active' && user) {
                if (user.blocked) {
                    html += RCUtil.blockedMessage('Your account is blocked. You cannot accept rides.');
                } else {
                    html += '<button class="layui-btn layui-btn-lg layui-btn-fluid" id="btn-accept" style="margin-bottom:10px;"><i class="layui-icon layui-icon-ok"></i> Accept This Ride</button>';
                }
            }

            if (!user && l.status === 'active') {
                html += '<p style="color:#999;margin-bottom:10px;">Please <a href="#/login" style="color:#009688;">log in</a> to accept this ride.</p>';
            }

            // Owner actions
            if (isOwner) {
                if (l.status === 'draft') {
                    html += '<button class="layui-btn layui-btn-fluid" id="btn-edit" style="margin-bottom:8px;"><i class="layui-icon layui-icon-edit"></i> Edit Listing</button>';
                    html += '<button class="layui-btn layui-btn-normal layui-btn-fluid" id="btn-publish" style="margin-bottom:8px;"><i class="layui-icon layui-icon-release"></i> Publish</button>';
                    html += '<button class="layui-btn layui-btn-danger layui-btn-fluid" id="btn-delete" style="margin-bottom:8px;"><i class="layui-icon layui-icon-delete"></i> Delete</button>';
                } else if (l.status === 'active') {
                    html += '<button class="layui-btn layui-btn-fluid" id="btn-edit" style="margin-bottom:8px;"><i class="layui-icon layui-icon-edit"></i> Edit Listing</button>';
                    html += '<button class="layui-btn layui-btn-warm layui-btn-fluid" id="btn-unpublish" style="margin-bottom:8px;"><i class="layui-icon layui-icon-pause"></i> Unpublish</button>';
                } else if (l.status === 'matched' || l.status === 'in_progress') {
                    html += '<p style="color:#999;font-size:13px;">This listing is currently ' + RCUtil.escapeHtml(l.status.replace('_', ' ')) + '. Editing is disabled.</p>';
                }
            }

            // Moderator actions
            if (isMod && !isOwner) {
                html += '<hr style="margin:12px 0;">';
                html += '<p style="font-size:12px;color:#999;margin-bottom:8px;">Moderator Actions</p>';
                if (l.status === 'active') {
                    html += '<button class="layui-btn layui-btn-warm layui-btn-sm layui-btn-fluid" id="btn-mod-unpublish" style="margin-bottom:8px;">Unpublish (Mod)</button>';
                }
                html += '<button class="layui-btn layui-btn-danger layui-btn-sm layui-btn-fluid" id="btn-flag" style="margin-bottom:8px;">Flag for Review</button>';
            }

            html += '</div></div>';

            // Listing info card
            html += '<div class="layui-card" style="margin-top:15px;"><div class="layui-card-header">Poster Info</div><div class="layui-card-body">';
            if (l.user) {
                html += '<div style="text-align:center;">' +
                    '<div style="width:60px;height:60px;border-radius:50%;background:#e0e0e0;margin:0 auto 10px;line-height:60px;font-size:24px;color:#999;">' +
                    RCUtil.escapeHtml((l.user.name || '?').charAt(0).toUpperCase()) + '</div>' +
                    '<strong>' + RCUtil.escapeHtml(l.user.name) + '</strong>' +
                    (l.user.rating ? '<div style="margin-top:4px;">' + RCUtil.starsHtml(l.user.rating, 5) + '</div>' : '') +
                    '</div>';
            }
            html += '</div></div>';

            return html;
        }

        function bindActions(l, isOwner, isMod, isAdmin, user) {
            var btnAccept = document.getElementById('btn-accept');
            if (btnAccept) {
                btnAccept.addEventListener('click', function() {
                    layui.layer.confirm(
                        'Are you sure you want to accept this ride? This will create an order and match you with the rider.',
                        { title: 'Confirm Accept', btn: ['Yes, Accept', 'Cancel'] },
                        function(idx) {
                            layui.layer.close(idx);
                            btnAccept.disabled = true;
                            btnAccept.innerHTML = '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Processing...';
                            RCApi.acceptListing(listingId).then(function(envelope) {
                                layui.layer.msg('Ride accepted! Redirecting to your order...', { icon: 1 });
                                var orderId = envelope.data ? envelope.data.id : '';
                                setTimeout(function() {
                                    RCRouter.navigate('#/orders/' + orderId);
                                }, 1200);
                            }).catch(function(err) {
                                btnAccept.disabled = false;
                                btnAccept.innerHTML = '<i class="layui-icon layui-icon-ok"></i> Accept This Ride';
                                layui.layer.msg(err.message || 'Failed to accept ride', { icon: 2 });
                            });
                        }
                    );
                });
            }

            var btnEdit = document.getElementById('btn-edit');
            if (btnEdit) {
                btnEdit.addEventListener('click', function() {
                    RCRouter.navigate('#/listings/' + l.id + '/edit');
                });
            }

            var btnPublish = document.getElementById('btn-publish');
            if (btnPublish) {
                btnPublish.addEventListener('click', function() {
                    btnPublish.disabled = true;
                    RCApi.publishListing(l.id).then(function() {
                        layui.layer.msg('Listing published!', { icon: 1 });
                        RCApi.getListing(listingId).then(function(envelope) {
                            renderDetail(envelope.data);
                        });
                    }).catch(function(err) {
                        btnPublish.disabled = false;
                        layui.layer.msg(err.message || 'Failed to publish', { icon: 2 });
                    });
                });
            }

            var btnUnpublish = document.getElementById('btn-unpublish');
            if (btnUnpublish) {
                btnUnpublish.addEventListener('click', function() {
                    layui.layer.confirm('Are you sure you want to unpublish this listing?', { title: 'Unpublish', btn: ['Yes', 'Cancel'] }, function(idx) {
                        layui.layer.close(idx);
                        RCApi.unpublishListing(l.id).then(function() {
                            layui.layer.msg('Listing unpublished.', { icon: 1 });
                            RCApi.getListing(listingId).then(function(env) {
                                renderDetail(env.data);
                            });
                        }).catch(function(err) {
                            layui.layer.msg(err.message || 'Failed to unpublish', { icon: 2 });
                        });
                    });
                });
            }

            var btnDelete = document.getElementById('btn-delete');
            if (btnDelete) {
                btnDelete.addEventListener('click', function() {
                    layui.layer.confirm('Are you sure you want to delete this draft listing? This cannot be undone.', {
                        title: 'Delete Listing', btn: ['Delete', 'Cancel'], btn2: function() {}
                    }, function(idx) {
                        layui.layer.close(idx);
                        RCApi.deleteListing(l.id).then(function() {
                            layui.layer.msg('Listing deleted.', { icon: 1 });
                            setTimeout(function() { RCRouter.navigate('#/my-listings'); }, 800);
                        }).catch(function(err) {
                            layui.layer.msg(err.message || 'Failed to delete', { icon: 2 });
                        });
                    });
                });
            }

            var btnModUnpublish = document.getElementById('btn-mod-unpublish');
            if (btnModUnpublish) {
                btnModUnpublish.addEventListener('click', function() {
                    layui.layer.prompt({ title: 'Reason for unpublishing', formType: 2 }, function(reason, promptIdx) {
                        layui.layer.close(promptIdx);
                        RCApi.unpublishListing(l.id, { reason: reason }).then(function() {
                            layui.layer.msg('Listing unpublished by moderator.', { icon: 1 });
                            RCApi.getListing(listingId).then(function(env) {
                                renderDetail(env.data);
                            });
                        }).catch(function(err) {
                            layui.layer.msg(err.message || 'Failed to unpublish', { icon: 2 });
                        });
                    });
                });
            }

            var btnFlag = document.getElementById('btn-flag');
            if (btnFlag) {
                btnFlag.addEventListener('click', function() {
                    layui.layer.prompt({ title: 'Reason for flagging', formType: 2 }, function(reason, promptIdx) {
                        layui.layer.close(promptIdx);
                        RCApi.flagListing(l.id, { reason: reason }).then(function() {
                            layui.layer.msg('Listing flagged for review.', { icon: 1 });
                        }).catch(function(err) {
                            layui.layer.msg(err.message || 'Failed to flag', { icon: 2 });
                        });
                    });
                });
            }
        }
    }
};
