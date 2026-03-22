## Finance System (PHP + MySQL on XAMPP)

This is a simple integrated finance system based on your diagrams, built with **PHP**, **MySQL**, **Bootstrap 5**, and basic JavaScript.

### 1. XAMPP setup

1. Install XAMPP (if not already) and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. In phpMyAdmin, go to the **Import** tab and import the SQL file:
   - `C:/Users/Benjie/finance-system/schema.sql`
   - This creates the `finance_system` database and tables.

### 2. Copy project into XAMPP `htdocs`

1. Create a folder `C:\xampp\htdocs\finance-system`.
2. Copy these folders/files from `C:\Users\Benjie\finance-system` into that folder:
   - `config.php`
   - `includes/`
   - `public/`
   - `schema.sql` (optional, only needed for future imports)
3. After copying, your structure under `C:\xampp\htdocs\finance-system` should look like:
   - `config.php`
   - `includes\db.php`
   - `public\index.php`
   - `public\pages\dashboard.php`
   - `public\pages\budgets.php`
   - `public\pages\ap.php`
   - `public\pages\ar.php`
   - `public\pages\gl.php`

The `config.php` is already configured for default XAMPP MySQL:

- Host: `localhost`
- User: `root`
- Password: *(empty)*
- Database: `finance_system`

If your XAMPP path or folder name is different, update `BASE_PATH` in `config.php` (e.g. `/finance-system`).

### 3. Run the app

1. In a browser, open:
   - `http://localhost/finance-system/public/index.php`
2. You will see:
   - **Dashboard** with counts for Budgets, AP, AR
   - **Budgets** page:
     - Create a budget (year, department, total amount)
     - See a list of budgets (status starts as `DRAFT`)
   - **Accounts Payable** page:
     - Record supplier invoices (vendor, invoice no, date, amount)
     - Vendors are auto-created based on the name you enter
   - **Accounts Receivable** page:
     - Generate customer invoices (customer, invoice no, date, amount)
     - Customers are auto-created based on the name you enter
   - **General Ledger** page:
     - Shows a simple **trial balance** (sum of debits and credits per account) based on `journal_entries` and `journal_lines` (you can insert these manually via SQL for now).

### 4. How this maps to your diagrams

- **Budget Management (Budget Officer / Department)**  
  - Diagrams: *Create/Update Budget, Allocate Budget, Monitor Budget Utilization*  
  - System: `budgets` + `budget_items` tables and the **Budgets** page handle budget creation and tracking per department and year.

- **Accounts Payable (AP)**  
  - Diagrams: *Receive Supplier Invoice, Verify Invoice, Record Payable, Process Payment*  
  - System: **AP** page creates `ap_invoices` for each supplier invoice; `vendors` store suppliers. You can extend this to add payment posting into `ap_payments` and automatically create `journal_entries` and `journal_lines`.

- **Accounts Receivable (AR)**  
  - Diagrams: *Generate Invoice, Record Receivable, Record Customer Payment*  
  - System: **AR** page creates `ar_invoices` and customers. You can later add receipts (`ar_receipts`) and link them to GL.

- **General Ledger (GL) & Reporting**  
  - Diagrams: *Post Journal Entry, Update Account Balances, Generate Financial Reports*  
  - System: `journal_entries` + `journal_lines` tables hold double-entry postings. **GL** page shows a basic trial balance; from here you can add:
    - Income Statement
    - Balance Sheet
    - Cash Flow reports

### 5. Next improvements you can add

- Add **login/authentication** using the `users` table (PHP sessions, password hashing).
- Tie AP/AR actions to **automatic journal entries** (so invoices and payments immediately hit the GL).
- Add **budget vs actual** reports by comparing `budget_items` with aggregated GL data.
- Add **status workflows** (Submitted, Approved, Paid) following your BPMN/DFD diagrams more strictly.

