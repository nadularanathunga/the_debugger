-- ============================================================
-- SKILL MARKET — Consolidated Database Schema
-- Compatible with MySQL / MariaDB / phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS the_debugger;
USE the_debugger;

-- ============================================================
-- 1. USERS  (Student, Client, Lecturer, Admin)
-- ============================================================
CREATE TABLE users (
    user_id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name          VARCHAR(100) NOT NULL,
    email              VARCHAR(150) NOT NULL UNIQUE,
    password           VARCHAR(255) NOT NULL,
    role               VARCHAR(20) NOT NULL
                        CHECK (role IN ('student','client','lecturer','admin')),

    -- Faculty department (only meaningful for students)
    department         VARCHAR(20) DEFAULT 'NONE'
                        CHECK (department IN ('ET','ICT','BST','NONE')),
    student_id_number  VARCHAR(50) DEFAULT NULL,

    phone              VARCHAR(20)  DEFAULT NULL,
    location           VARCHAR(150) DEFAULT NULL,
    is_verified        TINYINT(1)   DEFAULT 0,      -- university/admin verified trust badge

    wallet_balance     DECIMAL(10,2) DEFAULT 0.00,  -- running balance for financial dashboard
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. SKILLS  (Student's public "skill profile" listings)
--    e.g. "Web Design - Rs.500/project", "Maths Tuition - Rs.300/hr"
-- ============================================================
CREATE TABLE skills (
    skill_id      INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    skill_name    VARCHAR(100) NOT NULL,
    description   TEXT,
    category      VARCHAR(50),          -- e.g. 'Tech Repair','Tutoring','Design'
    price         DECIMAL(10,2),        -- indicative rate, not a fixed task price
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================================
-- 3. TASKS  (Requirements posted by Clients/Lecturers)
--    e.g. "Fix WiFi", "Build POS system", "Need a Maths tutor"
-- ============================================================
CREATE TABLE tasks (
    task_id             INT AUTO_INCREMENT PRIMARY KEY,
    creator_id          INT NOT NULL,              -- client or lecturer who posted
    assigned_student_id INT DEFAULT NULL,           -- student who accepted it

    title               VARCHAR(150) NOT NULL,
    description         TEXT NOT NULL,
    required_dept       VARCHAR(20) DEFAULT 'NONE'
                         CHECK (required_dept IN ('ET','ICT','BST','NONE')),

    budget              DECIMAL(10,2) NOT NULL,
    status              VARCHAR(20) DEFAULT 'open'
                         CHECK (status IN ('open','taken','completed','cancelled')),

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    taken_at            TIMESTAMP NULL DEFAULT NULL,
    completed_at        TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (creator_id)          REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_student_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ============================================================
-- 4. REVIEWS  (Rating given after a task is completed)
-- ============================================================
CREATE TABLE reviews (
    review_id    INT AUTO_INCREMENT PRIMARY KEY,
    task_id      INT NOT NULL,
    reviewer_id  INT NOT NULL,     -- usually the client/lecturer
    reviewee_id  INT NOT NULL,     -- usually the student
    rating       INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment      TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (task_id)     REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id)  ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(user_id)  ON DELETE CASCADE,
    UNIQUE (task_id, reviewer_id)   -- one review per task per reviewer
);

-- ============================================================
-- 5. FOOD ITEMS  (Locals selling food to students/lecturers)
-- ============================================================
CREATE TABLE food_items (
    item_id       INT AUTO_INCREMENT PRIMARY KEY,
    seller_id     INT NOT NULL,             -- local seller (client role)
    item_name     VARCHAR(100) NOT NULL,
    description   TEXT,
    price         DECIMAL(10,2) NOT NULL,
    is_available  TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================================
-- 6. FOOD ORDERS
-- ============================================================
CREATE TABLE food_orders (
    order_id      INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id      INT NOT NULL,             -- student or lecturer
    item_id       INT NOT NULL,
    quantity      INT DEFAULT 1,
    total_price   DECIMAL(10,2) NOT NULL,
    status        VARCHAR(20) DEFAULT 'pending'
                  CHECK (status IN ('pending','completed','cancelled')),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (buyer_id) REFERENCES users(user_id)      ON DELETE CASCADE,
    FOREIGN KEY (item_id)  REFERENCES food_items(item_id)  ON DELETE CASCADE
);

-- ============================================================
-- 7. FINANCIAL TRANSACTIONS  (Ledger powering the finance dashboard)
-- ============================================================
CREATE TABLE financial_transactions (
    transaction_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    transaction_type  VARCHAR(10) NOT NULL CHECK (transaction_type IN ('INCOME','EXPENSE')),
    category          VARCHAR(50) NOT NULL,   -- 'TASK_EARNING','TASK_PAYMENT','FOOD_PURCHASE','FOOD_SALE'
    amount            DECIMAL(10,2) NOT NULL,
    description       VARCHAR(255) NOT NULL,

    task_id           INT DEFAULT NULL,
    order_id          INT DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)  REFERENCES users(user_id)         ON DELETE CASCADE,
    FOREIGN KEY (task_id)  REFERENCES tasks(task_id)         ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES food_orders(order_id)  ON DELETE SET NULL
);

-- ============================================================
-- 8. ADMIN ACTIVITY LOG  (Optional but useful for an admin role)
-- ============================================================
CREATE TABLE admin_logs (
    log_id       INT AUTO_INCREMENT PRIMARY KEY,
    admin_id     INT NOT NULL,
    action       VARCHAR(255) NOT NULL,   -- e.g. "Verified user #12", "Removed task #45"
    target_table VARCHAR(50),
    target_id    INT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- ============================================================
-- SAMPLE DATA (for demo/testing)
-- ============================================================

-- Users
INSERT INTO users (full_name, email, password, role, department, student_id_number, phone, location, is_verified, wallet_balance) VALUES
('System Admin',   'admin@skillmarket.com', 'admin123',   'admin',    'NONE', NULL,           NULL,        NULL,        1, 0.00),
('Kasun Perera',   'kasun@stu.ac.lk',       'student123', 'student',  'ICT',  'TG/2022/101',   '0771234567','Galle',     1, 1500.00),
('Nimal Silva',    'nimal@stu.ac.lk',       'student123', 'student',  'ET',   'TG/2022/205',   '0779876543','Matara',    1, 500.00),
('Dr. Jayasinghe',  'lecturer@uni.ac.lk',    'lec123',     'lecturer', 'NONE', NULL,           NULL,        'Campus',    1, 10000.00),
('Sunil Shopkeeper','sunil@gmail.com',       'client123',  'client',   'NONE', NULL,           '0765551234','Town',      0, 5000.00);

-- Student skill listings
INSERT INTO skills (student_id, skill_name, description, category, price) VALUES
(2, 'POS System Development', 'Build simple billing/inventory systems', 'ICT', 5000.00),
(2, 'WiFi & Network Troubleshooting', 'Home/shop network setup and fixes', 'ICT', 1000.00),
(3, 'Electrical Wiring Repair', 'Socket, switch and circuit fixes', 'ET', 1500.00);

-- Tasks posted
INSERT INTO tasks (creator_id, title, description, required_dept, budget, status) VALUES
(5, 'Need POS System for Grocery Shop', 'Need a basic inventory and billing POS system.', 'ICT', 8000.00, 'open'),
(5, 'Fix House Wiring Issue', 'Power socket trips at the main counter.', 'ET', 2500.00, 'open'),
(4, 'Format Research Data', 'Need an ICT student to format 50 pages of research data.', 'ICT', 4000.00, 'open');

-- Food items
INSERT INTO food_items (seller_id, item_name, description, price, is_available) VALUES
(5, 'Chicken Rice & Curry', 'Fresh home-made lunch packet', 400.00, 1),
(5, 'Egg Rotty Set', '3 rottis with hot spicy curry', 250.00, 1);
