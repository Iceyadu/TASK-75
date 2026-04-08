# RideCircle Carpool Marketplace -- Reviewer Notes

## Purpose

This document guides a static reviewer to the most important evidence in the repository. It identifies where to look, what to verify, and which risks need the closest attention. All file paths are relative to `TASK-ridecircle/`.

---

## 1. Where to Start (Priority Order)

| # | What to Review | Where to Find It |
|---|---------------|-------------------|
| 1 | Order state machine (MOST CRITICAL) | `repo/backend/app/service/OrderService.php` -- 8 methods at lines 26, 86, 123, 164, 205, 286, 338, 380 |
| 2 | Auth: password hashing + token storage | `repo/backend/app/service/AuthService.php:38` (bcrypt), `TokenService.php:19` (SHA-256 hash) |
| 3 | Authorization middleware chain | `repo/backend/app/middleware/AuthMiddleware.php`, `RbacMiddleware.php`, `OrgIsolationMiddleware.php` |
| 4 | Route definitions with middleware | `repo/backend/route/api.php` -- 64 routes, 12 groups, every group has middleware |
| 5 | Signed URL + hotlink protection | `repo/backend/app/service/MediaService.php:125-135` (HMAC), `app/middleware/HotlinkMiddleware.php` |
| 6 | Content moderation engine | `repo/backend/app/service/ModerationService.php` -- sensitive words (:26), trigram Jaccard (:245), fingerprint (:95) |
| 7 | Credibility score formula | `repo/backend/app/service/CredibilityService.php:22-115` -- age/completion/pattern factors |
| 8 | Scheduled commands | `repo/backend/app/command/` -- 7 files, registered in `config/console.php` |
| 9 | Audit logging with PII masking | `repo/backend/app/service/AuditService.php` -- log(:18), sanitize(:94), mask in query(:73) |
| 10 | Database schema | `repo/backend/database/schema.sql` -- 20 tables |
| 11 | Frontend order lifecycle UI | `repo/frontend/js/pages/order-detail.js` -- 650 lines, blocked-action messages |
| 12 | Frontend search experience | `repo/frontend/js/pages/listings.js` -- suggestions, did-you-mean, highlights |
| 13 | Test suites | `repo/unit_tests/` (24 backend + 8 frontend), `repo/API_tests/` (6 files) |

---

## 2. Exact Evidence Pointers by Requirement

### 2.1 Order Lifecycle (Tier 1 Critical)

| Rule | Code Location | Evidence |
|------|--------------|----------|
| Pending match → 30-min expiry | `OrderService.php:62` | `strtotime('+30 minutes')` sets `expires_at` |
| Pending match auto-expire command | `OrderExpireCommand.php` | `order:expire` finds `status='pending_match'` older than 30 min |
| Accept = passenger only | `OrderService.php:86-100` | Checks `$order->passenger_id !== $userId` throws |
| Free cancel within 5 min | `OrderService.php:231` | `$fiveMinutesLater = $acceptedAt + (5 * 60)` |
| Reason required after 5 min | `OrderService.php:235-238` | Throws ValidationException if missing |
| Cancel BLOCKED in_progress | `OrderService.php:210-215` | `throw new BusinessException('Cancellation is not allowed once a trip is in progress.', 40901, 409)` |
| Dispute within 72 hours | `OrderService.php:300` | `$seventyTwoHours = $completedAt + (72 * 3600)` |
| Admin-only resolve | Route: `->middleware('rbac:dispute.resolve')` in `route/api.php:85` |
| Frontend blocked message | `order-detail.js:299` | `'Cancellation is not allowed once a trip is in progress.'` |
| Frontend 72h message | `order-detail.js:337` | `'The dispute window (72 hours) has closed for this trip.'` |

**Tests**: `unit_tests/order/OrderStateMachineTest.php` (10 methods), `OrderCancelTest.php` (6 methods), `OrderDisputeTest.php` (5 methods), `OrderExpiryTest.php` (4 methods)

### 2.2 Authentication and Token Security

