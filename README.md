# Northstar

Northstar is a small, responsive tour-booking site built with PHP 8, MySQL, PDO,
and Apache. Visitors can browse a catalogue of northern Pakistan tours, create an
account, reserve a tour with a travel date and group size, and see their confirmed
bookings on a personal dashboard.

The application stays deliberately framework-free so the request flow, database
access, and security controls remain easy to read. It demonstrates account
registration, session authentication, server-side booking validation, and
account-scoped data access.

## Features

- Public tour catalogue with destination, duration, and per-person price.
- Account registration with server-side validation and unique username/email checks.
- Password storage with `password_hash()` and verification with `password_verify()`.
- Session-based authentication with session-ID regeneration after sign-in.
- CSRF-protected registration, login, booking, and sign-out POST requests.
- Booking form that validates the travel date and guest count, then recalculates
  the total from the stored tour price instead of trusting submitted values.
- Protected dashboard that lists only the signed-in user's bookings.
- Responsive interface, accessible notices, and a deliberate empty state.
- Apache virtual host that denies access to application config, docs, tests, and SQL files.
- Unit, database integration, and end-to-end HTTP acceptance tests.
- Reproducible local provisioning and deployment scripts for Ubuntu/Debian.

## Technology

| Layer | Choice |
| --- | --- |
| Web server | Apache 2.4 with `mod_php` |
| Application | PHP 8.0+ |
| Database | MySQL 5.7+ or MariaDB 10.3+ |
| Data access | PDO MySQL with prepared statements |
| Authentication | PHP sessions, `password_hash()`, `password_verify()` |
| Front end | Semantic HTML, responsive CSS, progressive-enhancement JavaScript |

## Requirements

- Ubuntu/Debian for the included provisioning scripts (the application itself is portable).
- PHP 8.0 or newer with the `pdo_mysql` extension.
- MySQL 5.7+ or MariaDB 10.3+.
- Apache 2.4 with the PHP module and `mod_headers`.
- `rsync`, `curl`, and `openssl` for the local setup scripts.
- Node.js is optional; it is used only for the JavaScript syntax check in `tests/run.sh`.

## Quick start on Ubuntu/Debian

Clone the repository and run the local provisioning script:

```sh
git clone https://github.com/KazimNajafi/northstar.git
cd northstar
./scripts/setup-local.sh
```

The script:

1. Creates or updates the `web_project_db` database and imports `schema.sql`.
2. Creates the least-privilege application account (`SELECT`, `INSERT` only).
3. Creates a separate local test account with the privileges needed by the tests.
4. Generates strong database passwords and writes them outside the repository.
5. Deploys the application to `/var/www/northstar`.
6. Enables the Apache site and environment configuration, then reloads Apache.

Open <http://localhost/> after the script finishes.

The local demo account is:

- Email: `demo@example.com`
- Password: `DemoPass123!`

The demo account, its booking, and the seeded tours are for local development
only. Remove or replace them before using the application anywhere real.

## Manual setup

The scripts are a convenience wrapper around the documented manual steps:

- [Linux and Apache setup](docs/linux-apache.md)
- [MySQL setup](docs/database-setup.md)
- [Product requirements](docs/PRD.md)
- [Verification results](docs/verification.md)

## Configuration

The application reads database settings from the PHP/Apache process environment.
No credentials are committed to the repository.

| Variable | Purpose |
| --- | --- |
| `DB_HOST` | MySQL server host, for example `127.0.0.1` |
| `DB_PORT` | MySQL TCP port, normally `3306` |
| `DB_NAME` | Database name, `web_project_db` |
| `DB_USER` | Application database account |
| `DB_PASSWORD` | Application database password |

For Apache with `mod_php`, define these values with `SetEnv` in
`/etc/apache2/conf-available/northstar-env.conf` and enable it with
`a2enconf northstar-env`. The local setup script creates that file with mode
`0640`, owned by `root:www-data`. Never commit it or place it inside the web root.

The test suite additionally uses `TEST_DB_USER`, `TEST_DB_PASSWORD`, and
`BASE_URL`. The setup script writes local test values to `tests/.env`, which is
ignored by Git.

## Using the site

1. Open `/` to browse the tour catalogue.
2. Select **Reserve this tour**. Anonymous visitors are sent to sign in or create
   an account, then returned to the chosen tour.
