# MySQL Setup

Northstar requires MySQL 5.7+ or MariaDB 10.3+ and PHP 8.0+ with PDO MySQL. It
reads `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` from the PHP
process environment. Credentials are not stored in this repository.

## Automated setup

From the repository root:

```sh
./scripts/setup-local.sh
```

On Ubuntu/Debian, the script uses `sudo mysql` (socket authentication) to:

1. Import `schema.sql` and create `web_project_db`.
2. Create the application account `northstar_app` with `SELECT` and `INSERT` only.
3. Create the local test account `northstar_test` with `SELECT`, `INSERT`, `UPDATE`, and `DELETE` for the test suite.
4. Generate strong passwords.
5. Write `/etc/apache2/conf-available/northstar-env.conf` (mode `0640`, `root:www-data`).
6. Write local test settings to `tests/.env` (mode `0600`, ignored by Git).

The script is idempotent: re-running it resets the local account passwords, keeps
existing application data, and re-applies the schema fixtures without duplicating
the seeded tours or the demo booking.

## Manual database creation

Open an administrative MySQL session:

```sh
sudo mysql
```

Create the database and application account. Replace the example password with a
unique random value; do not reuse the example text.

```sql
CREATE DATABASE IF NOT EXISTS web_project_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'northstar_app'@'127.0.0.1'
    IDENTIFIED BY 'replace-with-a-long-random-password';
GRANT SELECT, INSERT ON web_project_db.* TO 'northstar_app'@'127.0.0.1';
```

Exit MySQL and import the schema:

```sh
sudo mysql < /home/kazim/northstar/schema.sql
```

The schema creates the database, the three InnoDB tables with their constraints,
six seeded tours, a local demo account, and one upcoming demo booking. The demo
credentials are:

- Email: `demo@example.com`
- Password: `DemoPass123!`

The demo account is for local verification only. Remove or replace it before
production use. The application account is limited to the operations required by
this release and cannot alter the schema or delete data.

## Test database account

The HTTP and database tests create temporary users and bookings. Create a separate
local test account with the privileges needed for cleanup:

```sql
CREATE USER IF NOT EXISTS 'northstar_test'@'127.0.0.1'
    IDENTIFIED BY 'replace-with-another-long-random-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON web_project_db.* TO 'northstar_test'@'127.0.0.1';
```

The test account is for local development only. Do not use it as the application
account in production.

## Configure PHP/Apache

For Apache with `mod_php`, create a root-owned environment file outside the Git
checkout:

```sh
sudoedit /etc/apache2/conf-available/northstar-env.conf
```

Add the five `SetEnv` directives, replacing the example password locally:

```apache
SetEnv DB_HOST "127.0.0.1"
SetEnv DB_PORT "3306"
SetEnv DB_NAME "web_project_db"
SetEnv DB_USER "northstar_app"
SetEnv DB_PASSWORD "replace-with-a-long-random-password"
```

Protect and enable the file, then reload Apache:

```sh
sudo chown root:www-data /etc/apache2/conf-available/northstar-env.conf
sudo chmod 640 /etc/apache2/conf-available/northstar-env.conf
sudo a2enconf northstar-env
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Do not commit this environment file or paste its password into source control.
If the database connection fails, PHP logs diagnostic details while the page
shows a generic retry message.

## Run the PHP development server without Apache

This is useful for a quick local check. The settings must be present in the
environment of the PHP process:

```sh
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=web_project_db
export DB_USER=northstar_app
read -rsp 'Database password: ' DB_PASSWORD
printf '\n'
export DB_PASSWORD
php -S 127.0.0.1:8000 -t /home/kazim/northstar
```

Open <http://127.0.0.1:8000/>.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `Database configuration is incomplete` | All five `DB_*` variables must be visible to the PHP process. |
| `Could not connect to the database` | Confirm MySQL is running, the database exists, and the account host/port are correct. |
| `PDO MySQL driver missing` | Enable `pdo_mysql` for the PHP runtime used by Apache. |
| Access denied for `root` | Use `sudo mysql` for socket authentication, or run the SQL as an administrative account. |
| Tests skip database coverage | Confirm `tests/.env` exists and contains `TEST_DB_USER` and `TEST_DB_PASSWORD`. |
