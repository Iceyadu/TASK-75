# RideCircle Carpool Marketplace -- Business and Engineering Ambiguities

---

## Q1: Multi-Organization vs Single-Organization Deployment

Question:
The prompt says "private organization or community" but does not specify whether a single deployment serves one organization or multiple organizations with data isolation.

My Understanding:
The offline-first, local-network constraint strongly implies a single deployment per organization. However, the mention of "organization" as a data entity (with org_id) suggests the schema should support multi-tenancy even if deployment is single-org.

Solution:
Design the database schema with `organization_id` on all tenant-scoped tables to support potential multi-org deployment, but the default deployment targets a single organization. The frontend will not expose org-switching UI. This preserves flexibility without adding complexity to the user experience.

---

## Q2: Driver vs Passenger Role -- Same User or Separate Accounts?

Question:
The prompt describes "Regular Users" who post trip requests (passenger behavior) and "Drivers" who browse and accept (driver behavior). It is unclear whether these are separate account types or the same user acting in different capacities.

My Understanding:
In a private community carpool, the same person often drives on some days and rides on others. Forcing separate accounts would be burdensome and unnatural.

Solution:
A single user account can act as both passenger and driver. The "Driver" mentioned in the prompt is a behavioral role, not a system role. Any Regular User can post a listing (passenger) or accept a listing (driver). RBAC roles (user, moderator, admin) are separate from this behavioral distinction.

---

## Q3: Pending Match -- Who Initiates and Who Accepts?

Question:
The prompt says "After a driver accepts, the system generates an order" with initial status "pending match." This creates ambiguity: if the driver already accepted, why is the order still "pending match"? What triggers the transition to "accepted"?

My Understanding:
There is a two-step matching flow: (1) a driver expresses interest, creating an order in "pending_match" state, and (2) the passenger confirms/accepts the match, moving the order to "accepted." The 30-minute auto-expiry applies to this pending_match window.

Solution:
Implement a two-step match: driver clicks "Accept" to create an order (pending_match), then the passenger reviews the driver and confirms (accepted). If the passenger does not confirm within 30 minutes, the order expires and the listing returns to active. This interpretation gives both parties agency while honoring the stated lifecycle.

---

## Q4: "Most Discussed" and "Most Popular" Sort Criteria

Question:
The prompt specifies sort options "newest, most discussed, or most popular" but does not define what constitutes "discussed" or "popular."

My Understanding:
In a carpool context without a comment/discussion feature explicitly described, "discussed" likely maps to engagement signals and "popular" to interest signals.

Solution:
Define "most discussed" as sorted by `comment_count` (comments on a listing, to be modeled as a lightweight comment feature on listings) and "most popular" as sorted by `favorite_count + view_count` (combined interest score). If comments are deemed out of scope, "most discussed" can fall back to `view_count` (most viewed).

---

## Q5: Text Similarity Threshold for Duplicate Detection

Question:
The prompt requires "duplicate detection via text similarity" but does not specify the similarity algorithm or threshold.

My Understanding:
The offline constraint rules out ML-based models or external NLP services. A simple, deterministic approach is needed.

Solution:
Use trigram Jaccard similarity (character-level n-grams) with a configurable threshold (default 0.85). This is implementable in pure PHP with no external dependencies. The threshold is exposed in `.env` for tuning. Flagged items go to manual review rather than auto-rejection, reducing false-positive impact.

---

## Q6: Credibility Score -- Absolute Thresholds and Weight Tuning

Question:
The prompt describes credibility score components (account age, completion history, abnormal patterns) but does not specify exact thresholds for auto-flagging or how weights should be balanced.

My Understanding:
These are product decisions that require empirical tuning after launch. Hardcoding thresholds would make them difficult to adjust.

Solution:
All weights and thresholds are configurable via `.env` with documented defaults (age weight: 0.3, completion weight: 0.4, pattern weight: 0.3; auto-flag threshold: score < 0.3). The nightly recomputation job ensures scores adapt as user history changes. Administrators can view the score distribution on the governance dashboard.

---

## Q7: Watermark -- Original File Retention

Question:
The prompt says "optional watermark overlays on images" but does not specify whether the original (unwatermarked) version should be retained.

My Understanding:
In an offline deployment, disk space is a real constraint. Storing both versions doubles photo storage. However, losing the original means watermarks are irreversible.

Solution:
Store only the watermarked version by default to conserve disk space. Document this as a deployment decision. If an organization needs originals, add a configuration flag (`MEDIA_RETAIN_ORIGINALS=true`) that stores both, with the original in a separate subdirectory.

---

## Q8: Did-You-Mean -- Dictionary Source and Update Frequency

Question:
The prompt requires a "did you mean" prompt for common typos but the offline constraint prohibits external spell-check services. The dictionary source is unspecified.

My Understanding:
The dictionary must be derived from the platform's own content (listing titles, descriptions, tags) since external word lists may not cover community-specific terminology.

Solution:
Build the did-you-mean dictionary from listing content. A nightly job extracts unique words (frequency >= 2) from active listing titles, descriptions, and tags, and writes them to an in-memory dictionary loaded at application startup. Typo correction uses Levenshtein distance (edit distance <= 2) against this dictionary.

---

## Q9: Behavior Event Volume and Storage

Question:
The prompt requires capturing browse/click/favorite/rate events. In an active community this could generate significant data volume. Retention period and storage limits are not specified.

My Understanding:
Offline deployments have finite disk space. Unbounded event storage is unsustainable.

Solution:
Implement a configurable retention period (default: 90 days) for behavior events. The nightly deduplication job also purges events older than the retention period. Aggregated metrics derived from events are retained indefinitely (they are small). This is documented so Administrators can adjust the retention period.

---

## Q10: Dispute Resolution Workflow

Question:
The prompt states disputes "must be resolved by an Administrator" but does not describe what resolution looks like beyond the state transition.

My Understanding:
A dispute resolution needs an outcome classification and a written explanation for both parties.

Solution:
Resolution requires: (1) an outcome enum (passenger_favor, driver_favor, mutual, dismissed) and (2) a resolution text (max 2000 chars). Both parties can view the resolution. The resolution is immutable once submitted. No appeals process is defined (if needed, it would be a new dispute -- but this is not in scope).
