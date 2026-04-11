# RideCircle Carpool Marketplace

An offline-first carpool coordination platform for private organizations and communities. Passengers post trip requests; drivers browse, accept, and coordinate rides -- all within a local network with no internet dependency.

## Implementation Summary

| Area | Evidence |
|------|----------|
| Backend | ThinkPHP 6 REST API: 13 controllers, 11 services, 19 models, 6 middleware, 5 validators |
| Frontend | Layui SPA: 21 page modules, hash-based router, local assets (zero CDN) |
| Database | MySQL 8.0: 20 tables with FK, indexes, ENUM types (`database/schema.sql`) |
| Auth | Session login + API tokens (bcrypt passwords, SHA-256 token hashes, 90-day default expiry) |
| RBAC | 3 roles (admin, moderator, user), 26 permissions, middleware-enforced on every route |
| Order lifecycle | Strict state machine: 30-min auto-expire, 5-min free cancel, in-progress cancel blocked, 72h dispute window |
| Moderation | Sensitive-word filter, trigram Jaccard duplicate detection, SHA-256 file fingerprint, credibility score |
| Media | Local disk storage, HMAC-SHA256 signed URLs (10-min expiry), GD watermark, hotlink protection |
| Governance | 7 scheduled commands, behavior event capture, nightly dedup/fill/lineage/quality |
| Tests | 258 test methods: 127 backend unit, 83 frontend, 48 API integration |

## Architecture

```
[Layui Frontend]  ──JSON/REST──>  [ThinkPHP Backend]  ──>  [MySQL 8.0]
  (Nginx static)                    (PHP-FPM)               (local server)
                                       │
                                       v
                                  [Local Disk]
                                  (media storage)
```

- **Frontend**: Static HTML/JS/CSS served by Nginx. All Layui assets bundled locally.
- **Backend**: ThinkPHP REST API returning JSON only -- no server-rendered HTML.
- **Database**: MySQL 8.0 with utf8mb4 encoding.
- **Media**: Local filesystem with signed, time-limited access URLs.

## Directory Structure

```
repo/
  frontend/           Layui SPA (21 pages, router, API client, local assets)
  backend/            ThinkPHP API (controllers, services, models, middleware)
  unit_tests/         24 backend PHP tests + 8 frontend browser tests + harness
  API_tests/          6 PHPUnit integration test files
  docker-compose.yml  3 containers: MySQL, backend, frontend
  run_tests.sh        Single test entrypoint (PHPUnit)
  .env.example        All configuration variables with defaults
```

## Quick Start (Docker — matches `docker-compose.yml`)

1. `cd` to the directory that contains `docker-compose.yml` (this repo’s `repo/` folder).
2. Copy `repo/.env.example` to `repo/.env` and set secrets (`APP_KEY`, `MEDIA_SIGN_SECRET`, DB passwords if you change defaults).
3. `docker compose up -d` — MySQL loads `backend/database/schema.sql` on **first** init of an empty data volume; backend waits for MySQL health, then serves the API on **port 8080 inside the container** (host port defaults to **8081**, override with `BACKEND_PORT`).
4. Load fixtures: from `repo/`, run `./run_tests.sh api` once (waits for MySQL + API, pipes `DatabaseSeeder.php` when `organizations` is empty), **or** manually:  
   `docker compose exec backend php /var/www/html/database/seeds/DatabaseSeeder.php | docker compose exec -T mysql mysql -uridecircle -pchange_me_in_production ridecircle`  
   (adjust user/password to match your `.env`).
5. Frontend: `http://localhost:8000` (container `frontend` maps host 8000 → nginx 80).
6. API base URL: `http://localhost:8081` (or `${BACKEND_PORT:-8081}`) — e.g. `GET /api/auth/me` returns `401` without a token.
7. Seeded login: `admin@ridecircle.local` / `Admin123!` (organization code `RC2026`).

**Note:** If you change MySQL data, remove the volume (`docker compose down -v`) to re-run initdb, or apply migrations/seeds manually.

## Configuration (.env.example)

