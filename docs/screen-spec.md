# RideCircle Carpool Marketplace -- Screen Specification

> All screens are implemented in `repo/frontend/js/pages/`. Each screen corresponds to a hash route in the SPA. The shell is `repo/frontend/index.html`, with shared modules in `repo/frontend/js/` (api.js, auth.js, util.js, router.js) and custom styles in `repo/frontend/css/app.css`.

## Global Layout

### Navigation Bar
- **Left**: Logo + "RideCircle" text (local image asset)
- **Center**: Role-dependent navigation links
- **Right**: User name dropdown (Profile, API Tokens, Logout)

### Role-Specific Navigation

| Link | User | Moderator | Admin |
|------|------|-----------|-------|
| Dashboard | Y | Y | Y |
| Listings | Y | Y | Y |
| My Listings | Y | Y | Y |
| Orders | Y | Y | Y |
| Reviews | Y | Y | Y |
| Moderation | - | Y | Y |
| Users | - | - | Y |
| Org Settings | - | - | Y |
| Governance | - | - | Y |
| Audit Logs | - | - | Y |

### Visual Hierarchy
- Primary actions: Layui `layui-btn-normal` (blue)
- Destructive actions: Layui `layui-btn-danger` (red)
- Secondary actions: Layui `layui-btn-primary` (outlined)
- Status badges: colored labels (green=active, blue=matched, yellow=in_progress, gray=completed, red=canceled, orange=disputed)

---

## Screen 1: Login

**URL**: /login

**Sections:**
1. Centered card with RideCircle logo (local asset)
2. Form: email input, password input, "Log In" button
3. Link: "Don't have an account? Register"

**Empty/Loading States:**
- Loading: "Log In" button shows spinner, disabled during request
- Error: inline red message below form ("Invalid email or password")

---

## Screen 2: Register

**URL**: /register

**Sections:**
1. Centered card with logo
2. Form: name, email, password, confirm password, organization invite code
3. Password strength indicator (client-side)
4. "Register" button
5. Link: "Already have an account? Log in"

**Validation:**
- Inline field validation on blur
- Server errors mapped to specific fields

---

## Screen 3: Dashboard

**URL**: /

**Sections:**
1. Welcome message with user name
2. Quick stats cards:
   - Regular User: active listings count, pending orders, completed trips
   - Moderator: + pending moderation queue count
   - Admin: + total users, data quality summary score
3. Recent activity feed (last 5 items: new listings, order updates, reviews)
4. Quick action buttons: "Post a Trip Request", "Browse Listings"

**Empty State:**
- New user with no activity: "Welcome to RideCircle! Start by posting your first trip request or browsing available rides."

---

## Screen 4: Listing Browse

**URL**: /listings

**Sections:**
1. **Search bar** (top, prominent):
   - Text input with placeholder "Search rides by keyword..."
   - As user types: dropdown shows keyword suggestions (from /api/search/suggestions)
   - On submit with no results: "Did you mean: [suggestion]?" prompt
   - Below search bar: recent search terms (stored in localStorage, max 10)
2. **Filter panel** (left sidebar or collapsible):
   - Vehicle type: checkboxes (sedan, SUV, van)
   - Rider count: range slider or min/max inputs (1-6)
   - Status: dropdown (active by default for non-admin)
3. **Sort controls**: tabs or dropdown (Newest, Most Discussed, Most Popular)
4. **Results list**: cards showing title, addresses, rider count, vehicle type, time window, status badge, match highlights (`<em>` tags rendered as yellow background)
5. **Pagination**: Layui pagination component

**Empty State:**
- No results for search query: "No rides found for '[query]'. Here are some recently active listings:" followed by recently active listing cards.
- No listings at all: "No trip requests yet. Be the first to post one!" with "Post a Trip Request" button.

**Loading State:**
- Skeleton cards (gray placeholders) while API request is in progress.

---

## Screen 5: Listing Detail

**URL**: /listings/{id}

**Sections:**
1. **Header**: Title, status badge, "by [user name]", posted date
2. **Details card**:
   - Pickup address
   - Drop-off address
   - Rider count
   - Vehicle type
   - Baggage notes
   - Time window (formatted 12-hour clock)
   - Tags (as label badges)
3. **Action panel** (right side or bottom):
   - For other users (driver role): "Accept This Ride" button (only if status=active)
   - For owner: "Edit", "Publish"/"Unpublish" toggle, "Delete" (if draft)
   - For moderator: "Flag", "Unpublish"
