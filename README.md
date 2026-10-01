# 📚 Book Store Management System

A complete, self-hosted Book Store Management System built with **vanilla JavaScript**, **PHP**, and **MySQL**. No frameworks, no build tools, no Node.js required — clone it, point it at MySQL, and it runs.

---

## 1. Tech Stack

| Layer      | Technology |
|------------|------------|
| Frontend   | HTML5, CSS3 (flat, light-blue theme), Vanilla JavaScript (no framework, no build step) |
| Backend    | PHP 8+ (procedural, PDO with prepared statements) |
| Database   | MySQL / MariaDB (managed via phpMyAdmin) |
| Hosting    | Any Apache/PHP host (XAMPP, WAMP, LAMP, shared hosting) + GitHub for source control |

---

## 2. Project Structure

```
bookstore-management-system/
│
├── backend/                     # Everything PHP / server-side
│   ├── api/                     # REST-style API endpoints, one folder per module
│   │   ├── auth/                 login.php, logout.php, session.php, change_password.php, profile.php
│   │   ├── books/                index.php (CRUD+search), upload_cover.php
│   │   ├── categories/           index.php (CRUD)
│   │   ├── authors/              index.php (CRUD)
│   │   ├── publishers/           index.php (CRUD)
│   │   ├── suppliers/            index.php (CRUD)
│   │   ├── customers/            index.php (CRUD + purchase history)
│   │   ├── purchases/            index.php (create/receive/cancel purchase orders)
│   │   ├── sales/                index.php (POS checkout, cancel/refund)
│   │   ├── inventory/            index.php (stock in/out/adjustment + history)
│   │   ├── discounts/            index.php (CRUD coupons/promotions)
│   │   ├── employees/            index.php (CRUD + attendance)
│   │   ├── reports/               index.php (all report types)
│   │   ├── settings/             index.php (store settings), backup.php (backup/restore)
│   │   ├── notifications/        index.php
│   │   └── dashboard/            index.php (stats + chart data)
│   ├── config/
│   │   ├── config.php                    global app config, session hardening
│   │   ├── database.php                  PDO connection
│   │   └── database.local.example.php    COPY this to database.local.php
│   ├── includes/
│   │   ├── bootstrap.php         included by every endpoint
│   │   └── security.php          CSRF, sanitization, rate limiting, auth guards
│   ├── uploads/book_covers/      uploaded book cover images
│   ├── backups/                  generated DB backups (.sql)
│   └── setup_admin.php           ONE-TIME script to set the admin password
│
├── frontend/                    # Everything static / client-side
│   ├── login.html
│   ├── dashboard.html
│   ├── books.html, categories.html, authors.html, publishers.html, suppliers.html
│   ├── customers.html, purchases.html, sales.html, inventory.html
│   ├── discounts.html, employees.html, reports.html, settings.html
│   ├── notifications.html, profile.html, index.html
│   └── assets/
│       ├── css/style.css         flat design, light-blue theme, responsive
│       ├── js/api.js             central fetch wrapper (CSRF + error handling)
│       ├── js/common.js          sidebar/topbar layout + auth guard + modal helper
│       ├── js/toast.js           toast notifications
│       └── js/simple_crud.js     generic CRUD engine for simple modules
│
├── database/
│   └── bookstore_db.sql          full schema + seed data — import via phpMyAdmin
│
├── docs/
│   └── INSTALLATION.md           detailed setup walkthrough
│
├── .gitignore
└── README.md                    (this file)
```

**Why this structure?** Every backend module (`books`, `sales`, `customers`, ...) has its own folder with a single `index.php` that handles GET/POST/PUT/DELETE — so a developer can open one file and see 100% of that feature's server logic. The frontend mirrors this 1:1 — one HTML page per module, each loading the same three shared JS files.

---

## 3. Features Implemented

