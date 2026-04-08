# RideCircle Carpool Marketplace -- Route Inventory

> **Backend routes implemented in: `repo/backend/route/api.php`**
>
> All backend route groups listed below are implemented and registered. Route definitions include middleware assignments for authentication, RBAC, rate limiting, and hotlink protection. Scheduled commands are registered in `repo/backend/config/console.php` with implementations in `repo/backend/app/command/`.

## Frontend Pages

| # | Page | URL Path | Roles | Description |
|---|------|----------|-------|-------------|
| 1 | Login | /login | Public | Email/password login form |
| 2 | Register | /register | Public | New user registration with org invite code |
| 3 | Dashboard | / | All authenticated | Role-appropriate landing: recent listings, active orders, quick stats |
| 4 | Listing Browse | /listings | All authenticated | Search, filter, sort listings; keyword suggestions; did-you-mean; no-results fallback |
| 5 | Listing Detail | /listings/{id} | All authenticated | Full listing view, status badge, accept button (for drivers), version history link |
| 6 | Listing Create | /listings/create | All authenticated | Form: title, description, addresses, rider count, vehicle type, baggage notes, time window, tags; save draft or publish |
| 7 | Listing Edit | /listings/{id}/edit | Owner | Edit form, version diff preview before re-publish |
| 8 | Listing Version History | /listings/{id}/versions | All authenticated | Timeline of versions, diff comparison between any two versions |
| 9 | My Listings | /my/listings | All authenticated | User's own listings with status filters, bulk close action (admin) |
| 10 | Order List | /orders | All authenticated | User's orders (as passenger or driver), status filters |
| 11 | Order Detail | /orders/{id} | Parties + Admin | Lifecycle UI with status badge, action buttons, blocked-action messages, timestamps |
| 12 | Review Create | /orders/{id}/review | Parties | Star rating, text input, file upload with preview, size/count limits shown |
| 13 | Reviews List | /reviews | All authenticated | Browse reviews, filter by listing or user |
| 14 | Profile | /profile | All authenticated | View/edit own profile |
| 15 | Moderation Queue | /moderation | Moderator, Admin | Flagged items list, approve/reject/escalate actions |
| 16 | Moderation Detail | /moderation/{id} | Moderator, Admin | Full content view, credibility score, user history, action panel |
| 17 | Admin: Users | /admin/users | Admin | User list, role assignment, enable/disable |
| 18 | Admin: Org Settings | /admin/settings | Admin | Organization configuration |
| 19 | Admin: Governance Dashboard | /admin/governance | Admin | Data quality metrics charts, event stats |
| 20 | Admin: Governance Lineage | /admin/governance/lineage | Admin | Data lineage records for nightly jobs |
| 21 | Admin: Audit Logs | /admin/audit | Admin | Searchable audit log viewer with PII masking toggle |
| 22 | API Tokens | /profile/tokens | All authenticated | Manage personal API tokens: create, list, rotate, revoke |

## Frontend Implementation

Each frontend page URL maps to a JavaScript module in `repo/frontend/js/pages/`. The SPA uses hash-based routing managed by `repo/frontend/js/router.js`.

| # | URL Path | JS Module File | Notes |
|---|----------|---------------|-------|
| 1 | #/login | js/pages/login.js | Public; redirects to dashboard if already authenticated |
| 2 | #/register | js/pages/register.js | Public; org invite code field |
| 3 | #/ | js/pages/dashboard.js | Role-dependent quick stats and activity feed |
| 4 | #/listings | js/pages/listings.js | Search bar, filters, sort tabs, paginated results |
| 5 | #/listings/:id | js/pages/listing-detail.js | Detail view with action panel and version history link |
| 6 | #/listings/create | js/pages/listing-create.js | Form with draft/publish dual action |
| 7 | #/listings/:id/edit | js/pages/listing-edit.js | Pre-populated form with change preview |
| 8 | #/listings/:id/versions | js/pages/listing-versions.js | Timeline and diff viewer |
| 9 | #/my/listings | js/pages/my-listings.js | Status filter tabs, bulk close (admin) |
| 10 | #/orders | js/pages/orders.js | Role and status filters, paginated cards |
| 11 | #/orders/:id | js/pages/order-detail.js | Lifecycle bar, action buttons, blocked-action messages |
| 12 | #/orders/:id/review | js/pages/review-create.js | Star rating, text, file upload with preview |
| 13 | #/reviews | js/pages/reviews.js | Filter by listing or user, paginated |
| 14 | #/profile | js/pages/profile.js | View/edit profile |
| 15 | #/moderation | js/pages/moderation.js | Flagged items queue (moderator+admin) |
| 16 | #/moderation/:id | js/pages/moderation-detail.js | Full content view with credibility breakdown |
| 17 | #/admin/users | js/pages/admin-users.js | User table with role management (admin) |
| 18 | #/admin/settings | js/pages/admin-settings.js | Organization configuration (admin) |
| 19 | #/admin/governance | js/pages/admin-governance.js | Data quality metrics and charts (admin) |
| 20 | #/admin/governance/lineage | js/pages/admin-lineage.js | Data lineage records (admin) |
| 21 | #/admin/audit | js/pages/admin-audit.js | Audit log viewer with PII toggle (admin) |
| 22 | #/profile/tokens | js/pages/api-tokens.js | API token management |