4. **Version history link**: "View version history (v{n})" -- links to version history page
5. **Associated order summary** (if matched/in_progress): mini order status card with link to full order
6. **Reviews section** (if completed): list of reviews for orders on this listing

**Blocked Actions:**
- "Accept This Ride" disabled with tooltip: "This listing is no longer accepting drivers" (if not active)
- "Delete" disabled: "You can only delete draft listings"

---

## Screen 6: Listing Create

**URL**: /listings/create

**Sections:**
1. **Form** (Layui form):
   - Title: text input, 5-200 chars, required
   - Description: textarea, max 2000 chars
   - Pickup address: text input, required
   - Drop-off address: text input, required
   - Rider count: number stepper 1-6, required
   - Vehicle type: radio buttons (Sedan / SUV / Van), required
   - Baggage notes: textarea, max 500 chars
   - Time window start: date+time picker (12-hour format), required
   - Time window end: date+time picker (12-hour format), required, validated > start
   - Tags: tag input (comma-separated or chip UI), max 10
2. **Action buttons**:
   - "Save Draft" (primary outlined)
   - "Publish" (primary filled)

**Validation:**
- Client-side: required fields, char limits, time window logic
- Server-side errors mapped to fields inline

---

## Screen 7: Listing Edit

**URL**: /listings/{id}/edit

Same layout as Create, pre-populated with current values.

**Additional section:**
- **Change preview panel**: Before re-publishing, show a diff summary: "You changed: pickup address, time window. Review changes before publishing." with "View Full Diff" link opening a modal.

---

## Screen 8: Listing Version History

**URL**: /listings/{id}/versions

**Sections:**
1. **Version timeline**: vertical timeline with version number, timestamp, author, change summary
2. **Diff viewer**: select any two versions via checkboxes, click "Compare" to see field-level diff (old value in red, new value in green)
3. Current published version highlighted

---

## Screen 9: My Listings

**URL**: /my/listings

**Sections:**
1. **Status filter tabs**: All, Draft, Active, Matched, In Progress, Completed, Canceled
2. **Listing table**: title, status badge, created date, last updated, actions
3. **Bulk actions** (Admin only): checkbox selection + "Close Selected" button

**Empty State:**
- "You haven't posted any trip requests yet." with "Post a Trip Request" button.

---

## Screen 10: Order List

**URL**: /orders

**Sections:**
1. **Role filter**: "As Passenger" / "As Driver" / "All" tabs
2. **Status filter**: dropdown
3. **Order cards**: listing title, other party name, status badge, key timestamps, action button
4. **Pagination**

**Empty State:**
- "No orders yet. Browse listings to find a ride or post your own trip request."

---

## Screen 11: Order Detail

**URL**: /orders/{id}

**Sections:**
1. **Status header**: Large status badge with human-readable label and timestamp
2. **Lifecycle progress bar**: visual step indicator (pending_match > accepted > in_progress > completed) with current step highlighted
3. **Parties card**: Passenger info, Driver info
4. **Listing summary**: link to full listing
5. **Action panel**:
   - Dynamic buttons based on `allowed_transitions` from API
   - Each button shows confirmation dialog
   - Blocked actions show inline message explaining why
6. **Cancellation section** (if applicable):
   - Within free window: "Cancel (free)" button
   - Past free window: "Cancel" button opens form with reason code dropdown and optional text
   - In progress: "Cancel" button disabled with message: "Cancellation is not allowed once a trip is in progress."
7. **Dispute section** (if completed):
   - Within 72 hours: "Open Dispute" button with reason textarea
   - Past 72 hours: "Dispute window has closed" message
8. **Resolution section** (if disputed, Admin view):
   - Outcome dropdown + resolution text
9. **Review link**: After completion, "Leave a Review" button (if no review yet)

**Blocked-Action Messages:**
| State | Blocked Action | Message |
|-------|---------------|---------|
| in_progress | Cancel | "Cancellation is not allowed once a trip is in progress." |
| completed (>72h) | Dispute | "The dispute window (72 hours) has closed for this trip." |
| pending_match | Start | "The trip cannot be started until the passenger accepts the match." |
| canceled | Any action | "This order has been canceled. No further actions are available." |
| expired | Any action | "This match request expired. The listing has been returned to active status." |

---

## Screen 12: Review Create

**URL**: /orders/{id}/review

