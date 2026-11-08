# Northstar Verification Results

**Verified:** 2026-11-08 21:39 PKT  
**Reference environment:** Ubuntu 24.04.2 LTS, Apache 2.4.58, PHP 8.3.6, MySQL 8.0.46  
**Test entry point:** `./tests/run.sh`

## 1. Scope

The verification covers the implemented application, the MySQL schema, the
Apache deployment, and three test layers:

- Static syntax checks for PHP and JavaScript.
- Unit checks for shared helpers.
- Database integration checks against the imported schema.
- End-to-end HTTP checks against the Apache-hosted site.
- A browser layout check at 360 px and 1280 px viewport widths.

The checks do not cover performance, load, accessibility auditing with a
dedicated tool, payments, or production infrastructure.

## 2. Commands run

```sh
./scripts/deploy.sh
PATH=/tmp/opencode/node/bin:$PATH ./tests/run.sh
cd /tmp/opencode/browser-test && node check.mjs
```

The `PATH` prefix supplied a temporary Node.js 26.13.0 binary for
`node --check`. Node.js is not required at runtime and is not installed system-wide
on the reference machine.

The full suite output was:

```text
== PHP syntax ==
No syntax errors detected in all application and test PHP files.

== JavaScript syntax ==
PASS assets/script.js passed node --check

== Unit checks ==
41 assertions, 0 failure(s)

== Database integration checks ==
23 assertions, 0 failure(s)

== HTTP acceptance checks ==
76 passed, 0 failed, 0 skipped

All available checks passed.
```

The browser layout check reported:

```text
21 passed, 0 failed
```

## 3. Results summary

