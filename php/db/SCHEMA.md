# Sawa — Database Schema Reference

Visual reference for the 18 PostgreSQL tables. Use this while writing the
`CREATE TABLE` statements in `01_auth.sql` → `06_admin.sql`.

**Legend:**
`PK` = primary key &nbsp;·&nbsp; `FK` = foreign key &nbsp;·&nbsp; `UQ` = unique &nbsp;·&nbsp; `NN` = NOT NULL

---

## 1. Overview — the 18 tables by domain

```
   AUTH (01_auth.sql) ─── 5 tables          CAMPAIGNS (02_campaigns.sql) ─── 4 tables
   ┌─────────────────────────┐              ┌─────────────────────────────┐
   │  users          (1)     │              │  campaigns         (1)      │
   │  organisations  (2)     │──────┐       │  campaign_images   (2)      │
   │  password_resets        │      │       │  campaign_updates  (3)      │
   │  email_verifications    │      │       │  saved_campaigns   (4)      │
   │  login_attempts         │      │       └─────────────────────────────┘
   └─────────────────────────┘      │                  ▲
            ▲                       │                  │
            │                       └──────────────────┤
            │                                          │
   LOOKUPS (05_lookups.sql) ─── 2 tables               │
   ┌─────────────────────────┐                         │
   │  categories             │─────────────────────────┤
   │  locations              │─────────────────────────┘
   └─────────────────────────┘

   MONEY (03_donations.sql) ─── 3 tables    ENGAGEMENT (04_engagement.sql) ─── 3 tables
   ┌─────────────────────────┐              ┌─────────────────────────────┐
   │  donations              │◄────┐        │  notifications              │
   │  wallet_transactions    │─────┘        │  comments                   │
   │  donation_status_history│              │  reports  (polymorphic)     │
   └─────────────────────────┘              └─────────────────────────────┘

   ADMIN (06_admin.sql) ─── 1 table
   ┌─────────────────────────┐
   │  audit_log  (append-only)│
   └─────────────────────────┘
```

**Load order:** `01_auth → 05_lookups → 02_campaigns → 03_donations → 04_engagement → 06_admin → seed`
(`02_campaigns` has FKs into `05_lookups`, so lookups must exist first.)

---

## 2. AUTH domain — `01_auth.sql`

### `users`
The base table for donors, organisation owners, and admins.

| Column         | Type          | Key/Constraint            | Notes |
|----------------|---------------|---------------------------|-------|
| id             | BIGSERIAL     | **PK**                    |       |
| email          | VARCHAR(255)  | **UQ**, NN                |       |
| password_hash  | VARCHAR(255)  | NN                        | bcrypt |
| full_name      | VARCHAR(120)  | NN                        |       |
| phone          | VARCHAR(40)   |                           | optional |
| role           | VARCHAR(20)   | NN, CHECK                 | `'user'` \| `'organisation'` \| `'admin'` |
| email_verified | BOOLEAN       | NN, default FALSE         |       |
| active         | BOOLEAN       | NN, default TRUE          | soft-disable via admin |
| created_at     | TIMESTAMPTZ   | NN, default NOW()         |       |
| updated_at     | TIMESTAMPTZ   | NN, default NOW()         |       |

Indexes: `(role)`, `(email)` where `active = TRUE`.

### `organisations`
One row per organisation. Linked **1:1** to a user with `role = 'organisation'`.

