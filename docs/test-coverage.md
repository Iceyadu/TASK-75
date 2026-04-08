# RideCircle Carpool Marketplace -- Test Coverage Strategy

## Philosophy

Risk-first testing: prioritize tests for business logic that is hardest to verify by static review, has the highest blast radius if broken, or involves complex state transitions.

## Risk Tiers

### Tier 1 -- Critical (Must have before any deployment)

These areas have strict business rules where a bug means data corruption, security breach, or broken core workflow.

| Area | Risk | Test Type | What to Test |
|------|------|-----------|--------------|
| Order state machine | Invalid transitions corrupt data; financial/trust implications | Unit | Every valid transition succeeds; every invalid transition is rejected; auto-expire at exactly 30 min; free cancel window at exactly 5 min; in-progress cancel blocked; dispute window at exactly 72 hours |
| Authentication | Broken auth = security breach | Unit + API | Session creation/validation/expiry; token creation/hashing/rotation/expiry; invalid credentials rejected; expired tokens rejected |
| Authorization (RBAC + object-level) | Broken authz = data leak or privilege escalation | API | Each role can only access permitted endpoints; object ownership enforced (user A cannot edit user B's listing); admin-only endpoints reject non-admins |
| Password handling | Plaintext storage = catastrophic | Unit | Passwords are hashed (bcrypt/argon2); plaintext never stored or logged; hash comparison works |
| Signed URL validation | Broken = unauthorized media access | Unit + API | Valid signature accepted; expired signature rejected; tampered signature rejected; missing params rejected |

### Tier 2 -- High (Must have before feature complete)

Business logic that is complex but whose failures are more visible and less catastrophic.

| Area | Risk | Test Type | What to Test |
|------|------|-----------|--------------|
| Listing workflow | Wrong status transitions; draft/publish confusion | Unit | All valid transitions; invalid transitions rejected; bulk close only affects eligible listings; version created on each edit |
| Review validation | Bad data persisted; rate limit bypass | Unit + API | Rating 1-5 enforced; text length 1-1000; file count max 5; file size limits; rate limit (3/hour) enforced and returns 429 |
| Content moderation flags | Offensive content published; false positives | Unit | Sensitive word detection (case-insensitive, word-boundary); duplicate text detection at threshold; file fingerprint match; credibility score computation |
| Media upload | Oversized files; wrong types accepted | API | File type validation; file size rejection; correct MIME type check; SHA-256 hash computed |
| Hotlink protection | Media accessible without auth | API | Missing referrer + no signature = 403; wrong referrer = 403; valid signed URL + valid referrer = 200 |

### Tier 3 -- Medium (Should have before release)

Features that are important but whose failure modes are less severe.

| Area | Risk | Test Type | What to Test |
|------|------|-----------|--------------|
| Search and suggestions | Poor UX but not data loss | API | Keyword search returns matching results; highlights present; suggestions returned for partial input; did-you-mean for known typos; no-results fallback shows recent listings |
| Credibility score | Wrong weighting affects ratings display | Unit | Score computation with various inputs; age factor < 14 days = 0.5; completion factor correct; pattern penalties applied; score < 0.3 flags review |
| Data governance nightly jobs | Stale data; metric drift | Unit | Dedup removes duplicate events in window; missing-value fill corrects counters; lineage records created; quality metrics computed correctly |
| Pagination | Missing data on pages | API | Page 1 returns correct count; last page correct; out-of-range page returns empty data (not error) |
| Audit logging | Missing audit trail | Integration | CRUD operations on listings/orders/reviews create audit log entries; PII is masked by default; unmasked only with permission |

### Tier 4 -- Low (Nice to have)

| Area | Risk | Test Type | What to Test |
|------|------|-----------|--------------|
| Watermark application | Visual quality | Manual | Watermark visible but not obstructive; correct position; transparency correct |
| 12-hour time format | Display issue | Unit | AM/PM parsing and display correct; midnight and noon edge cases |
| Version diff | Display issue | Unit | Diff correctly identifies changed fields; unchanged fields not shown |

## Test Structure

```
repo/
  unit_tests/
    auth/
      LoginTest.php
      TokenTest.php
      PasswordHashTest.php
    listing/
      ListingWorkflowTest.php
      ListingVersionTest.php
      ListingValidationTest.php
    order/
      OrderStateMachineTest.php
      OrderExpiryTest.php
      OrderCancelTest.php
      OrderDisputeTest.php
    review/
      ReviewValidationTest.php
      ReviewRateLimitTest.php
    moderation/
      SensitiveWordTest.php
      DuplicateDetectionTest.php
      CredibilityScoreTest.php
    media/
      SignedUrlTest.php
      FileValidationTest.php
      FingerprintTest.php
    governance/
      DedupJobTest.php
      QualityMetricsTest.php
      LineageTest.php
  API_tests/
    AuthApiTest.php
    ListingApiTest.php
    OrderApiTest.php
    ReviewApiTest.php
    MediaApiTest.php
    ModerationApiTest.php
    GovernanceApiTest.php
    AuditApiTest.php
    PermissionApiTest.php
```

## Coverage Targets

| Tier | Target | Rationale |
|------|--------|-----------|
| Tier 1 | 100% of stated rules | These are non-negotiable business constraints |
| Tier 2 | >90% of happy + error paths | Complex logic with meaningful failure modes |
| Tier 3 | >70% happy paths | Important but failure is more visible/less catastrophic |
| Tier 4 | Manual verification | Cost of automation exceeds risk |

## Running Tests

```bash
# All tests
./run_tests.sh

# Unit tests only
./run_tests.sh unit

# API tests only (requires running backend + MySQL)
./run_tests.sh api
```

## Implemented Tests

The following test files have been created with real assertion logic. All tests are mock-based unit tests that verify service logic, computation, validation, and state transitions without a database connection.

### Tier 1 -- Critical

| Test File | Risk Area | Test Methods |
|-----------|-----------|-------------|
| `unit_tests/auth/LoginTest.php` | Authentication | test_login_with_valid_credentials_returns_user_and_session, test_login_with_invalid_password_throws_auth_exception, test_login_with_nonexistent_email_throws_auth_exception, test_login_with_disabled_account_throws_auth_exception, test_register_with_valid_data_creates_user, test_register_with_duplicate_email_throws_exception, test_register_with_invalid_org_code_throws_exception, test_password_is_hashed_not_stored_plaintext |
| `unit_tests/auth/TokenTest.php` | Token security | test_create_token_returns_plaintext_once, test_token_stored_as_sha256_hash, test_revoke_sets_revoked_at, test_rotate_invalidates_old_creates_new, test_expired_token_is_not_valid, test_revoked_token_is_not_valid, test_token_default_expiry_is_90_days |
| `unit_tests/auth/PasswordHashTest.php` | Password handling | test_password_is_hashed_with_bcrypt, test_password_verify_matches_hash, test_different_passwords_produce_different_hashes, test_plaintext_password_never_equals_hash |
| `unit_tests/auth/AuthMiddlewareTest.php` | Auth middleware | test_bearer_token_authenticates_user, test_expired_bearer_token_returns_401, test_revoked_bearer_token_returns_401, test_session_authenticates_user, test_no_credentials_returns_401, test_disabled_user_returns_401 |
| `unit_tests/auth/RbacTest.php` | Authorization | test_admin_has_all_permissions, test_moderator_has_moderation_permissions, test_regular_user_lacks_admin_permissions, test_user_with_multiple_roles_has_union_of_permissions |
| `unit_tests/auth/OrgIsolationTest.php` | Org isolation | test_user_cannot_access_other_org_listing, test_user_cannot_access_other_org_order, test_query_scopes_by_org_id |
| `unit_tests/order/OrderStateMachineTest.php` | **Order state machine** | test_create_order_sets_pending_match, test_create_order_sets_30_minute_expiry, test_driver_cannot_accept_own_listing, test_accept_transitions_pending_to_accepted, test_start_transitions_accepted_to_in_progress, test_complete_transitions_in_progress_to_completed, test_cannot_skip_states, test_only_passenger_can_accept_match, test_only_party_can_start_trip, test_only_party_can_complete_trip |
| `unit_tests/order/OrderExpiryTest.php` | Auto-expire | test_expire_command_expires_orders_older_than_30_minutes, test_expire_command_ignores_non_pending_orders, test_expire_restores_listing_to_active, test_expire_is_idempotent |
| `unit_tests/order/OrderCancelTest.php` | **Cancellation rules** | test_free_cancel_within_5_minutes_of_acceptance, test_reason_required_after_5_minutes, test_cancel_blocked_when_in_progress (code 40901), test_cancel_reason_OTHER_requires_text, test_cancel_restores_listing_to_active, test_cancel_pending_match_is_always_free |
| `unit_tests/order/OrderDisputeTest.php` | Dispute window | test_dispute_within_72_hours_succeeds, test_dispute_after_72_hours_throws_exception, test_dispute_only_on_completed_orders, test_only_admin_can_resolve_dispute, test_resolve_sets_outcome_and_notes |
| `unit_tests/media/SignedUrlTest.php` | Signed URLs | test_valid_signature_accepted, test_expired_signature_rejected, test_tampered_signature_rejected, test_missing_signature_rejected, test_missing_expires_rejected |

### Tier 2 -- High

| Test File | Risk Area | Test Methods |
|-----------|-----------|-------------|
| `unit_tests/listing/ListingWorkflowTest.php` | Listing workflow | test_create_listing_starts_as_draft, test_publish_transitions_draft_to_active, test_publish_requires_all_fields, test_unpublish_transitions_active_to_draft, test_cannot_edit_in_progress_listing, test_only_owner_can_edit, test_only_draft_can_be_deleted, test_bulk_close_only_affects_active_listings, test_bulk_close_skips_matched_listings |
| `unit_tests/listing/ListingVersionTest.php` | Version tracking | test_create_listing_creates_version_1, test_update_listing_increments_version, test_version_snapshot_contains_all_fields, test_diff_identifies_changed_fields |
| `unit_tests/review/ReviewRateLimitTest.php` | Rate limiting | test_first_3_reviews_in_hour_succeed, test_4th_review_in_hour_throws_rate_limit_exception, test_review_after_window_resets_succeeds |
| `unit_tests/review/DuplicateDetectionTest.php` | Duplicate detection | test_trigram_jaccard_identical_strings_returns_1, test_trigram_jaccard_different_strings_returns_low, test_trigram_jaccard_similar_strings_above_threshold, test_file_hash_duplicate_detected, test_file_hash_different_files_not_flagged |
| `unit_tests/review/CredibilityScoreTest.php` | Credibility score | test_new_account_under_14_days_gets_half_age_factor, test_old_account_over_14_days_gets_full_age_factor, test_completion_factor_is_ratio_of_completed_to_total, test_no_orders_gives_zero_completion_factor, test_burst_five_star_reviews_reduce_pattern_factor, test_review_within_5_min_of_completion_penalized, test_score_clamped_between_0_and_1, test_weights_sum_correctly |
| `unit_tests/media/FileValidationTest.php` | File validation | test_photo_under_5mb_accepted, test_photo_over_5mb_rejected, test_video_under_50mb_accepted, test_video_over_50mb_rejected, test_max_5_files_per_review, test_invalid_mime_type_rejected |
| `unit_tests/media/FingerprintTest.php` | File fingerprint | test_sha256_hash_computed_on_upload, test_duplicate_hash_detected_within_30_days, test_same_hash_different_user_not_flagged |
| `unit_tests/moderation/SensitiveWordTest.php` | Content moderation | test_exact_word_match_detected, test_case_insensitive_match, test_word_boundary_respected, test_clean_text_passes, test_multiple_matches_returned |

### Tier 3 -- Medium

| Test File | Risk Area | Test Methods |
|-----------|-----------|-------------|
| `unit_tests/listing/SearchTest.php` | Search | test_keyword_search_matches_title, test_keyword_search_matches_description, test_keyword_search_matches_tags, test_filter_by_vehicle_type, test_filter_by_rider_count_range, test_sort_by_newest, test_sort_by_most_popular, test_highlight_wraps_matched_terms, test_no_results_returns_recent_active, test_did_you_mean_for_typo |
| `unit_tests/governance/DedupJobTest.php` | Data governance | test_duplicate_events_in_1_minute_window_removed, test_events_outside_window_kept, test_lineage_record_created |
| `unit_tests/governance/QualityMetricsTest.php` | Quality metrics | test_listing_completeness_calculation, test_stale_listing_rate_calculation, test_moderation_queue_depth |
| `unit_tests/governance/LineageTest.php` | Data lineage | test_lineage_record_has_correct_structure, test_lineage_run_id_is_unique |
| `unit_tests/audit/AuditMaskingTest.php` | PII masking | test_masked_output_hides_user_id, test_masked_output_hides_ip_address, test_unmasked_output_shows_full_data, test_password_hash_never_in_audit_log |

### Total: 22 backend unit test files, 103 test methods

## Frontend Tests

Browser-based test files in `repo/unit_tests/frontend/`. Each is a self-contained HTML file that loads `test-harness.js` and runs assertions, displaying green/red results in the page.

| Test File | Screen/Feature | Test Methods | Requirement Verified |
|-----------|---------------|-------------|---------------------|
| `test-navigation.html` | Global navigation | test_user_sees_basic_nav_items, test_user_does_not_see_moderation_link, test_user_does_not_see_admin_links, test_moderator_sees_moderation_link, test_moderator_does_not_see_admin_links, test_admin_sees_all_nav_items, test_unauthenticated_redirects_to_login, test_logout_clears_user_state | Role-based navigation (screen-spec: Navigation Bar); RBAC UI enforcement |
| `test-listing-form.html` | Listing Create/Edit | test_title_required, test_title_min_5_chars, test_title_max_200_chars, test_pickup_address_required, test_dropoff_address_required, test_rider_count_min_1_max_6, test_vehicle_type_required, test_time_window_end_after_start, test_description_max_2000_chars, test_baggage_notes_max_500_chars, test_tags_max_10, test_draft_save_does_not_require_publish_fields, test_publish_requires_all_fields | Client-side form validation (screen-spec: Screen 6 Listing Create) |
| `test-listing-workflow.html` | Listing draft/publish | test_new_listing_starts_as_draft, test_publish_button_sends_api_call, test_unpublish_button_sends_api_call, test_edit_shows_change_preview, test_version_history_displays_timeline, test_diff_view_shows_old_and_new | Listing workflow (design.md Section 8); version history UI (screen-spec: Screen 7, 8) |
| `test-search.html` | Search/filter/sort | test_search_input_triggers_suggestions, test_search_submit_calls_api, test_highlight_renders_em_tags_with_yellow_background, test_did_you_mean_shown_on_typo, test_did_you_mean_click_triggers_new_search, test_no_results_shows_recently_active, test_filter_vehicle_type_sent_to_api, test_filter_rider_count_range_sent_to_api, test_sort_newest_default, test_sort_most_popular_changes_api_param, test_search_history_stored_in_localStorage, test_search_history_max_10_items, test_search_history_click_triggers_search | Search UX (screen-spec: Screen 4 Listing Browse); local search history |
| `test-order-lifecycle.html` | Order detail UI | test_pending_match_shows_accept_button_for_passenger, test_accepted_shows_start_button_for_party, test_in_progress_shows_complete_button, test_in_progress_cancel_disabled_with_message, test_completed_shows_dispute_button_within_72h, test_completed_hides_dispute_after_72h, test_canceled_shows_no_actions_message, test_expired_shows_expiry_message, test_lifecycle_bar_highlights_current_step, test_cancel_free_within_5_min_no_reason, test_cancel_after_5_min_requires_reason, test_dispute_requires_reason_text, test_resolve_only_shown_for_admin | Order lifecycle UI (screen-spec: Screen 11); blocked-action messages; 5-min cancel window; 72h dispute window; admin-only resolve |
| `test-review-form.html` | Review create | test_rating_required, test_rating_1_to_5, test_text_required, test_text_max_1000_chars, test_char_counter_updates, test_char_counter_red_near_limit, test_file_count_max_5, test_photo_max_5mb_warning, test_video_max_50mb_warning, test_accepted_file_types, test_preview_shown_for_images, test_submit_disabled_without_rating_and_text, test_rate_limit_error_displayed, test_duplicate_detection_message | Review validation (screen-spec: Screen 12); file limits; rate limit UI feedback |
| `test-moderation.html` | Moderation queue | test_forbidden_for_regular_user, test_queue_renders_flagged_items, test_sensitive_word_flag_shown_in_red, test_duplicate_flag_shown_in_orange, test_credibility_score_colored, test_approve_sends_api_call, test_reject_requires_reason, test_escalate_sends_api_call, test_empty_queue_message | Moderation queue UI (screen-spec: Screen 15, 16); flag color coding; credibility score display |
| `test-governance.html` | Governance dashboard | test_forbidden_for_non_admin, test_metrics_cards_render, test_empty_state_before_first_run, test_lineage_expandable_rows, test_audit_pii_toggle, test_audit_masked_by_default, test_audit_unmask_requires_permission | Governance dashboard (screen-spec: Screen 19, 20, 21); PII masking; admin-only access |

### Frontend Test Totals: 8 test files, 78 test methods

## API Integration Tests

PHPUnit integration tests in `repo/API_tests/`. Require a running backend + MySQL with seeded test data.

| Test File | Endpoints Tested | Test Methods | Requirement Verified |
|-----------|-----------------|-------------|---------------------|
| `AuthApiTest.php` | /api/auth/* | test_register_success, test_register_duplicate_email, test_register_invalid_org_code, test_login_success, test_login_invalid_credentials, test_logout_clears_session, test_me_returns_user_with_roles, test_me_requires_auth | Authentication (Tier 1); session management; org code validation |
| `ListingApiTest.php` | /api/listings/* | test_create_listing_as_draft, test_create_listing_and_publish, test_edit_listing_creates_version, test_publish_draft_listing, test_unpublish_active_listing, test_bulk_close_active_listings, test_bulk_close_fails_for_matched_listings, test_search_by_keyword, test_search_filter_vehicle_type, test_search_filter_rider_count, test_search_sort_newest, test_search_sort_most_popular, test_search_no_results_returns_recent_active | Listing workflow (Tier 2); search and filter (Tier 3); bulk operations |
| `OrderApiTest.php` | /api/orders/* | test_create_order_as_driver, test_accept_order_as_passenger, test_start_trip, test_complete_trip, test_cancel_within_5_minutes_free, test_cancel_after_5_minutes_requires_reason, test_cancel_blocked_in_progress (409 + code 40901), test_dispute_within_72_hours, test_dispute_after_72_hours_rejected, test_resolve_requires_admin, test_resolve_non_admin_rejected | **Order state machine (Tier 1, MOST CRITICAL)**; cancellation rules; dispute window; admin-only resolve |
| `ReviewApiTest.php` | /api/reviews | test_create_review, test_rate_limit_3_per_hour, test_duplicate_review_flagged, test_file_count_max_5, test_file_size_photo_max_5mb, test_file_size_video_max_50mb | Review validation (Tier 2); rate limiting; file limits |
| `MediaApiTest.php` | /api/media/* | test_signed_url_valid, test_signed_url_expired, test_signed_url_tampered, test_hotlink_wrong_referrer | Signed URL security (Tier 1); hotlink protection (Tier 2) |
| `PermissionApiTest.php` | Multiple protected endpoints | test_regular_user_cannot_access_moderation, test_regular_user_cannot_access_governance, test_regular_user_cannot_access_audit, test_regular_user_cannot_access_admin_users, test_moderator_can_access_moderation, test_admin_can_access_all | RBAC enforcement (Tier 1); endpoint-level authorization |

### API Integration Test Totals: 6 test files, 41 test methods

## Key Test Principles

1. **Order state machine tests are the single most important test suite.** Every boundary condition (exactly 30 min, exactly 5 min, exactly 72 hours) must be tested with time mocking.
2. **Authorization tests must cover negative cases.** It is not enough to verify that an admin can access admin endpoints; tests must also verify that a regular user receives 403.
3. **Rate limit tests must use time mocking** to avoid flaky tests that depend on wall-clock timing.
4. **Media tests must use real files** (small fixtures) to verify MIME type detection and hash computation, not mocked file objects.
5. **No external dependencies in tests.** Tests must run fully offline, matching the production constraint.
