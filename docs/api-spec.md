# RideCircle Carpool Marketplace -- API Specification

## Conventions

- Base path: `/api`
- Content-Type: `application/json` for all requests and responses
- Authentication: session cookie or `Authorization: Bearer <token>` header
- All responses use the envelope: `{ "code": 0, "message": "success", "data": {...} }`
- Error responses add `"errors": {...}` for field-level validation failures
- Timestamps are ISO 8601 format (UTC)
- Pagination: `?page=1&per_page=20` (defaults), response includes `"meta": { "total", "page", "per_page", "last_page" }`

## Error Code Ranges

| Range       | Category |
|-------------|----------|
| 0           | Success |
| 40001-40099 | Validation errors |
| 40101-40199 | Authentication errors |
| 40301-40399 | Authorization errors |
| 40401-40499 | Not found errors |
| 40901-40999 | Conflict / state errors |
| 42201-42299 | Unprocessable entity |
| 42901-42999 | Rate limit errors |
| 50001-50099 | Server errors |

---

## 1. Authentication

### POST /api/auth/register

Register a new user account.

**Request:**
```json
{
  "name": "string, required, 2-100 chars",
  "email": "string, required, valid email, unique per org",
  "password": "string, required, 8-72 chars, mixed case + digit",
  "password_confirmation": "string, required, must match password",
  "organization_code": "string, required, valid org invite code"
}
```

**Success (201):**
```json
{
  "code": 0,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.local",
      "organization_id": 1,
      "roles": ["user"],
      "created_at": "2026-04-08T10:00:00Z"
    }
  }
}
```

**Errors:**
- 40001: Validation failed (field errors in `errors` object)
- 40901: Email already registered

### POST /api/auth/login

**Request:**
```json
{
  "email": "string, required",
  "password": "string, required"
}
```

**Success (200):**
```json
{
  "code": 0,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.local",
      "organization_id": 1,
      "roles": ["user"]
    },
    "session_expires_at": "2026-04-08T12:00:00Z"
  }
}
```

**Errors:**
- 40101: Invalid credentials

### POST /api/auth/logout

**Success (200):**
```json
{ "code": 0, "message": "Logged out", "data": null }
```

### GET /api/auth/me

Returns current authenticated user profile.

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.local",
      "organization_id": 1,
      "roles": ["user"],
      "created_at": "2026-04-08T10:00:00Z"
    }
  }
}
```

---

## 2. API Tokens

### POST /api/auth/tokens

Create a new API token.

**Request:**
```json
{
  "name": "string, required, 1-100 chars",
  "expires_in_days": "integer, optional, default 90, max 365"
}
```

**Success (201):**
```json
{
  "code": 0,
  "message": "Token created",
  "data": {
    "token": {
      "id": 1,
      "name": "My integration",
      "plaintext_token": "rc_xxxxxxxxxxxxxxxxxxxx",
      "expires_at": "2026-07-07T10:00:00Z",
      "created_at": "2026-04-08T10:00:00Z"
    }
  }
}
```

Note: `plaintext_token` is shown only in this response. It cannot be retrieved again.

### GET /api/auth/tokens

List current user's tokens (without plaintext).

### DELETE /api/auth/tokens/{id}

Revoke a token.

### POST /api/auth/tokens/{id}/rotate

Rotate a token. Returns new plaintext, old token is immediately invalidated.

---

## 3. Listings

### GET /api/listings

Browse/search listings.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| q | string | Keyword search (title, description, tags) |
| status | string | Filter by status: active, matched, in_progress, completed, canceled, disputed |
| vehicle_type | string | Filter: sedan, suv, van |
| rider_count_min | int | Min riders (1-6) |
| rider_count_max | int | Max riders (1-6) |
| sort | string | newest (default), most_discussed, most_popular |
| page | int | Page number |
| per_page | int | Items per page (default 20, max 100) |

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "listings": [
      {
        "id": 1,
        "title": "Downtown to Airport",
        "description": "Need a ride to the airport tomorrow morning",
        "pickup_address": "123 Main St",
        "dropoff_address": "City Airport Terminal 2",
        "rider_count": 2,
        "vehicle_type": "sedan",
        "baggage_notes": "One large suitcase",
        "time_window_start": "2026-04-09 08:00 AM",
        "time_window_end": "2026-04-09 09:00 AM",
        "status": "active",
        "tags": ["airport", "morning"],
        "highlight": {
          "title": "Downtown to <em>Airport</em>",
          "description": "Need a ride to the <em>airport</em> tomorrow morning"
        },
        "version": 3,
        "view_count": 42,
        "favorite_count": 5,
        "comment_count": 3,
        "user": {
          "id": 1,
          "name": "John Doe"
        },
        "created_at": "2026-04-08T10:00:00Z",
        "updated_at": "2026-04-08T11:30:00Z"
      }
    ],
    "meta": {
      "total": 50,
      "page": 1,
      "per_page": 20,
      "last_page": 3
    },
    "suggestions": ["airport shuttle", "airport transfer"],
    "did_you_mean": null,
    "recent_active": []
  }
}
```