| Column            | Type          | Key/Constraint                          | Notes |
|-------------------|---------------|-----------------------------------------|-------|
| id                | BIGSERIAL     | **PK**                                  |       |
| user_id           | BIGINT        | **FK → users.id**, UQ, NN, ON DELETE CASCADE |  |
| name              | VARCHAR(200)  | NN                                      |       |
| description       | TEXT          |                                         |       |
| phone             | VARCHAR(40)   |                                         |       |
| address           | VARCHAR(255)  |                                         |       |
| website           | VARCHAR(255)  |                                         |       |
| logo_path         | VARCHAR(500)  |                                         |       |
| registration_doc  | VARCHAR(500)  |                                         | proof-of-registration upload |
| verified          | BOOLEAN       | NN, default FALSE                       |       |
| verified_at       | TIMESTAMPTZ   |                                         |       |
| verified_by       | BIGINT        | **FK → users.id**, ON DELETE SET NULL   | admin who approved |
| rejected          | BOOLEAN       | NN, default FALSE                       |       |
| rejection_reason  | TEXT          |                                         |       |
| created_at        | TIMESTAMPTZ   | NN, default NOW()                       |       |
| updated_at        | TIMESTAMPTZ   | NN, default NOW()                       |       |

Index: `(verified)`.

### `password_resets`

| Column     | Type          | Key/Constraint                          | Notes |
|------------|---------------|-----------------------------------------|-------|
| id         | BIGSERIAL     | **PK**                                  |       |
| user_id    | BIGINT        | **FK → users.id**, NN, ON DELETE CASCADE |      |
| token      | VARCHAR(128)  | UQ, NN                                  | url-safe random |
| expires_at | TIMESTAMPTZ   | NN                                      | typically NOW() + 1h |
| used       | BOOLEAN       | NN, default FALSE                       |       |
| created_at | TIMESTAMPTZ   | NN, default NOW()                       |       |

Index: `(user_id)`.

### `email_verifications`
Same shape as `password_resets` but with a longer TTL (48h).

| Column     | Type          | Key/Constraint                          |
|------------|---------------|-----------------------------------------|
| id         | BIGSERIAL     | **PK**                                  |
| user_id    | BIGINT        | **FK → users.id**, NN, ON DELETE CASCADE |
| token      | VARCHAR(128)  | UQ, NN                                  |
| expires_at | TIMESTAMPTZ   | NN                                      |
| used       | BOOLEAN       | NN, default FALSE                       |
| created_at | TIMESTAMPTZ   | NN, default NOW()                       |

### `login_attempts`
Append-only log used for brute-force throttling. No FK — keeps history even if user is deleted.

| Column       | Type          | Key/Constraint    | Notes |
|--------------|---------------|-------------------|-------|
| id           | BIGSERIAL     | **PK**            |       |
| email        | VARCHAR(255)  | NN                |       |
| ip_address   | VARCHAR(45)   | NN                | IPv6 fits |
| success      | BOOLEAN       | NN                |       |
| user_agent   | VARCHAR(500)  |                   |       |
| attempted_at | TIMESTAMPTZ   | NN, default NOW() |       |

Indexes: `(email, attempted_at DESC)`, `(ip_address, attempted_at DESC)`.

---

## 3. LOOKUPS domain — `05_lookups.sql`

> Run this BEFORE `02_campaigns.sql` (campaigns FK into these tables).

### `categories`

| Column     | Type          | Key/Constraint    | Notes |
|------------|---------------|-------------------|-------|
| id         | BIGSERIAL     | **PK**            |       |
| slug       | VARCHAR(60)   | UQ, NN            | `'medical'`, `'education'`, ... |
| name_en    | VARCHAR(120)  | NN                |       |
| name_ar    | VARCHAR(120)  | NN                |       |
| icon       | VARCHAR(120)  |                   | filename in `/images/` |
| sort_order | INTEGER       | NN, default 0     |       |
| active     | BOOLEAN       | NN, default TRUE  |       |

### `locations`
Lebanese governorates / cities.

| Column     | Type          | Key/Constraint    | Notes |
|------------|---------------|-------------------|-------|
| id         | BIGSERIAL     | **PK**            |       |
| slug       | VARCHAR(60)   | UQ, NN            | `'beirut'`, `'tripoli'`, ... |
| name_en    | VARCHAR(120)  | NN                |       |
| name_ar    | VARCHAR(120)  | NN                |       |
| region     | VARCHAR(80)   |                   | governorate (`Beirut`, `North`, ...) |
| sort_order | INTEGER       | NN, default 0     |       |
| active     | BOOLEAN       | NN, default TRUE  |       |

