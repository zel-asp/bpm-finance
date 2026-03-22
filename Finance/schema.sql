-- Integrated Finance System - MySQL Schema
-- Engine: InnoDB, default charset utf8mb4

CREATE DATABASE IF NOT EXISTS finance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finance_system;

-- Users & roles
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','FINANCE_MANAGER','ACCOUNTANT','BUDGET_OFFICER','VIEWER') NOT NULL DEFAULT 'VIEWER',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Chart of Accounts
CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Budget Management
CREATE TABLE budgets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fiscal_year YEAR NOT NULL,
  department VARCHAR(100) NOT NULL,
  status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','CLOSED') NOT NULL DEFAULT 'DRAFT',
  total_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_budgets_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE budget_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  budget_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  CONSTRAINT fk_budget_items_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_items_account FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

-- Vendors & Customers
CREATE TABLE vendors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(150),
  email VARCHAR(150),
  phone VARCHAR(50),
  address TEXT
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(150),
  email VARCHAR(150),
  phone VARCHAR(50),
  address TEXT
) ENGINE=InnoDB;

-- Accounts Payable (AP)
CREATE TABLE ap_invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(50) NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE,
  amount DECIMAL(16,2) NOT NULL,
  status ENUM('PENDING','APPROVED','PAID','REJECTED') NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_invoices_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id)
) ENGINE=InnoDB;

CREATE TABLE ap_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ap_invoice_id INT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  method ENUM('CASH','BANK_TRANSFER','CHECK','OTHER') NOT NULL,
  reference VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_payments_invoice FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cash & Bank Management
CREATE TABLE bank_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  account_number VARCHAR(100),
  bank_name VARCHAR(150),
  opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE bank_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_account_id INT UNSIGNED NOT NULL,
  txn_date DATE NOT NULL,
  description VARCHAR(255),
  direction ENUM('IN','OUT') NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  source_module VARCHAR(50),
  source_id INT UNSIGNED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bank_transactions_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
) ENGINE=InnoDB;

-- Expense Management
CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_name VARCHAR(150) NOT NULL,
  department VARCHAR(100) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT,
  amount DECIMAL(16,2) NOT NULL,
  status ENUM('SUBMITTED','APPROVED','REJECTED','PAID') NOT NULL DEFAULT 'SUBMITTED',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  paid_at DATETIME NULL
) ENGINE=InnoDB;

-- Payroll integration
CREATE TABLE payroll_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period VARCHAR(7) NOT NULL, -- YYYY-MM
  description VARCHAR(255),
  gross_amount DECIMAL(16,2) NOT NULL,
  net_amount DECIMAL(16,2) NOT NULL,
  employer_contributions DECIMAL(16,2) NOT NULL DEFAULT 0,
  status ENUM('DRAFT','APPROVED','POSTED') NOT NULL DEFAULT 'DRAFT',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  posted_at DATETIME NULL
) ENGINE=InnoDB;

-- Accounts Receivable (AR)
CREATE TABLE ar_invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(50) NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE,
  amount DECIMAL(16,2) NOT NULL,
  status ENUM('PENDING','APPROVED','PAID','CANCELLED') NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ar_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

CREATE TABLE ar_receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ar_invoice_id INT UNSIGNED NOT NULL,
  receipt_date DATE NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  method ENUM('CASH','BANK_TRANSFER','CHECK','OTHER') NOT NULL,
  reference VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ar_receipts_invoice FOREIGN KEY (ar_invoice_id) REFERENCES ar_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- General Ledger (GL)
CREATE TABLE journal_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  source_module VARCHAR(50),
  source_id INT UNSIGNED,
  posted_by INT UNSIGNED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_journal_entries_user FOREIGN KEY (posted_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE journal_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  journal_entry_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  debit DECIMAL(16,2) NOT NULL DEFAULT 0,
  credit DECIMAL(16,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_journal_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_journal_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

-- Simple seed for an admin user (update password hash after first login if desired)
INSERT INTO users (name, email, password_hash, role)
VALUES ('Admin', 'admin@example.com', '$2y$10$abcdefghijklmnopqrstuvABCDEFGHijklmnopqrstu', 'ADMIN');