## Backend Route Groups

### Group: /api/auth
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| POST | /api/auth/register | AuthController@register | guest |
| POST | /api/auth/login | AuthController@login | guest |
| POST | /api/auth/logout | AuthController@logout | auth |
| GET | /api/auth/me | AuthController@me | auth |
| POST | /api/auth/tokens | TokenController@store | auth |
| GET | /api/auth/tokens | TokenController@index | auth |
| DELETE | /api/auth/tokens/{id} | TokenController@destroy | auth |
| POST | /api/auth/tokens/{id}/rotate | TokenController@rotate | auth |

### Group: /api/listings
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/listings | ListingController@index | auth |
| POST | /api/listings | ListingController@store | auth, can:listing.create |
| GET | /api/listings/{id} | ListingController@show | auth |
| PUT | /api/listings/{id} | ListingController@update | auth, can:listing.update, owner |
| DELETE | /api/listings/{id} | ListingController@destroy | auth, can:listing.delete, owner |
| POST | /api/listings/{id}/publish | ListingController@publish | auth, owner |
| POST | /api/listings/{id}/unpublish | ListingController@unpublish | auth, owner-or-moderator |
| POST | /api/listings/bulk-close | ListingController@bulkClose | auth, can:listing.admin |
| GET | /api/listings/{id}/versions | VersionController@index | auth |
| GET | /api/listings/{id}/versions/{version} | VersionController@show | auth |
| GET | /api/listings/{id}/versions/{v1}/diff/{v2} | VersionController@diff | auth |

### Group: /api/search
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/search/suggestions | SearchController@suggestions | auth |
| GET | /api/search/did-you-mean | SearchController@didYouMean | auth |

### Group: /api/orders
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/orders | OrderController@index | auth |
| POST | /api/orders | OrderController@store | auth |
| GET | /api/orders/{id} | OrderController@show | auth, party-or-admin |
| POST | /api/orders/{id}/accept | OrderController@accept | auth, passenger |
| POST | /api/orders/{id}/start | OrderController@start | auth, party |
| POST | /api/orders/{id}/complete | OrderController@complete | auth, party |
| POST | /api/orders/{id}/cancel | OrderController@cancel | auth, party |
| POST | /api/orders/{id}/dispute | OrderController@dispute | auth, party |
| POST | /api/orders/{id}/resolve | OrderController@resolve | auth, can:dispute.resolve |

### Group: /api/reviews
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/reviews | ReviewController@index | auth |
| POST | /api/reviews | ReviewController@store | auth, rate-limit:3/hour |
| PUT | /api/reviews/{id} | ReviewController@update | auth, owner |
| DELETE | /api/reviews/{id} | ReviewController@destroy | auth, owner |

### Group: /api/media
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/media/{id} | MediaController@show | signed-url, hotlink |
| GET | /api/media/{id}/thumbnail | MediaController@thumbnail | signed-url, hotlink |

### Group: /api/moderation
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/moderation/queue | ModerationController@index | auth, can:moderation.read |
| POST | /api/moderation/queue/{id}/approve | ModerationController@approve | auth, can:moderation.update |
| POST | /api/moderation/queue/{id}/reject | ModerationController@reject | auth, can:moderation.update |
| POST | /api/moderation/queue/{id}/escalate | ModerationController@escalate | auth, can:moderation.update |

### Group: /api/governance
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/governance/quality-metrics | GovernanceController@metrics | auth, can:governance.view_dashboard |
| GET | /api/governance/lineage | GovernanceController@lineage | auth, can:governance.view_dashboard |
| GET | /api/governance/events | GovernanceController@events | auth, can:governance.view_dashboard |

### Group: /api/audit
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/audit/logs | AuditController@index | auth, can:audit.read |

### Group: /api/org
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/org/settings | OrgController@show | auth, can:org_settings.read |
| PUT | /api/org/settings | OrgController@update | auth, can:org_settings.update |

### Group: /api/users
| Method | Endpoint | Controller | Middleware |
|--------|----------|------------|------------|
| GET | /api/users | UserController@index | auth, can:user.manage |
| GET | /api/users/{id} | UserController@show | auth, can:user.manage |
| PUT | /api/users/{id}/roles | UserController@updateRoles | auth, can:role.manage |
| POST | /api/users/{id}/disable | UserController@disable | auth, can:user.manage |
| POST | /api/users/{id}/enable | UserController@enable | auth, can:user.manage |

### Scheduled Commands (Cron)
| Command | Schedule | Description |
|---------|----------|-------------|
| php think order:expire | * * * * * | Auto-expire pending_match orders > 30 min |
| php think governance:dedup | 0 2 * * * | Nightly event deduplication |
| php think governance:fill | 15 2 * * * | Nightly missing-value completion |
| php think governance:lineage | 30 2 * * * | Record lineage for cleanup steps |
| php think governance:quality | 45 2 * * * | Compute data quality metrics |
| php think credibility:recompute | 0 3 * * * | Recompute review credibility scores |
| php think search:build-dictionary | 0 4 * * * | Rebuild did-you-mean dictionary from listing content |