When `q` returns no results, `recent_active` is populated with up to 10 recently active listings as a fallback.

When `q` appears to contain a typo, `did_you_mean` contains a corrected suggestion.

### GET /api/listings/{id}

Get listing detail.

**Success (200):** Full listing object with all fields including version history summary.

### POST /api/listings

Create a new listing (draft or direct publish).

**Request:**
```json
{
  "title": "string, required, 5-200 chars",
  "description": "string, optional, max 2000 chars",
  "pickup_address": "string, required, 5-500 chars",
  "dropoff_address": "string, required, 5-500 chars",
  "rider_count": "integer, required, 1-6",
  "vehicle_type": "string, required, enum: sedan|suv|van",
  "baggage_notes": "string, optional, max 500 chars",
  "time_window_start": "string, required, format: YYYY-MM-DD hh:mm AM/PM",
  "time_window_end": "string, required, format: YYYY-MM-DD hh:mm AM/PM, must be after start",
  "tags": "array of strings, optional, max 10 tags, each max 30 chars",
  "publish": "boolean, optional, default false (save as draft)"
}
```

**Success (201):**
```json
{
  "code": 0,
  "message": "Listing created",
  "data": {
    "listing": { "...full listing object..." }
  }
}
```

**Errors:**
- 40001: Validation failed
- 40301: Not authorized (missing listing.create permission)

### PUT /api/listings/{id}

Update a listing. Creates a new version.

**Request:** Same fields as POST (all optional, only changed fields required).

**Errors:**
- 40301: Not the owner
- 40901: Cannot edit listing in current status (e.g., in_progress)

### POST /api/listings/{id}/publish

Publish a draft or re-publish after edits.

**Success (200):**
```json
{
  "code": 0,
  "message": "Listing published",
  "data": {
    "listing": { "...full listing object with status: active..." }
  }
}
```

### POST /api/listings/{id}/unpublish

Unpublish an active listing (return to draft).

### POST /api/listings/bulk-close

Bulk close outdated listings.

**Request:**
```json
{
  "listing_ids": [1, 2, 3],
  "reason": "outdated"
}
```

**Errors:**
- 40301: Administrator only
- 40901: Some listings cannot be closed (already matched/in-progress). Response lists which failed.

### GET /api/listings/{id}/versions

Get version history.

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "versions": [
      {
        "version": 3,
        "change_summary": "Updated pickup address and time window",
        "created_by": { "id": 1, "name": "John Doe" },
        "created_at": "2026-04-08T11:30:00Z"
      }
    ]
  }
}
```

### GET /api/listings/{id}/versions/{version}

Get a specific version snapshot.

### GET /api/listings/{id}/versions/{v1}/diff/{v2}

Compare two versions. Returns field-level diff.

---

## 4. Search Helpers

### GET /api/search/suggestions

Keyword suggestions based on existing listing content.

**Query:** `?q=air` (partial keyword)

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "suggestions": ["airport", "airport shuttle", "air conditioning"]
  }
}
```

### GET /api/search/did-you-mean

Typo correction for a search query.

