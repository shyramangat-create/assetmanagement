# Asset Management System (PHP 8 + MySQL)

A responsive admin-only asset management application built with PHP 8, PDO, MySQL, Bootstrap 5, and Chart.js.

## Included modules

- Single administrator login (no user management, roles, or Employees module)
- Dashboard with exactly five summary cards, date filters, charts, recent records, warranty reminders, and maintenance alerts
- Asset entry, editing, inventory cards, search, status filter, pagination, photo upload, and asset history
- Multi-asset assignment, return validation, direct transfers, maintenance, and disposal
- Vendor directory and per-asset expenses
- CSV import; direct `.xlsx` / `.xls` import when PhpSpreadsheet is installed
- Hardware asset purchases, depreciation, assignment, return, transfer, maintenance, vendor, and expense reports with print layouts; individual asset history is available separately
- PDO prepared statements, password hashing, session checks, CSRF tokens, output escaping, and upload validation

## Requirements

- PHP 8.1 or later
- MySQL 5.7+ or MySQL 8 / MariaDB with InnoDB support
- Apache (XAMPP or WAMP)
- PHP extensions: `pdo_mysql`, `fileinfo`; `mbstring` recommended
- Optional for direct Excel workbook import: Composer and the PhpSpreadsheet package

## XAMPP setup

1. Place the project at `C:\xampp\htdocs\assetmanagement-main\asset_management\` to match the current configuration. You can use another location under `htdocs` if you update `BASE_URL` in step 6.
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
6. Open `config/config.php`. Its current `BASE_URL` is `/assetmanagement-main/asset_management/`. Match this to the folder path beneath `htdocs`, including the trailing slash. For example, if you install directly at `C:\xampp\htdocs\asset_management\`, use `/asset_management/`.
7. Visit `http://localhost/assetmanagement-main/asset_management/`, or the URL corresponding to your chosen `BASE_URL`.

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

The database seed creates one administrator account. The application has no user-management interface or employee table.

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

Use `1` for depreciation enabled and `0` for disabled. Supported conditions are `New`, `Good`, `Fair`, `Poor`, and `Damaged`. Supported depreciation methods are `Straight Line` and `Written Down Value`. Useful life, salvage value, and WDV rate are numeric fields. When depreciation is enabled, useful life must be greater than zero and salvage value cannot exceed purchase cost.

The importer currently applies defaults to some unsupported values instead of rejecting them: an unknown condition becomes `New`, an unknown method becomes `Straight Line`, and the depreciation flag is enabled only when its integer conversion is `1`. Numeric fields may be converted or limited to their allowed ranges. Use the documented values to avoid unintended results. Import files are limited to 10 MB.

## Upload folder

The folder `assets/uploads/` stores asset photos. Ensure the Apache/PHP process can write to this folder. Only JPG, PNG, and WEBP uploads up to 5 MB are accepted. The included `.htaccess` blocks PHP execution in the upload folder.

Keep this directory and its `.htaccess` when copying the project. A missing folder produces an upload error. On Windows, the upload code checks whether saving the uploaded file succeeds rather than relying on the directory's `is_writable()` result.

## Reports and printing

The Hardware Asset Report lists each asset's purchase date, asset ID, name, quantity, purchase cost, and status, followed by total items and total purchase cost. Each asset entry counts as one item. Its date filters use purchase date. The Reports menu currently labels this entry **Assets & History**, but the report itself shows purchases; use the individual asset history page for activity records.

Reports share a centered system/company heading, address, and contact number in `reports/view.php`. The contact details are fictional sample values and should be replaced for real use. Print styling is in `assets/css/style.css` and requests A4 portrait with 18 mm top/bottom and 15 mm side margins. Long reports can span multiple pages.

Use the browser's Print command or **Print Report**, select A4 portrait and 100% scale, and choose **Save as PDF** if needed. Disable **Headers and footers** in the browser print settings to remove the browser-added URL, date, and page numbers.

`report-preview.html` is a static design preview with sample records, not live database data. Run `php report-preview-build.php` from the project folder to regenerate it after template or style changes. The browser print preview remains the check for actual page breaks.

## Maintenance status

In **Assets → Maintenance**, save a maintenance record with status **In Progress** to set an Available or Assigned asset to **Under Maintenance**. Marking that record **Completed** restores the asset's previous status. Recording already completed maintenance does not change the asset's status.

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
