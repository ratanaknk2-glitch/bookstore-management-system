# Installation Guide — Book Store Management System

Follow these steps in order. This guide assumes **XAMPP** on Windows/Mac/Linux, but the same steps apply to WAMP or a native LAMP stack — only the folder paths differ.

---

## Step 1 — Install a local server stack

1. Download and install **XAMPP** (includes Apache, PHP, MySQL, phpMyAdmin): https://www.apachefriends.org
2. Open the **XAMPP Control Panel** and click **Start** next to both **Apache** and **MySQL**.

---

## Step 2 — Place the project files

1. Locate your XAMPP `htdocs` folder (Windows: `C:\xampp\htdocs`, Mac: `/Applications/XAMPP/htdocs`, Linux: `/opt/lampp/htdocs`).
2. Copy the entire `bookstore-management-system` folder into `htdocs`, so you end up with:
   ```
   htdocs/bookstore-management-system/backend/...
   htdocs/bookstore-management-system/frontend/...
   htdocs/bookstore-management-system/database/...
   ```

---

## Step 3 — Create the database with phpMyAdmin

1. Open your browser and go to `http://localhost/phpmyadmin`.
2. Click **Import** in the top menu.
3. Click **Choose File** and select `database/bookstore_db.sql` from the project.
4. Leave all other options at their defaults and click **Go**.
5. You should now see a new database called **`bookstore_db`** in the left sidebar, with tables like `users`, `books`, `sales`, etc. already created, plus starter data (roles, categories, membership levels, settings).

> If your MySQL uses a different port or is not on `localhost`, note it down — you'll need it in the next step.

---

## Step 4 — Configure database credentials

1. In `backend/config/`, find `database.local.example.php`.
2. Make a **copy** of it in the same folder and rename the copy to `database.local.php`.
3. Open `database.local.php` and edit the values to match your MySQL setup. Default XAMPP values are usually:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'bookstore_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');   // XAMPP's default root password is empty
   ```
4. Save the file. `database.local.php` is already listed in `.gitignore`, so it will **never** be pushed to GitHub — this keeps your real credentials private even in a public repo.

---

## Step 5 — Set the admin password

The database seed does **not** ship a working password (shipping a real password hash in source control is bad practice, even for a "default" account).

1. Go to `http://localhost/bookstore-management-system/backend/setup_admin.php`.
2. Enter a strong password (min 8 characters, 1 uppercase letter, 1 number) and confirm it.
3. Submit. The page will confirm success and **delete itself automatically** — this endpoint cannot be reused, so you're safe even if you forget to remove it manually.

---

## Step 6 — Log in

1. Go to `http://localhost/bookstore-management-system/frontend/login.html`.
2. Username: `admin`, password: whatever you set in Step 5.
3. You should land on the Dashboard.

Create additional users directly in phpMyAdmin under the `users` table for now (an in-app "Manage Users" screen can be added later) — just make sure to generate password hashes with PHP's `password_hash()`, never store plaintext.

---

## Step 7 — Verify everything works

Quick checklist:
- [ ] Dashboard loads with stats (all zero on a fresh install — that's expected).
- [ ] Books → Add Book → fill the form → Save → book appears in the list.
- [ ] Books → Edit a book → upload a cover image → cover shows in the table.
- [ ] Categories/Authors/Publishers/Suppliers → Add/Edit/Delete work.
- [ ] Customers → Register a customer.
- [ ] Sales (POS) → search a book → add to cart → Process Payment → receipt window opens.
- [ ] Reports → pick a report type → Generate → Export Excel (CSV) downloads a file.
- [ ] Settings → Create Backup Now → a `.sql` file appears in the list.

---

## Step 8 — Push to GitHub

1. Open a terminal in the project's root folder (`bookstore-management-system/`).
2. Initialize git and make the first commit:
   ```bash
   git init
   git add .
   git commit -m "Initial commit: Book Store Management System"
   ```
3. Create a new, empty repository on GitHub (do **not** initialize it with a README, since you already have one).
4. Connect and push:
   ```bash
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
   git push -u origin main
   ```
5. Double-check on GitHub.com that `backend/config/database.local.php` and `backend/uploads/book_covers/*` (your real images) are **not** in the repo — only the `.example.php` file and `.gitkeep` placeholders should show up. This is handled automatically by `.gitignore`, but it's worth a quick visual check the first time.

**When a teammate clones the repo**, they simply repeat Steps 3–5 (import the SQL, create their own `database.local.php`, run `setup_admin.php`) — nothing sensitive ever needs to be shared over Slack/email.

---

## Step 9 — Deploying to a live host (shared hosting / VPS)

1. Upload all files the same way (FTP, SSH, or a GitHub Actions deploy step).
2. Create a MySQL database through your host's control panel (e.g. cPanel → MySQL Databases) and import `database/bookstore_db.sql` through their phpMyAdmin.
3. Create `backend/config/database.local.php` on the server with your live DB credentials (never commit this file, even for production).
4. Run `setup_admin.php` once on the live domain, exactly like Step 5.
5. In `backend/config/config.php`, change:
   ```php
   define('APP_ENV', 'production');
   // and uncomment this line once you have a valid SSL certificate:
   // ini_set('session.cookie_secure', '1');
   ```
6. In `frontend/assets/js/api.js`, update `API_BASE` if your backend is not served from the same relative path as the frontend.
7. Uncomment the HTTPS-redirect block at the top of the root `.htaccess` once SSL is active.

---

## Troubleshooting

| Problem | Likely Cause | Fix |
|---|---|---|
| "Missing backend/config/database.local.php" | Step 4 skipped | Copy the example file and fill in credentials |
| Login always fails | Admin password never set | Run `backend/setup_admin.php` |
| 401 errors on every page after login | Cookies blocked / different origin | Make sure frontend and backend are served from the same domain, or adjust `ALLOWED_ORIGIN` in `config.php` and CORS headers |
| Book cover doesn't show | Upload folder not writable | Ensure `backend/uploads/book_covers/` has write permission (`chmod 755` on Linux) |
| CSV export downloads empty file | No report generated yet | Click "Generate" before "Export Excel" |