**Query:** `?q=airprot`

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "original": "airprot",
    "suggestion": "airport",
    "confidence": 0.92
  }
}
```

Implementation: Levenshtein distance against a dictionary built from listing titles, descriptions, and tags. Updated nightly.

---

## 5. Orders

### POST /api/orders

Driver accepts a listing, creating an order.

**Request:**
```json
{
  "listing_id": "integer, required",
  "driver_notes": "string, optional, max 500 chars"
}
```

**Success (201):**
```json
{
  "code": 0,
  "message": "Order created",
  "data": {
    "order": {
      "id": 1,
      "listing_id": 1,
      "passenger_id": 1,
      "driver_id": 2,
      "status": "pending_match",
      "driver_notes": "I have a blue sedan",
      "created_at": "2026-04-08T10:00:00Z",
      "expires_at": "2026-04-08T10:30:00Z"
    }
  }
}
```

Note: The initial status is `pending_match`. The listing creator (passenger) must accept the match for the order to move to `accepted`.

### GET /api/orders

List orders for the current user (as passenger or driver).

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| role | string | Filter: passenger, driver, or all (default) |
| status | string | Filter by status |
| page, per_page | int | Pagination |

### GET /api/orders/{id}

Get order detail with current status and allowed transitions.

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "order": {
      "id": 1,
      "listing_id": 1,
      "listing": { "...summary..." },
      "passenger": { "id": 1, "name": "John Doe" },
      "driver": { "id": 2, "name": "Jane Smith" },
      "status": "accepted",
      "status_badge": "Accepted",
      "driver_notes": "I have a blue sedan",
      "cancel_reason": null,
      "dispute_reason": null,
      "resolution_notes": null,
      "allowed_transitions": ["in_progress", "canceled"],
      "cancel_free_until": "2026-04-08T10:05:00Z",
      "created_at": "2026-04-08T10:00:00Z",
      "accepted_at": "2026-04-08T10:01:00Z",
      "completed_at": null,
      "disputed_at": null,
      "resolved_at": null
    }
  }
}
```

The `allowed_transitions` array tells the frontend which action buttons to show. When an action is blocked, the response explains why.

### POST /api/orders/{id}/accept

Passenger accepts the driver match. Transitions: pending_match -> accepted.

### POST /api/orders/{id}/start

Either party marks trip as started. Transitions: accepted -> in_progress.

### POST /api/orders/{id}/complete

Driver or both parties mark trip complete. Transitions: in_progress -> completed.

### POST /api/orders/{id}/cancel

Cancel an order.

**Request:**
```json
{
  "reason_code": "string, required if past free-cancel window, enum: DRIVER_UNAVAILABLE|PASSENGER_CHANGED_PLANS|VEHICLE_ISSUE|SCHEDULE_CONFLICT|OTHER",
  "reason_text": "string, required if reason_code=OTHER, max 500 chars"
}
```

**Errors:**
- 40901: Cannot cancel -- order is in_progress. Message: "Cancellation is not allowed once a trip is in progress."
- 40001: Reason code required (past 5-minute free window).

### POST /api/orders/{id}/dispute

Open a dispute.

**Request:**
```json
{
  "reason": "string, required, max 1000 chars"
}
```

**Errors:**
- 40901: Dispute window expired (>72 hours after completion). Message: "Disputes must be opened within 72 hours of trip completion."
- 40901: Order not in completed status.

### POST /api/orders/{id}/resolve

Administrator resolves a dispute.

**Request:**
```json
{
  "resolution": "string, required, max 2000 chars",
  "outcome": "string, required, enum: passenger_favor|driver_favor|mutual|dismissed"
}
```

**Errors:**
- 40301: Administrator only.

---

## 6. Reviews

### GET /api/reviews

List reviews (filterable by listing, user, or order).

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| listing_id | int | Reviews for a specific listing |
| user_id | int | Reviews by a specific user |
| order_id | int | Review for a specific order |
| page, per_page | int | Pagination |

### POST /api/reviews

Create a review.

**Request (multipart/form-data):**
| Field | Type | Validation |
|-------|------|------------|
| order_id | integer | Required, must be a completed order involving the user |
| rating | integer | Required, 1-5 |
| text | string | Required, 1-1000 chars |
| media[] | file | Optional, max 5 files total |

File validation:
- Photos: max 5 MB each, allowed: jpeg, png, gif, webp
- Videos: max 50 MB each, allowed: mp4, webm
- Total files per review: max 5

**Success (201):**
```json
{
  "code": 0,
  "message": "Review submitted",
  "data": {
    "review": {
      "id": 1,
      "order_id": 1,
      "user_id": 1,
      "rating": 5,
      "text": "Great ride, very punctual!",
      "credibility_score": 0.85,
      "status": "published",
      "media": [
        {
          "id": 1,
          "file_type": "photo",
          "url": "/api/media/1?signature=xxx&expires=1712577600",
          "thumbnail_url": "/api/media/1/thumbnail?signature=xxx&expires=1712577600"
        }
      ],
      "created_at": "2026-04-08T12:00:00Z"
    }
  }
}
```

