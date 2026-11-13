# Asset Management System (PHP 8 + MySQL)

A responsive admin-only asset management application built with PHP 8, PDO, MySQL, Bootstrap 5, and Chart.js.

## Included modules

- Single administrator login (no user management, roles, or Employees module)
- Dashboard with exactly five summary cards, date filters, charts, recent records, warranty reminders, and maintenance alerts
- Asset entry, editing, inventory cards, search, status filter, pagination, photo upload, and asset history
- Multi-asset assignment, return validation, direct transfers, maintenance, and disposal
- Vendor directory and per-asset expenses
- CSV import; direct `.xlsx` / `.xls` import when PhpSpreadsheet is installed
- Asset/activity, depreciation, assignment, return, transfer, maintenance, vendor, and expense reports with print layouts
- PDO prepared statements, password hashing, session checks, CSRF tokens, output escaping, and upload validation

## Requirements

- PHP 8.1 or later
- MySQL 5.7+ or MySQL 8 / MariaDB with InnoDB support
- Apache (XAMPP or WAMP)
- PHP extensions: `pdo_mysql`, `fileinfo`; `mbstring` recommended
- Optional for direct Excel workbook import: Composer and the PhpSpreadsheet package

## XAMPP setup

1. Copy the entire `asset_management` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Choose **Import**, select `database.sql` from this project folder, and run the import. The script creates the `asset_management` database and sample data.
5. Open `config/database.php`. The default XAMPP settings are:

   ```php
   $host = '127.0.0.1';
   $dbname = 'asset_management';
   $dbuser = 'root';
   $dbpass = '';
   ```

   Change `$dbuser` and `$dbpass` when your MySQL setup uses different credentials.
6. Open `config/config.php`. Its `BASE_URL` is `/asset_management/`. Change it if you use another folder name.
7. Visit `http://localhost/asset_management/`.

## Default administrator login

- Username: `admin`
- Password: `password`

This is a demonstration credential. **Change it before using real asset records.** One way to generate a password hash is to run this command in a terminal with PHP installed:

```bash
php -r "echo password_hash('YOUR_NEW_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

Copy the generated hash, then run this query in phpMyAdmin:

```sql
UPDATE admin
SET password = 'PASTE_THE_GENERATED_HASH_HERE'
WHERE username = 'admin';
```

There is only one row in the `admin` table. The application has no additional user or employee tables.

## Excel import (optional)

CSV importing works without Composer. To import `.xlsx` or `.xls` files directly:

1. Install Composer.
2. Open a terminal in the `asset_management` project folder.
3. Run:

   ```bash
   composer install
   ```

4. Use the same fixed column order shown by **Assets → Import CSV**. You may also save an Excel workbook as **CSV UTF-8** and import it without installing Composer.

The import template is downloadable from the import page and uses spaces in column headers. Headers with underscores are also accepted, and `condition` is accepted as an alternative to `condition status` or `condition_status`. Do not reorder or remove columns. Blank optional cells should remain in the file. Purchase dates accept MM/DD/YYYY or YYYY-MM-DD.

## Upload folder

The folder `assets/uploads/` stores asset photos. Ensure the Apache/PHP process can write to this folder. Only JPG, PNG, and WEBP uploads up to 5 MB are accepted. The included `.htaccess` blocks PHP execution in the upload folder.

## Notes about calculations and filters

- Warranty expiry is calculated from purchase date plus warranty months.
- Straight-line depreciation is prorated by elapsed days over 365.25 days per year and stops at salvage value.
- Written Down Value uses the asset's annual reducing-balance percentage and prorates each year by elapsed days.
- The financial-year dashboard preset uses **April 1 through March 31**. Adjust `dateRange()` in `config/functions.php` if your organization uses a different financial year.
- Dashboard asset cards and asset charts use purchase date for the selected period. Upcoming maintenance and warranty alerts are rolling 30-day alerts from the current date.
- Reports are printable through the browser's Print command. Choose “Save as PDF” in the print dialog to create a PDF copy.
- Bootstrap, Font Awesome, and Chart.js are loaded from CDNs, so the browser needs internet access for those UI libraries unless you download and host them locally.

## Security and deployment

This is an educational/local-project starter. Before exposing it to the public internet, use HTTPS, change the default admin password, configure secure database credentials, back up the database, and review server permissions. The project uses prepared statements and CSRF tokens, but a production deployment should also add a password-change workflow, login rate limiting, audit review, and a tested backup strategy.