| Check | Result | Evidence |
| --- | --- | --- |
| PHP syntax | Passed | `php -l` on all PHP files. |
| JavaScript syntax | Passed | `node --check assets/script.js` with Node.js 26.13.0. |
| Unit checks | Passed | 41 assertions, 0 failures. |
| Database integration | Passed | 23 assertions, 0 failures. |
| HTTP acceptance | Passed | 76 checks, 0 failures, 0 skips. |
| Responsive browser layout | Passed | 21 checks at 360 px and 1280 px with Chrome 157. |
| Apache configuration | Passed | `apache2ctl configtest` returned `Syntax OK`. |
| Apache and MySQL services | Passed | Both reported `active`. |
| Schema import | Passed | `web_project_db`, `users`, `tours`, and `bookings` created; six tours and one demo booking present. |
| Anonymous dashboard access | Passed | `302` redirect to `login.php`. |
| Anonymous booking access | Passed | `302` redirect to `login.php` with the chosen tour preserved. |
| Protected file access | Passed | `403` for `config/db.php`, `schema.sql`, `docs/database-setup.md`, and `apache/northstar.conf`. |
| Test suite on the web root | Passed | `404`; `tests/` is excluded from deployment. |
| Security headers | Passed | `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, and `Permissions-Policy` on dynamic and static responses. |
| Multi-user isolation | Passed | Each test account saw only its own booking row. |
| Server-side pricing | Passed | A forged `total_price=1` was ignored; the stored total matched price x guests. |

## 4. Requirement coverage

| Requirement | Verification |
| --- | --- |
| FR-01 Tour catalogue | HTTP check renders the catalogue and a seeded tour; browser check confirms cards and reserve links. |
| FR-02 Registration form | HTTP checks for valid registration, mismatched passwords, invalid email, and CSRF rejection. |
| FR-03 Unique account | HTTP duplicate email and duplicate username checks; integration test verifies the unique indexes. |
| FR-04 Login | HTTP checks for wrong and correct passwords; integration test verifies `password_verify()`. |
| FR-05 Protected booking | Anonymous HTTP request returns `302`; a signed-in user receives the booking form. |
| FR-06 Server-side total | HTTP check submits a false total and verifies the database total equals price x guests. |
| FR-07 Travel date rules | Unit checks for today, past, impossible, malformed, and out-of-window dates; HTTP check for a past date. |
| FR-08 Personal bookings | Integration query-scoping test and HTTP two-user isolation checks using booking markers. |
| FR-09 Confirmation | HTTP check verifies the `home.php?booked=1` redirect and the confirmation notice. |
| FR-10 Sign out | HTTP checks for invalid CSRF (`400`, session remains) and valid CSRF (`302`, session ends). |
| FR-11 Error feedback | Unit test verifies technical database details are redacted; HTTP checks exercise validation messages. |
| FR-12 Safe return | Unit checks for the redirect allow-list; HTTP check preserves the tour through the login redirect. |
| NFR-01 Security | Hashed passwords, prepared statements, CSRF tokens, strict session mode, output escaping, and security headers are covered by unit, integration, and HTTP checks. |
| NFR-02 Portability | Configuration comes from environment variables; includes use `__DIR__` and `DIRECTORY_SEPARATOR`; no credentials are committed. |
| NFR-03 Usability | Browser layout checks at 360 px and 1280 px; HTTP checks confirm form labels and empty state. |
| NFR-04 Maintainability | Application, config, assets, docs, scripts, and tests are separated. |
| NFR-05 Reliability | Missing configuration and connection failures produce a generic retry message; PHP syntax and integration checks pass. |
| NFR-06 Compatibility | Verified on PHP 8.3.6 and MySQL 8.0.46; schema uses MySQL 5.7+/MariaDB-compatible SQL. |

## 5. Unit checks

File: `tests/unit.php`

Verified:

- `e()` escapes HTML special characters.
- `post_string()` handles missing keys and non-string values.
- `csrf_token()` returns a stable 64-character hexadecimal token.
- `csrf_is_valid()` accepts the matching token and rejects wrong or missing tokens.
- `password_hash()` does not store plain text; `password_verify()` accepts only the correct password.
- `database_error_message()` hides diagnostic details and gives a retry message.
- `sign_in_user()` stores the authenticated identity and rotates the CSRF token.
- Strict session mode is enabled.
- Session IDs are not accepted from URLs.
- `format_price()` renders whole-rupee prices.
- `parse_travel_date()` accepts today and future dates inside two years, and rejects past, impossible, malformed, and out-of-window dates.
- `parse_guest_count()` accepts 1-12 and rejects zero, thirteen, fractional, and non-numeric values.
- `format_travel_date()` renders a readable date.
- `safe_next_path()` allows only `book.php` and `home.php`, and rejects absolute, protocol-relative, unknown, and nested URLs.

Result: **41 assertions, 0 failures.**

## 6. Database integration checks

File: `tests/integration.php`

Verified against MySQL 8.0.46:

- The PDO connection has emulated prepares disabled.
- A registered user can be read back from MySQL.
- The stored password is a hash, not the submitted plain text.
- `password_verify()` accepts the correct password and rejects a wrong password.
- Duplicate email and duplicate username inserts are rejected by unique indexes.
- The tour catalogue contains at least six seeded tours with positive prices.
- A duplicate tour name is rejected by the unique index.
- The dashboard query returns only the selected user's bookings.
- Bookings are ordered by ascending travel date.
- The stored total equals the tour price multiplied by guests.
- A second user receives a different booking set.
- A booking must reference an existing tour.
- A user can be found by email for sign-in.
- A prepared statement resists a SQL injection payload.
- Deleting a user cascades to that user's bookings.
- Deleting one user leaves another user's bookings and the catalogue intact.
- The deleted user no longer exists.

Result: **23 assertions, 0 failures.**

## 7. HTTP acceptance checks

File: `tests/http.sh`

The suite uses unique accounts in the
`northstar.http-test.*@example.test` namespace and removes them at the start and
end of the run.

Verified:

- The catalogue renders with seeded tours and reservation links.
- The registration page renders with a 64-character CSRF token.
- The sign-in page renders.
- Anonymous dashboard access redirects to `login.php`.
- Anonymous booking access redirects to `login.php` and preserves the chosen tour.
- Direct access to application config, schema, docs, and Apache config returns `403`.
- The test suite is not deployed and returns `404`.
- Security headers are present on responses.
- A valid registration redirects to the dashboard.
- A new dashboard shows the empty bookings state and greets the user.
- The password is stored as a hash.
- A signed-in user can open the booking form for a seeded tour.
- A valid booking redirects with `home.php?booked=1` and appears on the dashboard.
- The stored total is calculated from the tour price, not a submitted total.
- Duplicate email and duplicate username registrations are rejected.
- Mismatched passwords and invalid email addresses are rejected.
- A stale CSRF token is rejected on registration and on booking.
- A past travel date and an oversized group are rejected.
- An unknown tour returns `404` with a friendly page.
- Sign-out with an invalid CSRF token returns `400` and keeps the session.
- Sign-out with a valid CSRF token redirects to login and ends the session.
- A wrong password is rejected.
- A correct password signs the user in and redirects to the dashboard.
- A second account can register and book a different tour.
- Each account sees only its own booking row.

Result: **76 passed, 0 failed, 0 skipped.**

## 8. Responsive browser check

The catalogue, registration, sign-in, dashboard, and booking pages were opened in
Google Chrome 157.0.8103.38 through the Chrome DevTools protocol at two viewport
sizes.

At 360 x 800:

- `document.documentElement.scrollWidth` equalled `window.innerWidth` on every page (no horizontal overflow).
- The mobile brand was visible and the story panel was hidden on the auth pages.
- The catalogue tour grid used a single column and exposed all six reserve links.
- The booking page used a single-column shell and a 282 px form inside the viewport.
- A booking card was visible on the demo dashboard.

At 1280 x 800:

- `document.documentElement.scrollWidth` equalled `window.innerWidth`.
- The catalogue tour grid used three columns.
- The booking page used a two-column shell.
- Booking cards remained readable on the dashboard.

The dashboard check signed in with the local demo account. The demo account and
its booking are development fixtures.

Result: **21 passed, 0 failed.**

## 9. Deployment checks

| Check | Result |
| --- | --- |
| `systemctl is-active apache2` | `active` |
| `systemctl is-active mysql` | `active` |
| `apache2ctl configtest` | `Syntax OK` (`AH00558` server-name warning is local and harmless) |
| `GET /` | `200` (tour catalogue) |
| Anonymous `GET /home.php` | `302` to `login.php` |
| Anonymous `GET /book.php?tour=1` | `302` to `login.php?next=book.php%3Ftour%3D1` |
| `GET /config/db.php` | `403` |
| `GET /schema.sql` | `403` |
| `GET /docs/database-setup.md` | `403` |
| `GET /apache/northstar.conf` | `403` |
| `GET /tests/run.sh` | `404` (not deployed) |
| Security headers on dynamic response | Present |
| Security headers on static CSS response | Present |

The Apache virtual host serves `/var/www/northstar`, denies application config,
docs, Apache config, tests, dotfiles, `schema.sql`, and `README.md`, and sets the
browser security headers.

## 10. Known limitations and follow-up work

- The browser layout check was run manually with Chrome and `puppeteer-core`; it is not part of `tests/run.sh`.
- Node.js was supplied from a temporary binary for the JavaScript syntax check; the suite skips that check when `node` is unavailable.
- No CI pipeline is configured. `tests/run.sh` is the intended CI entry point.
- The demo account, demo booking, and seeded tours must be removed or replaced before production.
- Rate limiting, email verification, password reset, multi-factor authentication, payments, cancellation, centralised audit logging, backups, and monitoring remain out of scope.
- Tours are seeded through `schema.sql`; there is no admin authoring interface.
- The deployment targets a single Ubuntu/Debian host with Apache `mod_php` and a local MySQL server.

## 11. Reproduction notes

1. Run `./scripts/setup-local.sh` on an Ubuntu/Debian machine with Apache, PHP, and MySQL installed.
2. Open <http://localhost/> and confirm the catalogue loads.
3. Run `./tests/run.sh`.
4. Optionally install Node.js (`sudo apt install nodejs`) to enable the JavaScript syntax check.
5. For a browser check, open the site at a narrow viewport and confirm there is no horizontal scrolling.