---

## 4. CAMPAIGNS domain — `02_campaigns.sql`

### `campaigns`

| Column            | Type          | Key/Constraint                                | Notes |
|-------------------|---------------|-----------------------------------------------|-------|
| id                | BIGSERIAL     | **PK**                                        |       |
| organisation_id   | BIGINT        | **FK → organisations.id**, NN, ON DELETE CASCADE | |
| title             | VARCHAR(200)  | NN                                            |       |
| summary           | VARCHAR(500)  |                                               | short tagline |
| description       | TEXT          | NN                                            |       |
| goal_amount       | NUMERIC(12,2) | NN, CHECK > 0                                 |       |
| raised_amount     | NUMERIC(12,2) | NN, default 0, CHECK >= 0                     | updated when donation verified |
| currency          | VARCHAR(3)    | NN, default `'USD'`                           |       |
| category_id       | BIGINT        | **FK → categories.id**, ON DELETE SET NULL    |       |
| location_id       | BIGINT        | **FK → locations.id**, ON DELETE SET NULL     |       |
| cover_image       | VARCHAR(500)  |                                               |       |
| status            | VARCHAR(20)   | NN, default `'draft'`, CHECK                  | `'draft'` \| `'pending'` \| `'active'` \| `'paused'` \| `'completed'` \| `'rejected'` |
| rejection_reason  | TEXT          |                                               |       |
| starts_at         | TIMESTAMPTZ   |                                               |       |
| ends_at           | TIMESTAMPTZ   |                                               |       |
| created_at        | TIMESTAMPTZ   | NN, default NOW()                             |       |
| updated_at        | TIMESTAMPTZ   | NN, default NOW()                             |       |

Indexes: `(status)`, `(category_id)`, `(location_id)`, `(organisation_id)`,
and a partial `(created_at DESC) WHERE status = 'active'` for the home feed.

### `campaign_images`

| Column      | Type          | Key/Constraint                                 | Notes |
|-------------|---------------|------------------------------------------------|-------|
| id          | BIGSERIAL     | **PK**                                         |       |
| campaign_id | BIGINT        | **FK → campaigns.id**, NN, ON DELETE CASCADE   |       |
| image_path  | VARCHAR(500)  | NN                                             |       |
| caption     | VARCHAR(255)  |                                                |       |
| sort_order  | INTEGER       | NN, default 0                                  |       |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                              |       |

Index: `(campaign_id, sort_order)`.

### `campaign_updates`
Progress posts by the organisation.

| Column      | Type          | Key/Constraint                                 | Notes |
|-------------|---------------|------------------------------------------------|-------|
| id          | BIGSERIAL     | **PK**                                         |       |
| campaign_id | BIGINT        | **FK → campaigns.id**, NN, ON DELETE CASCADE   |       |
| posted_by   | BIGINT        | **FK → users.id**, NN, ON DELETE SET NULL      |       |
| title       | VARCHAR(200)  | NN                                             |       |
| body        | TEXT          | NN                                             |       |
| image_path  | VARCHAR(500)  |                                                |       |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                              |       |

Index: `(campaign_id, created_at DESC)`.

### `saved_campaigns`
Donor bookmarks. UNIQUE pair to prevent duplicates.

| Column      | Type          | Key/Constraint                                 |
|-------------|---------------|------------------------------------------------|
| id          | BIGSERIAL     | **PK**                                         |
| user_id     | BIGINT        | **FK → users.id**, NN, ON DELETE CASCADE       |
| campaign_id | BIGINT        | **FK → campaigns.id**, NN, ON DELETE CASCADE   |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                              |

`UNIQUE (user_id, campaign_id)`. Index: `(user_id)`.

---

## 5. MONEY domain — `03_donations.sql`

### `donations`