- ✅ Authentication (login/logout, change password, profile, Role-Based Access Control: Admin / Manager / Cashier)
- ✅ Dashboard (stats cards, 7-day sales chart, best sellers, low stock alerts)
- ✅ Book Management (full CRUD, search, cover image upload, ISBN uniqueness)
- ✅ Category / Author / Publisher / Supplier Management (CRUD + search)
- ✅ Customer Management (CRUD, membership levels, purchase history)
- ✅ Purchase Orders (create → receive → auto stock-in, or cancel)
- ✅ Sales / POS (cart, discount codes, tax, payment, receipt printing, cancel/restore stock)
- ✅ Inventory (stock in/out/adjustment, low stock alerts, movement history)
- ✅ Discounts & Coupons (percentage/fixed, usage limits, date ranges)
- ✅ Employee Management (CRUD, attendance table in DB)
- ✅ Reports (10 report types: inventory, sales, purchase, customer, supplier, author, revenue, profit & loss, low stock, best sellers) with **Print** and **Export to CSV/Excel**
- ✅ Notifications (low stock, membership expiry — live-generated)
- ✅ Settings (store info, tax %, currency, **database backup & restore**)

---

## 4. Security Measures (see also `docs/SECURITY.md` notes below)

- **SQL Injection**: 100% PDO prepared statements, no string-concatenated queries anywhere.
- **XSS**: All output escaped on the frontend via `escapeHtml()`; server strips tags on input.
- **CSRF**: A per-session token is required as an `X-CSRF-Token` header on every POST/PUT/DELETE.
- **Password storage**: bcrypt via PHP's `password_hash()` / `password_verify()`. No plaintext, no reversible encryption, ever.
- **Brute-force protection**: failed logins are counted; account locks for 15 minutes after 5 failed attempts. All login attempts are logged.
- **Rate limiting**: IP-based limiter on the login endpoint.
- **Session hardening**: HttpOnly + SameSite cookies, session ID regeneration on login, idle timeout (30 min).
- **File upload security**: real MIME-type checks (not just file extension), image-content verification, random filenames, execution blocked in the uploads folder via `.htaccess`.
- **Secrets never committed**: `database.local.php` (real DB credentials) is git-ignored; the repo only ships an example file.
- **No hardcoded admin password**: the seed SQL creates the `admin` account with an unusable placeholder; you set the real password once via `backend/setup_admin.php`, which deletes itself after use.
- **Security headers**: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Content-Security-Policy`, restricted CORS.
- **Sensitive folders blocked**: `.htaccess` files deny direct web access to `config/`, `includes/`, `backups/`.

---

## 5. Step-by-Step Setup

See **`docs/INSTALLATION.md`** for the full walkthrough (local XAMPP/WAMP setup, phpMyAdmin import, GitHub push). Quick version:

1. Install XAMPP (or WAMP/LAMP) and start Apache + MySQL.
2. Copy this whole folder into `htdocs/bookstore-management-system/`.
3. Open phpMyAdmin → Import → select `database/bookstore_db.sql` → Go.
4. Copy `backend/config/database.local.example.php` → `backend/config/database.local.php` and set your DB credentials.
5. Visit `http://localhost/bookstore-management-system/backend/setup_admin.php` and set the admin password.
6. Visit `http://localhost/bookstore-management-system/frontend/login.html` and log in as `admin`.
7. Push to GitHub (credentials are already git-ignored — see step 6 in `docs/INSTALLATION.md`).

---

## 6. Performance Notes

- Indexes on `books.title`, `books.isbn`, `books.status`, `sales.created_at` for fast search/reporting.
- Paginated book list API (`per_page`, default 20) instead of loading the whole catalog at once.
- No frontend framework/bundler = zero build step and minimal JS payload (~15KB total, uncompressed).
- Row-level locking (`SELECT ... FOR UPDATE`) during checkout prevents overselling under concurrent sales without slowing down normal reads.
- Static assets (CSS/JS) can be cached by the browser/CDN indefinitely since there's no build hash — bump filenames manually if you ever need to force a refresh after an update.

---

## 7. License

Free to use and modify for your own bookstore.
