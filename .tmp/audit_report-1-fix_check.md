# RideCircle Issue Recheck (Static-Only)

Date: 2026-04-08  
Scope: Static code/document inspection only (no runtime execution).

## Summary
- Fixed: 6
- Partially Fixed: 3
- Not Fixed: 2

## Results by Reported Issue

| # | Reported Issue | Status | Static Evidence | Notes |
|---|---|---|---|---|
| 1 | Blocker: Schema contract mismatch (`org_id`, `password`, `media_type`, related fields) | **Partially Fixed** | `repo/backend/database/schema.sql:31,45,127,177,212,237,261,295,313,334,353,369`; `repo/backend/app/service/MediaService.php:107,116,166`; `repo/backend/app/model/User.php:15` | Major contracts were migrated to `organization_id`, `password_hash`, `file_type`. Remaining inconsistency: `User` model hides `password` instead of `password_hash` (`User.php:15`). |
| 2 | Blocker: Frontend API envelope and HTTP method mismatch | **Partially Fixed** | `repo/frontend/js/api.js:56-59,133-140`; `repo/frontend/js/pages/listings.js:239-241`; `repo/frontend/js/pages/orders.js:218-221`; `repo/backend/route/api.php:47`; `repo/backend/app/controller/ListingController.php:207-237` | Envelope handling and missing methods (`acceptListing`, `flagListing`) were added. Remaining risk: mixed page patterns still exist, so full runtime compatibility cannot be confirmed statically. |
| 3 | High: Order workflow semantics are incorrect, driver accepts directly | **Fixed (Prompt-aligned)** | `repo/backend/app/service/OrderService.php:24-29,61-67`; `repo/backend/app/controller/OrderController.php:71-79` | Implementation now creates order in `accepted` when driver accepts listing, matching prompt semantics. Residual inconsistency: `/orders/:id/accept` route still exists for `pending_match` path (`OrderService.php:88-105`). |
| 4 | High: Invalid status values used (`cancelled`, missing enum coverage) | **Fixed** | `repo/backend/app/service/OrderService.php:261-283`; `repo/backend/app/service/ListingService.php:365`; `repo/backend/database/schema.sql:141,183` | Service now uses `canceled`; schema now includes statuses used by services (`closed`, `resolved`) for listings. |
| 5 | High: Governance commands write non-existent columns | **Fixed** | `repo/backend/database/schema.sql:332-342,351-358`; `repo/backend/app/command/GovernanceDedupCommand.php:96-106`; `repo/backend/app/command/GovernanceFillCommand.php:97-107`; `repo/backend/app/command/GovernanceLineageCommand.php:52-66`; `repo/backend/app/command/GovernanceQualityCommand.php:147-154` | Commands now use `details`/`executed_at` and valid `organization_id` fields consistent with schema. |
| 6 | High: Tenant isolation middleware not wired into request flow | **Fixed** | `repo/backend/config/middleware.php:5-11`; `repo/backend/route/api.php:35,51,57,70,78,92,99,104,110,119` | `org_isolation` alias is registered and applied to authenticated route groups. |
| 7 | Medium: Version diff route/controller parameter mismatch | **Fixed** | `repo/backend/route/api.php:50`; `repo/backend/app/controller/VersionController.php:46-53` | Route and controller now both use path params `:v1`/`:v2`. |
| 8 | Medium: Search suggestion response shape mismatch | **Fixed** | `repo/backend/app/controller/SearchController.php:29-35,50-56`; `repo/frontend/js/pages/listings.js:80-82,246-248,280-282` | Backend now returns `data.suggestions` and `data.did_you_mean`; frontend reads those shapes and handles object/string suggestion. |
| 9 | Medium: Test suite is simulation-based and not representative | **Not Fixed** | `repo/unit_tests/auth/LoginTest.php:11-25`; `repo/unit_tests/auth/AuthMiddlewareTest.php:11-24`; `repo/unit_tests/order/OrderStateMachineTest.php:29-49`; `repo/unit_tests/listing/SearchTest.php:11-22` | Core unit tests still rely heavily on in-memory/anonymous simulation instead of exercising real services/models/routes. |
| 10 | Medium: Documentation references non-existent modules/files | **Not Fixed** | `docs/route-inventory.md:45-46,51,61`; actual files: `repo/frontend/js/pages/listing-form.js`, `repo/frontend/js/pages/review-form.js`, `repo/frontend/js/pages/tokens.js` | Docs still reference `listing-create.js`, `listing-edit.js`, `review-create.js`, `api-tokens.js` which do not exist. |
| 11 | Medium: CORS wildcard origin with credentials enabled | **Partially Fixed** | `repo/backend/app/middleware/CorsMiddleware.php:26-35,45-49,58-62` | Middleware now reflects request origin when configured with `*`, reducing browser-credential conflict. Default still allows wildcard configuration and may need stricter deployment settings. |

## Overall Recheck Conclusion
- The previously reported set is **substantially improved**.
- Remaining material gaps are:
  1. incomplete cleanup of schema-contract edge cases,
  2. simulation-heavy tests,
  3. stale documentation references,
  4. CORS hardening still partly configuration-dependent.