**Errors:**
- 42901: Rate limit exceeded (3 reviews/hour). Message: "You can submit up to 3 reviews per hour. Please try again later." Headers include `Retry-After`.
- 40001: Validation failed.
- 40901: Duplicate content detected. Message: "This review appears to be a duplicate of a recent submission."

### PUT /api/reviews/{id}

Edit own review (if not yet moderated).

### DELETE /api/reviews/{id}

Delete own review.

---

## 7. Media

### GET /api/media/{id}

Serve a media file. Requires valid signed URL parameters.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| signature | string | HMAC-SHA256 signature |
| expires | integer | Unix timestamp expiry |

**Errors:**
- 40301: Invalid or expired signature.
- 40301: Hotlink protection failed (invalid referrer).

### GET /api/media/{id}/thumbnail

Serve a photo thumbnail (generated on first request, cached on disk).

---

## 8. Moderation (Moderator/Admin)

### GET /api/moderation/queue

List flagged items pending review.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| type | string | Filter: review, listing, all (default) |
| flag_reason | string | Filter by flag reason |
| sort | string | newest (default), lowest_credibility |
| page, per_page | int | Pagination |

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "item_type": "review",
        "item_id": 42,
        "flag_reason": "sensitive_word",
        "flag_details": "Matched word: 'xxx' in review text",
        "credibility_score": 0.35,
        "content_preview": "This ride was xxx...",
        "user": { "id": 5, "name": "User Five", "account_age_days": 3 },
        "flagged_at": "2026-04-08T12:00:00Z"
      }
    ],
    "meta": { "total": 15, "page": 1, "per_page": 20, "last_page": 1 }
  }
}
```

### POST /api/moderation/queue/{id}/approve

Approve a flagged item (publish it).

### POST /api/moderation/queue/{id}/reject

Reject a flagged item.

**Request:**
```json
{
  "reason": "string, required, max 500 chars"
}
```

### POST /api/moderation/queue/{id}/escalate

Escalate to Administrator (Moderator action).

---

## 9. Data Governance (Admin)

### GET /api/governance/quality-metrics

Get data quality metrics.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| from_date | date | Start date (default: 30 days ago) |
| to_date | date | End date (default: today) |

**Success (200):**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "metrics": [
      {
        "metric_date": "2026-04-07",
        "metric_name": "listing_completeness",
        "metric_value": 0.87,
        "details": { "total_listings": 200, "complete_listings": 174 }
      }
    ]
  }
}
```

### GET /api/governance/lineage

Get data lineage records.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| job_name | string | Filter by job |
| run_id | string | Filter by run ID |
| from_date | date | Start date |
| to_date | date | End date |

### GET /api/governance/events

Query behavior events (admin only, paginated).

---

## 10. Audit Logs (Admin)

### GET /api/audit/logs

Query audit logs.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| user_id | int | Filter by acting user |
| action | string | Filter by action type |
| resource_type | string | Filter by resource type |
| from_date | datetime | Start |
| to_date | datetime | End |
| page, per_page | int | Pagination |
| unmask | boolean | Request unmasked PII (requires audit.read_unmasked permission) |

---

## 11. Organization Settings (Admin)

### GET /api/org/settings

Get current organization settings.

### PUT /api/org/settings

Update organization settings.

**Request:**
```json
{
  "name": "string, optional, 2-200 chars",
  "hotlink_allowed_domains": "string, optional, comma-separated domains",
  "moderation_duplicate_threshold": "float, optional, 0.0-1.0",
  "media_watermark_enabled": "boolean, optional",
  "media_url_expiry_minutes": "integer, optional, 1-1440"
}
```

---

## 12. User Management (Admin)

### GET /api/users

List organization users.

### GET /api/users/{id}

Get user detail.

### PUT /api/users/{id}/roles

Assign roles to a user.

**Request:**
```json
{
  "roles": ["user", "moderator"]
}
```

### POST /api/users/{id}/disable

Disable a user account.

### POST /api/users/{id}/enable

Re-enable a disabled user account.