| Rule | Code Location | Evidence |
|------|--------------|----------|
| Bcrypt password hashing | `AuthService.php:38` | `password_hash($data['password'], PASSWORD_BCRYPT)` |
| Password verification | `AuthService.php:73` | `password_verify($password, $user->password_hash)` |
| Token stored as SHA-256 | `TokenService.php:19` | `hash('sha256', $plaintext)` |
| Plaintext shown once only | `TokenService.php:26-28` | Returns `$plaintext` only in `create()` response |
| Default 90-day expiry | `TokenService.php:16` | `int $expiresInDays = 90` |
| Rotation invalidates old | `TokenService.php:64-78` | Calls `revoke()` on old, then `create()` new |
| Token validation in middleware | `AuthMiddleware.php` | Checks `revoked_at IS NULL`, `expires_at > NOW()`, computes SHA-256 hash |
| Password hidden in model | `User.php` | `$hidden = ['password']` (or `password_hash`) |

**Tests**: `unit_tests/auth/LoginTest.php`, `TokenTest.php`, `PasswordHashTest.php`, `AuthMiddlewareTest.php`

### 2.3 Authorization Boundaries

| Boundary | Code Location | Evidence |
|----------|--------------|----------|
| RBAC middleware on routes | `route/api.php` | Every protected group has `->middleware(['auth'])` or `->middleware(['auth', 'rbac:...'])` |
| Admin-only: governance | `route/api.php` | `->middleware(['auth', 'rbac:governance.view_dashboard'])` |
| Admin-only: audit | `route/api.php` | `->middleware(['auth', 'rbac:audit.read'])` |
| Admin-only: user mgmt | `route/api.php` | `->middleware(['auth', 'rbac:user.manage'])` |
| Moderator: moderation | `route/api.php` | `->middleware(['auth', 'rbac:moderation.read'])` |
| Object-level: listing owner | `ListingController.php` | Checks `$listing->user_id !== $request->user->id` before edit/delete |
| Object-level: order party | `OrderService.php` | `$order->isParty($userId)` check in cancel/start/complete/dispute |
| Org isolation | `OrgIsolationMiddleware.php` | Validates resource `organization_id` matches `$request->orgId` |
| Org scope on models | All org-scoped models | `scopeOrg($query, $orgId)` method adds `where('org_id', $orgId)` |

**Tests**: `unit_tests/auth/RbacTest.php`, `OrgIsolationTest.php`, `API_tests/PermissionApiTest.php`

### 2.4 Offline-First Constraint

| Check | Result | How to Verify |
|-------|--------|---------------|
| External URLs in frontend | **ZERO** | `grep -rn "https\?://" repo/frontend/ --include="*.html" --include="*.js" --include="*.css"` returns nothing |
| CDN references | **ZERO** | No `cdn.`, `googleapis.com`, `cloudflare.com`, `unpkg.com`, `jsdelivr.net` in any file |
| Remote fonts | **ZERO** | No `@import url(http` or `fonts.googleapis.com` in CSS |
| Layui vendored locally | **YES** | `repo/frontend/layui/css/layui.css` (1219 lines), `layui/layui.js` (848 lines) |
| Search: local algorithm | **YES** | `SearchService.php` uses Levenshtein distance against local dictionary |
| Typo correction: local | **YES** | `SearchBuildDictionaryCommand.php` builds dictionary from listing content |
| Sensitive words: local file | **YES** | `database/seeds/sensitive-words.txt` loaded by `ModerationService.php:26` |

### 2.5 Content Moderation

| Component | Code Location | Evidence |
|-----------|--------------|----------|
| Sensitive-word filter | `ModerationService.php:26-67` | Loads word list from file, `\b` word-boundary regex |
| Rate limit (3/hour) | `route/api.php:74` | `->middleware('rate_limit:reviews,3,60')` |
| Rate limit middleware | `RateLimitMiddleware.php` | Sliding window with cache, throws RateLimitException |
| Trigram Jaccard similarity | `ModerationService.php:245-268` | Extracts trigrams, computes intersection/union |
| Duplicate threshold | `ModerationService.php:83` | Uses `env('MODERATION_DUPLICATE_THRESHOLD', 0.85)` |
| File fingerprint (SHA-256) | `MediaService.php:79` | `hash_file('sha256', $tempPath)` |
| Credibility: age factor | `CredibilityService.php:32-38` | 0.5 if < 14 days, 1.0 otherwise |
| Credibility: completion factor | `CredibilityService.php:40-55` | `completed / total` orders |
| Credibility: pattern factor | `CredibilityService.php:58-113` | Burst detection, timing check, near-identical text |
| Auto-flag threshold | `CredibilityService.php` | Reviews with score < 0.3 flagged |
| Manual queue (not auto-reject) | `ModerationService.php:109-130` | `flagItem()` creates queue entry; human approves/rejects |