| Variable | Default | Purpose |
|----------|---------|---------|
| DB_HOST, DB_PORT, DB_DATABASE | 127.0.0.1, 3306, ridecircle | MySQL connection |
| SESSION_LIFETIME | 120 | Session expiry in minutes |
| API_TOKEN_EXPIRY_DAYS | 90 | Default API token lifespan |
| MEDIA_SIGN_SECRET | (required) | HMAC secret for signed URLs |
| MEDIA_STORAGE_PATH | /var/ridecircle/media | Local disk path for uploads |
| MEDIA_URL_EXPIRY_MINUTES | 10 | Signed URL TTL |
| MEDIA_WATERMARK_ENABLED | true | Apply watermark to photos |
| MODERATION_WORD_LIST_PATH | (local file) | Sensitive word dictionary |
| MODERATION_DUPLICATE_THRESHOLD | 0.85 | Trigram Jaccard threshold |
| MODERATION_REVIEW_RATE_LIMIT | 3 | Max reviews per user per hour |
| CREDIBILITY_WEIGHT_* | 0.3, 0.4, 0.3 | Age, completion, pattern weights |
| ORDER_PENDING_EXPIRE_MINUTES | 30 | Pending match auto-expire |
| ORDER_FREE_CANCEL_MINUTES | 5 | Free cancellation window |
| ORDER_DISPUTE_WINDOW_HOURS | 72 | Dispute opening window |
| HOTLINK_ALLOWED_DOMAINS | localhost,127.0.0.1 | Allowed referrer domains |
| LOG_MASK_PII | true | Mask identifiers in logs |

## Offline Deployment Assumptions

This system is designed for fully offline operation:
- **No internet required** at any point (install, run, or use).
- All PHP dependencies are specified in `composer.json` and must be installed via `composer install` before deployment (or vendored).
- All Layui assets are bundled in `frontend/layui/` -- no CDN fallback.
- The search dictionary, sensitive-word list, and typo correction all use local data.
- Docker images (nginx, mysql, php) must be pre-pulled or loaded from local tarballs.
- No external APIs, fonts, analytics, or telemetry.

## Running Tests

```bash
./run_tests.sh          # Run all tests (unit + API)
./run_tests.sh unit     # Backend unit tests only
./run_tests.sh api      # API integration tests only
```

**Prerequisites**: PHP 8.x, Composer dependencies installed (`cd backend && composer install`).

### Frontend Tests

Browser-based HTML test files in `unit_tests/frontend/`. Open directly in a browser:

```bash
open unit_tests/frontend/test-navigation.html      # Role-based nav (8 tests)
open unit_tests/frontend/test-listing-form.html     # Form validation (13 tests)
open unit_tests/frontend/test-search.html           # Search UX (13 tests)
open unit_tests/frontend/test-order-lifecycle.html  # Lifecycle UI (13 tests)
open unit_tests/frontend/test-review-form.html      # Review form (14 tests)
open unit_tests/frontend/test-moderation.html       # Moderation queue (9 tests)
open unit_tests/frontend/test-governance.html       # Governance (7 tests)
```

No build step, Node.js, or test runner required.

### API Integration Tests

Require a running backend + MySQL with seeded test data:

```bash
cd backend && php vendor/bin/phpunit --configuration phpunit.xml --testsuite api
```

## Scheduled Jobs

| Command | Schedule | Purpose |
|---------|----------|---------|
| `php think order:expire` | `* * * * *` | Auto-expire pending_match orders > 30 min |
| `php think governance:dedup` | `0 2 * * *` | Deduplicate behavior events |
| `php think governance:fill` | `15 2 * * *` | Reconcile listing counters |
| `php think governance:lineage` | `30 2 * * *` | Record daily lineage summary |
| `php think governance:quality` | `45 2 * * *` | Compute 7 quality metrics |
| `php think credibility:recompute` | `0 3 * * *` | Recompute review credibility scores |
| `php think search:build-dictionary` | `0 4 * * *` | Rebuild search dictionary |

Commands are registered in `backend/config/console.php`. Add cron entries manually:

