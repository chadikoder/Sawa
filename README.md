<div align="center">

# Sawa

**A Lebanese charity platform — connecting donors with families in need, transparently and directly.**

[![Made by Chadi Khoder](https://img.shields.io/badge/made_by-Chadi_Khoder-2563eb?style=for-the-badge)](https://github.com/chadikoder)
[![PHP](https://img.shields.io/badge/PHP-8.x-2563eb?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-2563eb?style=for-the-badge&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![License](https://img.shields.io/badge/license-PolyForm_NC-2563eb?style=for-the-badge)](#license)

[**Open the platform →**](https://www.sawa-together.com)

</div>

---

## What is this

A full-stack donation platform. Verified organisations list campaigns; donors give directly; admins keep the system honest. No intermediaries between donor and recipient.

```
18  · database tables
3   · roles (donor / organisation / admin)
2   · languages (Arabic + English)
1   · wallet system with audit trail
```

## Features

**Donors**
- Browse verified campaigns by category & location
- Donate securely with full transaction history
- Save campaigns, follow updates, comment
- Notifications for campaign milestones

**Organisations**
- Create and manage fundraising campaigns
- Upload images, post progress updates
- Real-time donation tracking
- Manage a digital wallet

**Admins**
- Verify organisations and campaigns
- Audit log of all admin actions
- Content moderation and report handling

## Architecture

**Database (18 tables)**

| Domain | Tables |
|---|---|
| Users & Auth | `users`, `organisations`, `password_resets`, `email_verifications`, `login_attempts` |
| Campaigns | `campaigns`, `campaign_images`, `campaign_updates`, `saved_campaigns` |
| Money | `donations`, `wallet_transactions`, `donation_status_history` |
| Engagement | `notifications`, `comments`, `reports` |
| Lookups | `categories`, `locations` |
| Admin | `audit_log` |

## Security

- JWT auth with refresh-token rotation
- Email verification on signup
- Password reset via secure single-use tokens
- Role-based access control (user / organisation / admin)
- Brute-force protection (login-attempt tracking)
- Full audit trail of admin actions

## Tech stack

| | |
|---|---|
| Frontend | HTML5, CSS3, vanilla JavaScript |
| Backend | PHP 8.x |
| Database | PostgreSQL |
| Auth | JWT + email verification |
| Hosting | sawa-together.com |

## Quick start

```bash
git clone https://github.com/chadikoder/Sawa.git
cd Sawa
# Configure your PHP + PostgreSQL environment, then serve index.html.
```

## Project structure

```
Sawa/
├── index.html
├── access.html
├── css/                  ← design system + per-page styles
│   ├── tokens.css        ← colors, spacing, typography
│   └── ...
├── js/                   ← page logic (vanilla)
├── pages/                ← about, login, signup, userhome, etc.
├── php/                  ← backend endpoints
└── images/               ← SVG + assets
```

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
