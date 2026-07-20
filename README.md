<div align="center">

# Sawa

**A Lebanese charity platform — connecting donors with families in need, transparently and directly.**

[![Made by Chadi Khoder](https://img.shields.io/badge/made_by-Chadi_Khoder-2563eb?style=for-the-badge)](https://github.com/chadikoder)
[![PHP](https://img.shields.io/badge/PHP-8.x-2563eb?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL_/_MariaDB-2563eb?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/license-PolyForm_NC-2563eb?style=for-the-badge)](#license)

</div>

---

## What is this

A full-stack donation platform. Verified organisations list campaigns; donors give directly; admins keep the system honest. No intermediaries between donor and recipient.

```
25  · database tables
4   · roles (donor / beneficiary / organisation / admin)
2   · fee tiers (5% members, 10% guests — shown before checkout)
1   · wallet system with a full ledger per user and per organisation
```

## Features

**Donors**
- Browse verified campaigns by category and location
- Donate as a member or as a guest, with the platform fee shown before payment
- Wallet with a running balance and a transaction ledger
- Donation history, receipts, and status tracking
- Save campaigns, comment, and receive notifications

**Organisations**
- Create and manage fundraising campaigns
- Upload images and post progress updates
- Live donation totals per campaign
- Organisation wallet with a credit ledger and cash-out requests

**Admins**
- Verify organisations, review and moderate campaigns
- Handle user reports, suspend accounts
- Approve cash-outs
- Audit log of admin actions

## Architecture

**Database (25 tables)**

| Domain | Tables |
|---|---|
| Users & Auth | `users`, `user_profiles`, `organisations`, `password_resets`, `email_verifications`, `login_attempts` |
| Campaigns | `campaigns`, `campaign_images`, `campaign_updates`, `saved_campaigns` |
| Money | `donations`, `donation_status_history`, `wallet_transactions`, `user_wallet_ledger`, `cash_out_requests`, `receipts`, `payment_sessions` |
| Engagement | `notifications`, `comments`, `reports` |
| Messaging | `message_threads`, `messages` |
| Lookups | `categories`, `locations` |
| Admin | `audit_log` |

## Security

- **Server-side sessions** with ID regeneration on login and on account switch
- **CSRF tokens** on every state-changing endpoint, compared with `hash_equals`
- **Prepared statements** throughout — no string-built SQL
- **bcrypt** password hashing via `password_hash()`
- **Brute-force protection** — failed logins tracked in `login_attempts`
- **Role-based access control** — admin endpoints re-read the role from the
  database rather than trusting the session, so a demoted admin loses access
  immediately
- **Email verification** on signup, and single-use expiring password-reset tokens
- **Atomic money operations** — a wallet donation debits the donor, completes the
  donation and credits the organisation inside one transaction, so a failure
  part-way through refunds rather than losing the money
- Apache hardening via `.htaccess` (denies `.env`, the SQL dump and host config;
  sets `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)

## Tech stack

| | |
|---|---|
| Frontend | HTML5, CSS3, vanilla JavaScript |
| Backend | PHP 8.x (no framework) |
| Database | MySQL 8 / MariaDB 10.4 |
| Auth | PHP sessions + email verification |

## Quick start

Requires PHP 8.x and MySQL 8 or MariaDB 10.4.

```bash
git clone https://github.com/chadikoder/Sawa.git
cd Sawa
```

**1. Create the database and a user**

```sql
CREATE DATABASE sawa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sawa'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON sawa.* TO 'sawa'@'localhost';
```

**2. Import the schema and demo data**

`database_complete.sql` contains the full schema plus seed data, and is
idempotent — re-importing will not duplicate anything. It deliberately omits
`CREATE DATABASE`/`USE` so it also works on shared hosting with prefixed
database names.

```bash
mysql -u sawa sawa < database_complete.sql
```

Or in phpMyAdmin: select the `sawa` database → **Import** → choose the file → **Go**.

**3. Configure (optional)**

Defaults are `127.0.0.1:3306`, database `sawa`, user `sawa`, empty password.
To override, copy `.env.example` to `.env` and edit:

```
SAWA_DB_HOST=127.0.0.1
SAWA_DB_PORT=3306
SAWA_DB_NAME=sawa
SAWA_DB_USER=sawa
SAWA_DB_PASS=
```

**4. Run**

```bash
php -S localhost:8000 router.php
```

Then open <http://localhost:8000>.

### Demo logins

Seeded by `database_complete.sql`. **These are demo credentials — change them
before deploying anywhere real.**

| Role | Email | Password |
|---|---|---|
| Admin | `admin@sawa.local` | `Admin123` |
| Donor | `donor@sawa.local` | `Demo123!` |
| Beneficiary | `beneficiary@sawa.local` | `Demo123!` |
| Organisation | `org@sawa.local` | `Demo123!` |

## Project structure

```
Sawa/
├── index.html
├── router.php            ← dev-server router
├── database_complete.sql ← schema + seed data
├── css/                  ← design system + per-page styles
│   ├── tokens.css        ← colors, spacing, typography
│   └── ...
├── js/                   ← page logic (vanilla)
├── pages/                ← login, signup, userhome, admin, about, errors
├── php/
│   ├── lib/              ← Auth, Csrf, services (donations, wallet, campaigns)
│   ├── auth/             ← login, signup, verification, password reset
│   ├── admin/            ← admin actions
│   └── config/           ← config + PDO connection
└── images/
```

## Not implemented yet

Listed honestly rather than claimed as features:

- Arabic UI (the language switch is present but marked *Coming soon*; the
  interface is English only)
- Two-factor auth and connected-device management (shown as *Coming soon*)
- Payments run through a simulated provider flow, not a live payment gateway

## Author

<div align="center">

<a href="https://github.com/chadikoder">
  <img src="https://github.com/chadikoder.png" width="110" alt="Chadi Khoder" />
</a>

### Chadi Khoder

[![GitHub](https://img.shields.io/badge/@chadikoder-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/chadikoder)

</div>

## License

**PolyForm Noncommercial License 1.0.0** — Copyright © 2026 Chadi Ikhoder. All rights reserved.

You may read, study, and use this for personal, educational, and non-commercial purposes. You may **not** sell it or use it for any commercial purpose. See [`LICENSE`](./LICENSE) for the full text.