**Tests**: `unit_tests/review/CredibilityScoreTest.php`, `DuplicateDetectionTest.php`, `moderation/SensitiveWordTest.php`

### 2.6 Media Security

| Rule | Code Location | Evidence |
|------|--------------|----------|
| HMAC-SHA256 signed URL | `MediaService.php:133` | `hash_hmac('sha256', $mediaId . $expires, $secret)` |
| Default 10-min expiry | `MediaService.php:128` | `env('MEDIA_URL_EXPIRY_MINUTES', 10)` |
| Hotlink: signature check | `HotlinkMiddleware.php` | Validates signature + expiry on every media request |
| Hotlink: referrer check | `HotlinkMiddleware.php` | Checks `Referer` against `HOTLINK_ALLOWED_DOMAINS` |
| Photo max 5 MB | `MediaService.php:19` | `PHOTO_MAX_SIZE = 5 * 1024 * 1024` |
| Video max 50 MB | `MediaService.php:20` | `VIDEO_MAX_SIZE = 50 * 1024 * 1024` |
| Max 5 files per review | `MediaService.php` | Checks count before upload |
| UUID filenames | `MediaService.php` | Uses `md5(uniqid())` for disk path, not user filename |
| Watermark via GD | `MediaService.php` | `applyWatermark()` uses `imagecopy` with alpha |

**Tests**: `unit_tests/media/SignedUrlTest.php`, `FileValidationTest.php`, `FingerprintTest.php`

### 2.7 Scheduled Jobs

| Command | File | Schedule | Evidence |
|---------|------|----------|----------|
| `order:expire` | `OrderExpireCommand.php` | `* * * * *` | Finds pending_match > 30 min, sets expired, restores listing |
| `governance:dedup` | `GovernanceDedupCommand.php` | `0 2 * * *` | Dedup events in 1-min window, records lineage |
| `governance:fill` | `GovernanceFillCommand.php` | `15 2 * * *` | Reconciles listing counters from events |
| `governance:lineage` | `GovernanceLineageCommand.php` | `30 2 * * *` | Records daily summary meta-lineage |
| `governance:quality` | `GovernanceQualityCommand.php` | `45 2 * * *` | 7 metrics per org |
| `credibility:recompute` | `CredibilityRecomputeCommand.php` | `0 3 * * *` | Recomputes all review scores |
| `search:build-dictionary` | `SearchBuildDictionaryCommand.php` | `0 4 * * *` | Rebuilds word dictionary from listings |

All registered in `config/console.php`. Cron schedule documented; **Manual Verification Required** for actual cron execution.

### 2.8 PII and Audit Logging

| Rule | Code Location | Evidence |
|------|--------------|----------|
| Audit log masking | `AuditLog.php: toMaskedArray()` | Masks `user_id` → `user_***XX`, `ip_address` → `X.X.***.***` |
| Unmasked requires permission | `AuditController.php` | Checks `audit.read_unmasked` before passing `unmask=true` |
| Sanitize sensitive fields | `AuditService.php:94-107` | Removes `password`, `password_hash`, `token_hash`, `plaintext_token` |
| Helper masking functions | `app/common.php` | `mask_email()`, `mask_ip()`, `mask_user_id()` |
| Frontend PII toggle | `admin-audit.js` | Toggle only shown if user has `audit.read_unmasked` |

---

## 3. Document Consistency Checks

| Check | Files to Compare |
|-------|-----------------|
| API endpoints match route definitions | `docs/api-spec.md` vs `repo/backend/route/api.php` |
| Screen spec covers all pages | `docs/screen-spec.md` vs `repo/frontend/js/pages/` |
| Design doc models match schema | `docs/design.md` sections 8-24 vs `repo/backend/database/schema.sql` |
| Test coverage addresses Tier 1 risks | `docs/test-coverage.md` vs `repo/unit_tests/order/`, `auth/`, `media/` |
| Questions.md decisions in design | `docs/questions.md` vs `docs/design.md` |
| .env.example has all config keys | `repo/.env.example` vs env() calls in backend code |