3. Choose a travel date (today up to two years ahead) and 1-12 guests.
4. Confirm the booking. The total is calculated on the server from the tour price.
5. The dashboard at `/home.php` lists the account's bookings and suggests tours
   that have not been booked yet.

Bookings are recorded only; no payment is collected and there is no cancellation
workflow in this release.

## Testing

Run the full available suite from the repository root:

```sh
./tests/run.sh
```

The suite performs:

- PHP syntax checks for every PHP file.
- A JavaScript syntax check when Node.js is installed.
- Unit checks for escaping, CSRF, password hashing, prices, dates, guest counts,
  and redirect safety.
- Database integration checks for hashing, prepared statements, uniqueness,
  booking totals, account scoping, foreign keys, and cascade deletion.
- HTTP acceptance checks for the catalogue, registration, login, booking, date
  and guest validation, CSRF rejection, sign-out, and multi-user isolation.

The HTTP tests expect the site to be available at `http://localhost/` by default.
Set `BASE_URL` to test a different address. Test accounts use the
`northstar.http-test.*@example.test` namespace and are removed automatically when
the test database credentials are available.

See [tests/README.md](tests/README.md) for details.

## Project structure

```text
northstar/
├── apache/
│   └── northstar.conf          Apache virtual host and access rules
├── assets/
│   ├── script.js               Form validation and live booking total
│   └── style.css               Responsive interface styling
├── config/
│   ├── app.php                 Session, CSRF, escaping, and shared helpers
│   └── db.php                  PDO connection from environment variables
├── docs/
│   ├── PRD.md                  Product requirements and design
│   ├── database-setup.md       Manual MySQL setup
│   ├── linux-apache.md         Manual Linux/Apache setup
│   └── verification.md         Verification scope and results
├── scripts/
│   ├── deploy.sh               Copy the app into the Apache web root
│   └── setup-local.sh          Provision the local database, Apache site, and tests
├── tests/
│   ├── bootstrap.php           Shared test environment loader
│   ├── http.sh                 End-to-end HTTP acceptance checks
│   ├── integration.php         Database integration checks
│   ├── run.sh                  Full test entry point
│   └── unit.php                Shared-helper checks
├── book.php                    Protected booking form
├── home.php                    Protected booking dashboard
├── index.php                   Public tour catalogue
├── login.php                   Account authentication
├── logout.php                  CSRF-protected sign-out
├── schema.sql                  Database, tables, indexes, tours, and demo data
└── signin.php                  Account registration
```

## Database model

`users` owns zero or more `bookings`. Each booking references one `tours` record
and one `users` record. Booking rows store the travel date, guest count, total
price, and status. `bookings.user_id` cascades on user deletion, while
`bookings.tour_id` uses `ON DELETE RESTRICT` so a tour with bookings cannot be
removed accidentally. Usernames, emails, and tour names are unique. See
[docs/PRD.md](docs/PRD.md) for the data dictionary and ERD.

## Security notes

- Passwords are stored only as hashes and never logged.
- All dynamic HTML output is escaped with `e()` (`htmlspecialchars`).
- Database access uses prepared statements; user values are never concatenated into SQL.
- Session IDs are regenerated after successful authentication.
- Session cookies are HTTP-only and `SameSite=Lax`; `Secure` is enabled under HTTPS.
- State-changing requests require a per-session CSRF token.
- Booking totals are calculated from the database price, never from form input.
- Post-login redirects accept only local `book.php` and `home.php` paths.
- The Apache virtual host denies direct access to `config/`, `docs/`, `tests/`, `apache/`, dotfiles, `schema.sql`, and `README.md`.
- The application connects with an account limited to `SELECT` and `INSERT`.

This is a local/educational baseline, not a complete production booking system.
Add rate limiting, email verification, password reset, multi-factor
authentication, payment handling, cancellation rules, HTTPS enforcement,
monitoring, and backups before public use. Remove the demo account before
production.

## Limitations

- No payment collection, cancellation, or booking modification.
- No admin interface for creating tours; tours are seeded through `schema.sql`.
- There is no password reset, email verification, role administration, or social sign-in.
- The sample account, demo booking, and seeded tours are development fixtures.
- The project targets a single Apache/PHP host and a local MySQL server.

## License

This project was created for an academic web-technologies assignment. No license
is granted for redistribution unless one is added by the project owner.