| Column       | Type          | Key/Constraint                                  | Notes |
|--------------|---------------|-------------------------------------------------|-------|
| id           | BIGSERIAL     | **PK**                                          |       |
| campaign_id  | BIGINT        | **FK → campaigns.id**, NN, ON DELETE RESTRICT   | donations are immutable |
| donor_id     | BIGINT        | **FK → users.id**, NN, ON DELETE RESTRICT       |       |
| amount       | NUMERIC(12,2) | NN, CHECK > 0                                   |       |
| currency     | VARCHAR(3)    | NN, default `'USD'`                             |       |
| status       | VARCHAR(20)   | NN, default `'pending'`, CHECK                  | `'pending'` \| `'verified'` \| `'completed'` \| `'refunded'` \| `'failed'` |
| anonymous    | BOOLEAN       | NN, default FALSE                               |       |
| message      | VARCHAR(500)  |                                                 |       |
| payment_ref  | VARCHAR(120)  |                                                 | external gateway reference |
| created_at   | TIMESTAMPTZ   | NN, default NOW()                               |       |
| verified_at  | TIMESTAMPTZ   |                                                 |       |
| completed_at | TIMESTAMPTZ   |                                                 |       |

Indexes: `(campaign_id)`, `(donor_id)`, `(status)`, `(created_at DESC)`.

**Status flow:** `pending → verified → completed`, with side branches `pending|verified → refunded` and `pending → failed`.

### `wallet_transactions`
Per-organisation ledger. Credit on `donation verified`, debit on `withdrawal`, adjustment on `refund`.

| Column              | Type          | Key/Constraint                                  | Notes |
|---------------------|---------------|-------------------------------------------------|-------|
| id                  | BIGSERIAL     | **PK**                                          |       |
| organisation_id     | BIGINT        | **FK → organisations.id**, NN, ON DELETE CASCADE |  |
| type                | VARCHAR(20)   | NN, CHECK                                       | `'credit'` \| `'debit'` \| `'withdrawal'` \| `'adjustment'` |
| amount              | NUMERIC(12,2) | NN, CHECK > 0                                   |       |
| currency            | VARCHAR(3)    | NN, default `'USD'`                             |       |
| balance_after       | NUMERIC(12,2) | NN                                              | running balance — cheap reads |
| related_donation_id | BIGINT        | **FK → donations.id**, ON DELETE SET NULL       |       |
| description         | VARCHAR(255)  |                                                 |       |
| created_by          | BIGINT        | **FK → users.id**, ON DELETE SET NULL           | admin who verified |
| created_at          | TIMESTAMPTZ   | NN, default NOW()                               |       |

Indexes: `(organisation_id, created_at DESC)`, `(related_donation_id)`.

### `donation_status_history`
One row per status change. Read-only audit trail.

| Column      | Type          | Key/Constraint                                 | Notes |
|-------------|---------------|------------------------------------------------|-------|
| id          | BIGSERIAL     | **PK**                                         |       |
| donation_id | BIGINT        | **FK → donations.id**, NN, ON DELETE CASCADE   |       |
| from_status | VARCHAR(20)   |                                                | NULL on first row |
| to_status   | VARCHAR(20)   | NN                                             |       |
| changed_by  | BIGINT        | **FK → users.id**, ON DELETE SET NULL          |       |
| notes       | VARCHAR(500)  |                                                |       |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                              |       |

Index: `(donation_id, created_at)`.

---

## 6. ENGAGEMENT domain — `04_engagement.sql`

### `notifications`

| Column     | Type          | Key/Constraint                                | Notes |
|------------|---------------|-----------------------------------------------|-------|
| id         | BIGSERIAL     | **PK**                                        |       |
| user_id    | BIGINT        | **FK → users.id**, NN, ON DELETE CASCADE      |       |
| type       | VARCHAR(40)   | NN                                            | `'donation_received'`, `'campaign_update'`, ... |
| title      | VARCHAR(200)  | NN                                            |       |
| body       | TEXT          |                                               |       |
| link       | VARCHAR(500)  |                                               | optional in-app deep link |
| is_read    | BOOLEAN       | NN, default FALSE                             |       |
| created_at | TIMESTAMPTZ   | NN, default NOW()                             |       |