**Sections:**
1. **Order summary**: brief card showing the trip that is being reviewed
2. **Rating**: clickable star widget (1-5), required
3. **Review text**: textarea, 1-1000 chars, character counter shown
4. **Media upload**:
   - Drag-and-drop zone or file picker
   - File preview (thumbnails for photos, video player for videos)
   - File size shown per file; red warning if over limit
   - Counter: "2 of 5 files uploaded"
   - Accept: jpeg, png, gif, webp (photos up to 5 MB), mp4, webm (videos up to 50 MB)
5. **Submit button**

**Blocked States:**
- Rate limited: "You've submitted 3 reviews in the last hour. Please wait [time remaining] before submitting another."
- Duplicate detected (after submit): "This review appears to be similar to one you recently submitted. It has been sent for review."

---

## Screen 13: Reviews List

**URL**: /reviews

**Sections:**
1. Filter by listing or user
2. Review cards: star display, text excerpt, media thumbnails, author, date, credibility indicator (not numeric -- just "verified" badge for score > 0.7)
3. Pagination

---

## Screen 14: Profile

**URL**: /profile

**Sections:**
1. Profile info: name, email, organization, roles, member since
2. Edit form: name (email is read-only)
3. Stats: total trips (as passenger/driver), average rating received

---

## Screen 15: Moderation Queue

**URL**: /moderation

**Sections:**
1. **Filter bar**: type (review/listing/all), flag reason dropdown
2. **Sort**: newest, lowest credibility score
3. **Queue table**: item type icon, content preview (truncated), flag reason badge, credibility score, user name, account age, flagged timestamp
4. **Actions per row**: Approve (green), Reject (red), Escalate (orange) -- confirm dialog on each

**Empty State:**
- "The moderation queue is empty. All content has been reviewed."

---

## Screen 16: Moderation Detail

**URL**: /moderation/{id}

**Sections:**
1. Full content display (review text, media previews, or listing details)
2. Flag information: reason, matched words (highlighted in content), detection method
3. User profile card: name, account age, completion rate, recent review count
4. Credibility score breakdown: age factor, completion factor, pattern factor, total
5. Action buttons: Approve, Reject (with reason textarea), Escalate

---

## Screen 17: Admin -- User Management

**URL**: /admin/users

**Sections:**
1. User table: name, email, roles (as badges), status (active/disabled), registered date
2. Search/filter by name or email
3. Row actions: Edit Roles (modal with checkbox list), Disable/Enable toggle
4. Pagination

---

## Screen 18: Admin -- Organization Settings

**URL**: /admin/settings

**Sections:**
1. Organization name
2. Hotlink allowed domains (comma-separated input)
3. Moderation settings: duplicate threshold slider, watermark toggle
4. Media settings: URL expiry duration, max file sizes
5. Save button

---

## Screen 19: Admin -- Governance Dashboard

**URL**: /admin/governance

**Sections:**
1. **Date range picker** (default: last 30 days)
2. **Metric cards**: one per metric (listing completeness, dedup ratio, missing value rate, counter drift, queue depth, stale listing rate)
3. **Time-series charts**: line charts for each metric over the selected period
4. **Credibility distribution**: histogram chart
5. **Last job run status**: table showing each nightly job, last run time, status, records processed

**Empty State:**
- Before first nightly run: "Data quality metrics will appear after the first nightly governance job runs."

---

## Screen 20: Admin -- Governance Lineage

**URL**: /admin/governance/lineage

**Sections:**
1. Filter by job name, date range
2. Run list: run_id, job name, execution time, total input/output/removed counts
3. Expandable rows: per-step detail (step name, input count, output count, removed count)

---

## Screen 21: Admin -- Audit Logs

**URL**: /admin/audit

**Sections:**
1. **Filter panel**: user (autocomplete), action type, resource type, date range
2. **PII toggle**: "Show unmasked data" switch (only visible to users with audit.read_unmasked)
3. **Log table**: timestamp, user (masked/unmasked), action, resource, IP (masked/unmasked)
4. **Row expand**: shows old_value / new_value JSON diff
5. Pagination

---

## Screen 22: API Tokens

**URL**: /profile/tokens

**Sections:**
1. **Token list**: name, created date, last used, expires at, status
2. **Create button**: opens modal (name input, expiry selection)
3. **Token display**: after creation, modal shows plaintext token with "Copy" button and warning "This token will not be shown again"
4. **Row actions**: Rotate (confirm dialog, shows new token), Revoke (confirm dialog)

**Empty State:**
- "No API tokens. Create one to access the RideCircle API programmatically."
