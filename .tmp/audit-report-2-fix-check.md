# Issue Verification Report

Date: 2026-04-08
Workspace: /Users/mac/Documents/EaglePoint/TASK-ridecircle

## Summary
- Total reviewed: 10
- Fixed: 9
- Still open: 1

## Results

1. Registration API field mismatch (`invite_code` vs `organization_code`)
- Status: Fixed
- Evidence:
  - Backend validator requires `organization_code`: `repo/backend/app/validate/AuthValidate.php:23,52-53`
  - Controller reads `organization_code`: `repo/backend/app/controller/AuthController.php:46`
  - Frontend register form sends `organization_code`: `repo/frontend/js/pages/register.js:44,202`
  - No `invite_code` usage found in repo (`rg -n invite_code` returned no matches).

2. Order lifecycle action/status gating mismatch in order detail flow
- Status: Still open
- Evidence:
  - Backend order creation sets status directly to `accepted`: `repo/backend/app/service/OrderService.php:65`
  - Order detail shows "Accept Match" only for `pending_match`: `repo/frontend/js/pages/order-detail.js:255-258`
  - Order API test still expects newly created order to be `pending_match`: `repo/API_tests/OrderApiTest.php:113`
- Why open:
  - Creation state and UI/test lifecycle assumptions are not aligned.

3. Governance dictionary rebuild command writing nonexistent schema field (`created_at`)
- Status: Fixed
- Evidence:
  - `search_dictionary` schema has no `created_at` (has `updated_at`): `repo/backend/database/schema.sql:367-373`
  - Command insert payload excludes `created_at`: `repo/backend/app/command/SearchBuildDictionaryCommand.php:75-79`
  - Model disables auto timestamps: `repo/backend/app/model/SearchDictionary.php:11`

4. Credibility recompute command writing nonexistent lineage fields (`started_at`/`completed_at`)
- Status: Fixed
- Evidence:
  - `data_lineage` schema has no `started_at`/`completed_at` columns: `repo/backend/database/schema.sql:332-343`
  - Command stores these inside JSON `details`, not table columns: `repo/backend/app/command/CredibilityRecomputeCommand.php:84-87`

5. Credibility recompute command querying orders by nonexistent `user_id` column
- Status: Fixed
- Evidence:
  - `orders` schema uses `passenger_id`/`driver_id`, not `user_id`: `repo/backend/database/schema.sql:181-183`
  - Command queries via `passenger_id` OR `driver_id`: `repo/backend/app/command/CredibilityRecomputeCommand.php:108-118`

6. Order expiry command writing nonexistent order field (`expired_at` instead of `expires_at`)
- Status: Fixed
- Evidence:
  - Schema field is `expires_at`: `repo/backend/database/schema.sql:197`
  - Command writes `expires_at`: `repo/backend/app/command/OrderExpireCommand.php:45`

7. Order expiry command writing nonexistent audit field (`user_agent`)
- Status: Fixed
- Evidence:
  - `audit_logs` schema has no `user_agent`: `repo/backend/database/schema.sql:311-322`
  - Command audit insert does not include `user_agent`: `repo/backend/app/command/OrderExpireCommand.php:56-66`

8. Password hash exposure risk from user serialization (`password_hash` not hidden)
- Status: Fixed
- Evidence:
  - User model hides `password_hash`: `repo/backend/app/model/User.php:15`
  - Auth responses serialize user with `toArray()`: `repo/backend/app/controller/AuthController.php:32,55`

9. Login tenant ambiguity from email-only lookup (not org-scoped)
- Status: Fixed
- Evidence:
  - Login requires `organization_code`: `repo/backend/app/validate/AuthValidate.php:52-53`
  - Service resolves organization and scopes user lookup by `organization_id`: `repo/backend/app/service/AuthService.php:68-75`

10. Moderation mutate endpoints protected by read permission instead of update permission
- Status: Fixed
- Evidence:
  - Mutate endpoints (`approve`, `reject`, `escalate`) use `rbac:moderation.update`: `repo/backend/route/api.php:89-91`
  - Only queue read endpoint uses `rbac:moderation.read`: `repo/backend/route/api.php:88`

## Recommendation for remaining open issue
- Choose one lifecycle contract and align all layers:
  - Option A: Keep create -> `accepted`, remove/retire `/orders/:id/accept` and pending-match UI path.
  - Option B: Change create -> `pending_match` + set `expires_at`, keep passenger accept flow in UI/tests.