Indexes: `(user_id, created_at DESC)`, partial `(user_id, created_at DESC) WHERE is_read = FALSE`.

### `comments`
Soft-deletable for moderation.

| Column      | Type          | Key/Constraint                                 |
|-------------|---------------|------------------------------------------------|
| id          | BIGSERIAL     | **PK**                                         |
| campaign_id | BIGINT        | **FK → campaigns.id**, NN, ON DELETE CASCADE   |
| user_id     | BIGINT        | **FK → users.id**, NN, ON DELETE CASCADE       |
| body        | TEXT          | NN                                             |
| deleted_at  | TIMESTAMPTZ   |                                                |
| deleted_by  | BIGINT        | **FK → users.id**, ON DELETE SET NULL          |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                              |

Index: `(campaign_id, created_at DESC)`. Filter out `deleted_at IS NOT NULL` in queries.

### `reports`
Polymorphic — `target_id` points into `campaigns` / `comments` / `organisations` / `users`
depending on `target_type`. No real FK on `target_id` (polymorphic).

| Column      | Type          | Key/Constraint                                 | Notes |
|-------------|---------------|------------------------------------------------|-------|
| id          | BIGSERIAL     | **PK**                                         |       |
| reporter_id | BIGINT        | **FK → users.id**, NN, ON DELETE SET NULL      |       |
| target_type | VARCHAR(20)   | NN, CHECK                                      | `'campaign'` \| `'comment'` \| `'organisation'` \| `'user'` |
| target_id   | BIGINT        | NN                                             | NO FK — polymorphic |
| reason      | VARCHAR(80)   | NN                                             | `'fraud'`, `'spam'`, `'abuse'`, ... |
| details     | TEXT          |                                                |       |
| status      | VARCHAR(20)   | NN, default `'open'`, CHECK                    | `'open'` \| `'reviewing'` \| `'resolved'` \| `'dismissed'` |
| resolved_by | BIGINT        | **FK → users.id**, ON DELETE SET NULL          |       |
| resolved_at | TIMESTAMPTZ   |                                                |       |
| resolution  | TEXT          |                                                |       |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                              |       |

Indexes: `(status, created_at DESC)`, `(target_type, target_id)`.

---

## 7. ADMIN domain — `06_admin.sql`

### `audit_log`
**Append-only.** Never UPDATE or DELETE rows.

| Column      | Type          | Key/Constraint                                | Notes |
|-------------|---------------|-----------------------------------------------|-------|
| id          | BIGSERIAL     | **PK**                                        |       |
| admin_id    | BIGINT        | **FK → users.id**, NN, ON DELETE SET NULL     |       |
| action      | VARCHAR(80)   | NN                                            | `'verify_organisation'`, `'reject_campaign'`, ... |
| target_type | VARCHAR(40)   | NN                                            | `'organisation'`, `'campaign'`, ... |
| target_id   | BIGINT        |                                               | nullable for actions without a target |
| details     | JSONB         |                                               | arbitrary structured context |
| ip_address  | VARCHAR(45)   |                                               |       |
| user_agent  | VARCHAR(500)  |                                               |       |
| created_at  | TIMESTAMPTZ   | NN, default NOW()                             |       |

Indexes: `(admin_id, created_at DESC)`, `(target_type, target_id)`, `(action, created_at DESC)`.

---

## 8. Foreign-key map (all 22 FKs in one place)

