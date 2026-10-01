-- ============================================================
-- BOOK STORE MANAGEMENT SYSTEM - COMBINED DATABASE SCHEMA
-- Structure only (no transactional data dumps).
-- Safe to run on a fresh DB or re-run on an existing one:
--   - All tables use CREATE TABLE IF NOT EXISTS
--   - All seed rows use INSERT IGNORE (won't duplicate/overwrite)
--   - Views use CREATE OR REPLACE (won't error if they exist)
-- Import via phpMyAdmin > Import, or:
--   mysql -u root -p < bookstore_db_schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS bookstore_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bookstore_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. ROLES / USERS / LOGIN LOGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (role_id, role_name) VALUES
  (1, 'Admin'), (2, 'Manager'), (3, 'Cashier');

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30),
  role_id INT NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  profile_image VARCHAR(255) DEFAULT NULL,
  failed_login_attempts INT DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin placeholder. Password hash is intentionally NOT a real
-- credential here — run your setup/first-run script once to set a real
-- password hash, then that script should delete or disable itself.
INSERT IGNORE INTO users (username, email, password_hash, full_name, role_id, status)
VALUES ('admin', 'admin@bookstore.local', 'NOT_SET_RUN_SETUP_SCRIPT', 'System Administrator', 1, 'active');

CREATE TABLE IF NOT EXISTS login_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  username_attempt VARCHAR(50),
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  status ENUM('success','failed') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. CATEGORY / AUTHOR / PUBLISHER
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (category_name, description) VALUES
('Fiction','Fictional literature'),
('Non-Fiction','Non-fictional literature'),
('Science','Science books'),
('Technology','Technology and computing'),
('Business','Business and finance'),
('Education','Educational and academic'),
('Children''s Books','Books for children'),
('Comics','Comics and graphic novels'),
('Novels','Novels');

CREATE TABLE IF NOT EXISTS authors (
  author_id INT AUTO_INCREMENT PRIMARY KEY,
  author_name VARCHAR(150) NOT NULL,
  biography TEXT,
  nationality VARCHAR(80),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publishers (
  publisher_id INT AUTO_INCREMENT PRIMARY KEY,
  publisher_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30),
  email VARCHAR(100),
  address VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. BOOKS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
  book_id INT AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(30) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  author_id INT,
  publisher_id INT,
  category_id INT,
  language VARCHAR(50) DEFAULT 'English',
  edition VARCHAR(50),
  publication_year YEAR,
  purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  quantity INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 5,
  shelf_location VARCHAR(50),
  cover_image VARCHAR(255) DEFAULT NULL,
  status ENUM('Available','Out of Stock','Discontinued') DEFAULT 'Available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES authors(author_id) ON DELETE SET NULL,
  FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
  INDEX idx_title (title),
  INDEX idx_isbn (isbn),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. SUPPLIERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
  supplier_id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(120),
  phone VARCHAR(30),
  email VARCHAR(100),
  address VARCHAR(255),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. CUSTOMERS / MEMBERSHIP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membership_levels (
  membership_id INT AUTO_INCREMENT PRIMARY KEY,
  level_name VARCHAR(50) NOT NULL UNIQUE,
  discount_percent DECIMAL(5,2) DEFAULT 0,
  min_spend DECIMAL(10,2) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO membership_levels (membership_id, level_name, discount_percent, min_spend) VALUES
(1, 'Bronze', 0, 0), (2, 'Silver', 5, 100), (3, 'Gold', 10, 300);

CREATE TABLE IF NOT EXISTS customers (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30),
  email VARCHAR(100),
  address VARCHAR(255),
  membership_id INT DEFAULT 1,
  membership_expiry DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (membership_id) REFERENCES membership_levels(membership_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. PURCHASES (from suppliers)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_orders (
  purchase_id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(30) NOT NULL UNIQUE,
  supplier_id INT NOT NULL,
  order_date DATE NOT NULL,
  expected_date DATE,
  status ENUM('Pending','Received','Cancelled') DEFAULT 'Pending',
  total_amount DECIMAL(12,2) DEFAULT 0,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT NOT NULL,
  book_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_cost DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (purchase_id) REFERENCES purchase_orders(purchase_id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. DISCOUNTS / COUPONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS discounts (
  discount_id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type ENUM('percentage','fixed') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  start_date DATE,
  end_date DATE,
  usage_limit INT DEFAULT NULL,
  used_count INT DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. SALES (POS)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
  sale_id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT DEFAULT NULL,
  cashier_id INT NOT NULL,
  discount_id INT DEFAULT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('Cash','Card','Mobile') DEFAULT 'Cash',
  amount_paid DECIMAL(12,2) DEFAULT 0,
  change_due DECIMAL(12,2) DEFAULT 0,
  status ENUM('Completed','Cancelled','Refunded') DEFAULT 'Completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
  FOREIGN KEY (cashier_id) REFERENCES users(user_id),
  FOREIGN KEY (discount_id) REFERENCES discounts(discount_id) ON DELETE SET NULL,
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
  sale_item_id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  book_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_returns (
  return_id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  book_id INT NOT NULL,
  quantity INT NOT NULL,
  reason VARCHAR(255),
  refund_amount DECIMAL(10,2) NOT NULL,
  processed_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(sale_id),
  FOREIGN KEY (book_id) REFERENCES books(book_id),
  FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. INVENTORY MOVEMENTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  type ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
  quantity INT NOT NULL,
  reference VARCHAR(100),
  notes VARCHAR(255),
  performed_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id) REFERENCES books(book_id),
  FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. EMPLOYEES / ATTENDANCE / SCHEDULE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
  employee_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  full_name VARCHAR(150) NOT NULL,
  position VARCHAR(100),
  phone VARCHAR(30),
  email VARCHAR(100),
  hire_date DATE,
  salary DECIMAL(10,2) DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
  attendance_id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  work_date DATE NOT NULL,
  time_in TIME,
  time_out TIME,
  status ENUM('Present','Absent','Leave') DEFAULT 'Present',
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_schedule (
  schedule_id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  shift_day VARCHAR(20) NOT NULL,
  shift_start TIME NOT NULL,
  shift_end TIME NOT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('Low Stock','New Arrival','Purchase Reminder','Promotion','Membership Expiry') NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  user_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. SETTINGS
-- (includes the store_logo key from your migration file, plus
--  receipt_footer_message which your live DB already has)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('store_name', 'My Book Store'),
('store_logo', ''),
('store_address', ''),
('store_phone', ''),
('store_email', ''),
('tax_percent', '0'),
('currency_symbol', '$'),
('currency_code', 'USD'),
('low_stock_threshold', '5'),
('receipt_footer_message', 'Thank you for your purchase!');

-- ------------------------------------------------------------
-- 13. VIEWS
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW view_low_stock AS
SELECT book_id, title, isbn, quantity, reorder_level
FROM books
WHERE quantity <= reorder_level AND status <> 'Discontinued';

CREATE OR REPLACE VIEW view_best_sellers AS
SELECT b.book_id, b.title, SUM(si.quantity) AS total_sold
FROM sale_items si
JOIN books b ON b.book_id = si.book_id
JOIN sales s ON s.sale_id = si.sale_id AND s.status = 'Completed'
GROUP BY b.book_id, b.title
ORDER BY total_sold DESC;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SCHEMA
-- ============================================================