```crontab
* * * * * cd /path/to/backend && php think order:expire >> /var/log/ridecircle/cron.log 2>&1
0 2 * * * cd /path/to/backend && php think governance:dedup >> /var/log/ridecircle/cron.log 2>&1
# ... (see route-inventory.md for full schedule)
```

**Manual Verification Required**: Cron execution, idempotency, and lineage recording require a running system.

## Security Notes

| Area | Implementation | Where to Verify |
|------|---------------|-----------------|
| Password hashing | bcrypt via `password_hash(PASSWORD_BCRYPT)` | `backend/app/service/AuthService.php:38` |
| Token storage | SHA-256 hash; plaintext shown once at creation | `backend/app/service/TokenService.php:19` |
| Token rotation | Old token revoked immediately; new one issued | `backend/app/service/TokenService.php:64` |
| Token expiry | Default 90 days, configurable | `backend/app/service/TokenService.php:16` |
| 401 boundaries | AuthMiddleware rejects missing/expired/revoked credentials | `backend/app/middleware/AuthMiddleware.php` |
| 403 boundaries | RbacMiddleware checks role permissions | `backend/app/middleware/RbacMiddleware.php` |
| Object-level authz | Controllers verify ownership before edit/delete | Each controller's update/destroy methods |
| Org isolation | OrgIsolationMiddleware + model scopeOrg() | `backend/app/middleware/OrgIsolationMiddleware.php` |
| Admin routes | Middleware: `rbac:governance.view_dashboard`, `rbac:audit.read`, `rbac:user.manage` | `backend/route/api.php` |
| Audit masking | user_id and ip_address masked by default | `backend/app/model/AuditLog.php: toMaskedArray()` |
| Audit sanitization | password_hash, token_hash stripped from audit values | `backend/app/service/AuditService.php:94-107` |
| No debug routes | No admin bypass, no debug endpoints | Verify: `grep -n "debug\|bypass" backend/route/api.php` |
| Media security | HMAC-SHA256 signed URLs, referrer validation | `backend/app/middleware/HotlinkMiddleware.php` |

## Known Limitations

| Limitation | Severity | Notes |
|------------|----------|-------|
| Composer `vendor/` not included | Expected | Run `composer install` before deployment |
| Layui shim (848 lines) instead of full distribution | Medium | Implements all used APIs; full Layui can replace it |
| No Layui icon font files | Low | Icon placeholders via CSS; add icon font for production |
| Frontend tests are browser-based, not CI-integrated | Low | Can be adapted to headless browser runner |
| API tests need running backend + MySQL | Expected | Documented prerequisites above |
| Docker environment not tested end-to-end | Medium | **Manual Verification Required** |
| Comment feature (comment_count) not fully modeled | Low | Sort by "most discussed" works; comment table deferred |
| Dashboard aggregation endpoint not dedicated | Low | Dashboard calls existing listing/order endpoints |
| No image/media preview for expired signed URLs | Low | UI shows error; URL refresh mechanism possible |

## Manual Verification Required

These items are structurally correct in code but require runtime execution to confirm:

1. Docker Compose startup (3 containers)
2. MySQL schema import and data seeding
3. Session creation and expiry
4. API token creation, rotation, and expiry
5. Cron job execution (7 commands)
6. Media upload, watermark, and signed URL serving
7. Sensitive-word detection triggering moderation queue
8. Rate limit enforcement (429 on 4th review/hour)
9. Order auto-expire after 30 minutes
10. Frontend rendering in browser (all 21 pages)

## Documentation

See the `docs/` directory at the project root:
- `design.md` -- Architecture and design decisions (25 sections)
- `api-spec.md` -- REST API specification (12 endpoint groups)
- `route-inventory.md` -- Frontend pages + backend routes
- `screen-spec.md` -- UI screen specifications (22 screens)
- `test-coverage.md` -- Risk-first test strategy with coverage mapping
- `questions.md` -- 10 business ambiguities with resolutions
- `reviewer-notes.md` -- Static review guide with exact file:line evidence