| From                                  | To                  | ON DELETE | Why |
|---------------------------------------|---------------------|-----------|-----|
| organisations.user_id                 | users.id            | CASCADE   | delete user → delete their org |
| organisations.verified_by             | users.id            | SET NULL  | keep org even if approving admin is gone |
| password_resets.user_id               | users.id            | CASCADE   |     |
| email_verifications.user_id           | users.id            | CASCADE   |     |
| campaigns.organisation_id             | organisations.id    | CASCADE   |     |
| campaigns.category_id                 | categories.id       | SET NULL  | keep campaign even if category removed |
| campaigns.location_id                 | locations.id        | SET NULL  |     |
| campaign_images.campaign_id           | campaigns.id        | CASCADE   |     |
| campaign_updates.campaign_id          | campaigns.id        | CASCADE   |     |
| campaign_updates.posted_by            | users.id            | SET NULL  |     |
| saved_campaigns.user_id               | users.id            | CASCADE   |     |
| saved_campaigns.campaign_id           | campaigns.id        | CASCADE   |     |
| donations.campaign_id                 | campaigns.id        | **RESTRICT** | donations are immutable financial records |
| donations.donor_id                    | users.id            | **RESTRICT** | same — keep the donor reference |
| wallet_transactions.organisation_id   | organisations.id    | CASCADE   |     |
| wallet_transactions.related_donation_id | donations.id      | SET NULL  |     |
| wallet_transactions.created_by        | users.id            | SET NULL  |     |
| donation_status_history.donation_id   | donations.id        | CASCADE   |     |
| donation_status_history.changed_by    | users.id            | SET NULL  |     |
| notifications.user_id                 | users.id            | CASCADE   |     |
| comments.campaign_id                  | campaigns.id        | CASCADE   |     |
| comments.user_id                      | users.id            | CASCADE   |     |
| comments.deleted_by                   | users.id            | SET NULL  |     |
| reports.reporter_id                   | users.id            | SET NULL  |     |
| reports.resolved_by                   | users.id            | SET NULL  |     |
| audit_log.admin_id                    | users.id            | SET NULL  |     |

---

## 9. CHECK constraints recap

| Table         | Column   | Allowed values |
|---------------|----------|----------------|
| users         | role     | `'user'`, `'organisation'`, `'admin'` |
| campaigns     | status   | `'draft'`, `'pending'`, `'active'`, `'paused'`, `'completed'`, `'rejected'` |
| campaigns     | goal_amount   | `> 0`     |
| campaigns     | raised_amount | `>= 0`    |
| donations     | status   | `'pending'`, `'verified'`, `'completed'`, `'refunded'`, `'failed'` |
| donations     | amount   | `> 0`     |
| wallet_transactions | type | `'credit'`, `'debit'`, `'withdrawal'`, `'adjustment'` |
| wallet_transactions | amount | `> 0`   |
| reports       | target_type | `'campaign'`, `'comment'`, `'organisation'`, `'user'` |
| reports       | status   | `'open'`, `'reviewing'`, `'resolved'`, `'dismissed'` |

---

## 10. PostgreSQL types used (quick cheat-sheet)

| Type          | Use for |
|---------------|---------|
| `BIGSERIAL`   | auto-incrementing 64-bit PK |
| `BIGINT`      | foreign keys to BIGSERIAL columns |
| `VARCHAR(n)`  | short text with a hard max |
| `TEXT`        | unbounded text (descriptions, bodies) |
| `BOOLEAN`     | true/false flags |
| `NUMERIC(12,2)` | money (12 digits total, 2 after decimal) |
| `TIMESTAMPTZ` | timestamp **with** time zone — always use this for `created_at`, `expires_at`, etc. |
| `JSONB`       | binary JSON (audit_log.details) |

> Use `CREATE TABLE IF NOT EXISTS ...` and `CREATE INDEX IF NOT EXISTS ...` so the files can be re-run safely.

> If your editor flags `BIGSERIAL`, `TIMESTAMPTZ`, `JSONB`, `IF NOT EXISTS`, or `ON CONFLICT` as syntax errors — it has the SQL dialect set to SQL Server / T-SQL. Switch the dialect to PostgreSQL.
