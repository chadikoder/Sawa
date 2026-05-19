# Sawa — Lebanese Charity Platform

> Connecting donors with families in need — transparently and directly, with zero intermediaries.

## 🎯 Project Overview

Sawa is a **full-stack charity platform** built entirely from scratch, designed to connect Lebanese donors with families in need. The platform emphasizes:

- **Direct connection:** No intermediaries between donors and recipients
- **Transparency:** Real-time donation tracking and wallet management
- **Trust:** Organization verification, audit trails, and accountability
- **Accessibility:** Arabic & English support, mobile-responsive design

---

## 📋 Features

### For Donors
- Browse verified campaigns by category & location
- Donate securely with transaction history
- Save campaigns for later
- Receive notifications about campaign updates
- Comment & engage with creators

### For Organizations
- Create & manage fundraising campaigns
- Upload images & post progress updates
- Track donations in real-time
- Manage digital wallet & transactions
- Build trust with transparency

### For Admin
- Verify organizations & campaigns
- Monitor platform activity (audit logs)
- Moderate content & handle reports
- Track all admin actions

---

## 🏗️ Architecture

### Database (18 tables)
- **Users & Auth (5):** users, organisations, password_resets, email_verifications, login_attempts
- **Campaigns (4):** campaigns, campaign_images, campaign_updates, saved_campaigns
- **Money (3):** donations, wallet_transactions, donation_status_history
- **Engagement (3):** notifications, comments, reports
- **Lookups (2):** categories, locations
- **Admin (1):** audit_log

### Tech Stack
- **Frontend:** HTML, CSS, JavaScript (vanilla, no frameworks)
- **Backend:** [Your backend tech]
- **Database:** PostgreSQL
- **Authentication:** JWT-based with email verification

---

## 📂 Project Structure

```
sawa/
├── access.html             # Access control page
├── css/                    # Stylesheets
│   ├── tokens.css         # Design system (colors, spacing, typography)
│   ├── nav.css
│   ├── login.css
│   └── ...
├── js/                     # JavaScript logic
│   ├── index.js           # Main app logic
│   └── ...
├── images/                 # SVG & asset files
├── explanation/            # Project documentation
└── DEVELOPMENT_LOG.md     # Detailed development timeline
```

---

## 🔐 Security

- JWT-based authentication with refresh token rotation
- Email verification for account creation
- Password reset with secure tokens
- Role-based access control (User, Organisation, Admin)
- Brute-force protection via login attempt tracking
- Complete audit trail of all admin actions

---

## 📊 Database Design

The schema is normalized and properly indexed with:
- Foreign key constraints for referential integrity
- Status tracking for donations (pending → verified → completed)
- Audit logs for compliance & accountability
- Multi-currency wallet support

See [DEVELOPMENT_LOG.md](./DEVELOPMENT_LOG.md) for detailed architecture rationale.

---

## 📈 Development Timeline

| Phase | Period | Focus |
|-------|--------|-------|
| Phase 1 | Jan 2026 | Database Design & ER Diagram |
| Phase 2 | Jan-Feb | Auth System & Authorization |
| Phase 3 | Feb | Campaign Management |
| Phase 4 | Feb-Mar | Donation & Wallet System |
| Phase 5 | Mar | Community & Engagement |
| Phase 6 | Mar-Apr | Admin & Moderation Tools |
| Phase 7 | May | UI/UX Redesign & Polish |

For detailed timeline with specific features, see [DEVELOPMENT_LOG.md](./DEVELOPMENT_LOG.md).

---

## 👤 Author

**Built by:** Chadi Ikhoder  
**Email:** chadikhoder571@gmail.com  
**GitHub:** https://github.com/chadikoder/Sawa  
**Built:** January – May 2026 | Entirely from scratch

This project represents **original work** with all architecture, design, and implementation decisions made by the author.

---

## 📜 License

**PolyForm Noncommercial License 1.0.0** (with bilingual English/Arabic summary)

> Copyright (c) 2026 Chadi Ikhoder. **All Rights Reserved.**
> Original work created entirely from scratch by Chadi Ikhoder
> between January 2026 and May 2026.

### What you CAN do

- ✅ Read and study the code
- ✅ Use it for personal study, learning, or research
- ✅ Use it for hobby or academic projects
- ✅ Use by non-profit educational, charitable, or research organizations

### What you CANNOT do

- ❌ **Sell this software, in whole or in part**
- ❌ **Use this software for any commercial purpose**
- ❌ Incorporate it into a paid product, paid service, or any
     revenue-generating activity
- ❌ Remove or alter the copyright notice or author attribution
- ❌ Claim authorship of this work or any substantial portion

For commercial licensing inquiries, contact **chadikhoder571@gmail.com**.

See [`LICENSE`](./LICENSE) for the full legal text (English + Arabic summary)
and [`NOTICE.md`](./NOTICE.md) for the proof-of-authorship declaration.

---

## 🛠️ Setup & Deployment

[Add your setup instructions here]

---

## 📝 Contributing

Since this is an original student project, contributions are not currently accepted. 
However, you can fork this repository to learn from it (with attribution to the original author).

---

## ❓ Questions?

For questions about the project, architecture, or usage:
- Email: chadikhoder571@gmail.com
- GitHub Issues: [Project Issues]

---

**Last Updated:** May 18, 2026  
**Status:** ✅ Complete & Deployed