---

## 4. Quick Grep Commands

```bash
# External URLs in frontend (should return ZERO results)
grep -rn "https\?://" repo/frontend/ --include="*.html" --include="*.js" --include="*.css"

# Routes missing auth middleware (should return ZERO unprotected API routes)
grep -n "Route::" repo/backend/route/api.php | grep -v "middleware\|group\|miss"

# Plaintext password handling (should only find hash/verify)
grep -rni "password" repo/backend/app/service/ --include="*.php"

# Hardcoded secrets (should find only env() references)
grep -rni "secret\|SIGN_SECRET" repo/backend/ --include="*.php" | grep -v "env("

# All console commands
grep -rn "setName(" repo/backend/app/command/ --include="*.php"

# All test methods
grep -c "public function test_" repo/unit_tests/**/*.php repo/API_tests/*.php

# Order cancel-blocked test
grep -n "cancel_blocked_when_in_progress\|40901" repo/unit_tests/order/OrderCancelTest.php

# PHP syntax check (should show zero errors)
find repo/backend -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

---

## 5. Frontend Review Guide

| Area | File | What to Check |
|------|------|---------------|
| Role-based nav | `repo/frontend/js/app.js` | Nav items conditional on `user.roles`. User=5 items, Mod=+Moderation, Admin=+Users/Settings/Governance/Audit |
| Search UX | `repo/frontend/js/pages/listings.js` | Debounced suggestions, did-you-mean link, localStorage history (max 10), `<em>` highlight, no-results fallback |
| Order lifecycle | `repo/frontend/js/pages/order-detail.js` | Progress bar, dynamic action buttons, 5 blocked-action messages, cancel reason form |
| Review form | `repo/frontend/js/pages/review-form.js` | Star widget, char counter (0/1000), file preview, size warnings, rate limit error |
| Moderation | `repo/frontend/js/pages/moderation.js` + `moderation-detail.js` | Access guard, flag badges (colored), credibility breakdown, approve/reject/escalate |
| Governance | `repo/frontend/js/pages/admin-governance.js` + `admin-lineage.js` | Admin guard, metric cards, empty state, expandable lineage rows |
| Audit + PII | `repo/frontend/js/pages/admin-audit.js` | PII toggle (permission-gated), masked by default, JSON diff viewer |
| Error states | Every page module | `.catch()` blocks show user-visible error messages |
| Visual hierarchy | `repo/frontend/css/app.css` | Status badge colors, blocked-action styling, primary/danger buttons |

---

## 6. Manual Verification Required

These items cannot be verified by static review alone:

| Item | Why | How to Verify Manually |
|------|-----|----------------------|
| Cron job execution | Requires running system + cron | Run `php think order:expire` and verify expired orders |
| Docker Compose startup | Requires Docker runtime | Run `docker-compose up -d` and check all 3 containers |
| Session persistence | Requires running MySQL | Login, check session in DB, verify expiry |
| Media upload + watermark | Requires PHP-GD extension + disk | Upload an image, verify watermark applied |
| Signed URL expiry | Requires clock progression | Generate URL, wait 10+ min, verify 403 |
| Sensitive-word detection | Requires running backend | POST review with word from list, verify flagged |
| Nightly job idempotency | Requires DB + multiple runs | Run governance:dedup twice, verify no double-removal |
| Rate limit enforcement | Requires running backend | POST 4 reviews in 1 hour, verify 429 on 4th |
| Frontend rendering | Requires browser | Open index.html, navigate all 21 pages |

---

## 7. Session Logs

Review `sessions/*.json` to verify:
- `develop-1.json`: Phase 0 structure + Phase 1 backend scaffolding
- `develop-2.json`: Phase 1 commands/tests + Phase 2 frontend
- `bugfix-1.json`: Phase 5 hardening (documentation fixes, no code bugs found)
- Each session has: files_changed, requirements_addressed, decisions, open_risks
