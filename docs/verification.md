# Northstar Verification Results

**Verified:** 2026-11-08 21:18 PKT  
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
dedicated tool, or production infrastructure.

## 2. Commands run

```sh
./scripts/setup-local.sh
./scripts/deploy.sh
PATH=/tmp/opencode/node/bin:$PATH ./tests/run.sh
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
17 assertions, 0 failure(s)

== Database integration checks ==
17 assertions, 0 failure(s)

== HTTP acceptance checks ==
51 passed, 0 failed, 0 skipped

All available checks passed.
```

## 3. Results summary

| Check | Result | Evidence |
| --- | --- | --- |
| PHP syntax | Passed | `php -l` on all PHP files. |
| JavaScript syntax | Passed | `node --check assets/script.js` with Node.js 26.13.0. |
| Unit checks | Passed | 17 assertions, 0 failures. |
| Database integration | Passed | 17 assertions, 0 failures. |
| HTTP acceptance | Passed | 51 checks, 0 failures, 0 skips. |
| Responsive browser layout | Passed | 13 checks at 360 px and 1280 px with Chrome 157. |
| Apache configuration | Passed | `apache2ctl configtest` returned `Syntax OK`. |
| Apache and MySQL services | Passed | Both reported `active`. |
| Schema import | Passed | `web_project_db`, `users`, and `items` created; demo fixtures present. |
| Anonymous dashboard access | Passed | `302` redirect to `login.php`. |
| Protected file access | Passed | `403` for `config/db.php`, `schema.sql`, `docs/database-setup.md`, and `apache/northstar.conf`. |
| Security headers | Passed | `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, and `Permissions-Policy` on dynamic and static responses. |
| Multi-user isolation | Passed | Each test account saw only its own item. |

## 4. Requirement coverage

| Requirement | Verification |
| --- | --- |
| FR-01 Registration form | HTTP checks for valid registration, mismatched passwords, invalid email, and CSRF rejection. |
| FR-02 Unique account | HTTP duplicate email and duplicate username checks; integration test verifies the unique indexes. |
| FR-03 Login | HTTP checks for wrong and correct passwords; integration test verifies `password_verify()`. |
| FR-04 Protected dashboard | Anonymous HTTP request returns `302` to `login.php`; after sign-out the dashboard is protected again. |
| FR-05 Personal item list | Integration query scoping test and HTTP two-user isolation checks. |
| FR-06 Sign out | HTTP checks for invalid CSRF (`400`, session remains) and valid CSRF (`302`, session ends). |
| FR-07 Error feedback | Unit test verifies technical database details are redacted; HTTP checks exercise validation messages. |
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

Result: **17 assertions, 0 failures.**

## 6. Database integration checks

File: `tests/integration.php`

Verified against MySQL 8.0.46:

- The PDO connection has emulated prepares disabled.
- A registered user can be read back from MySQL.
- The stored password is a hash, not the submitted plain text.
- `password_verify()` accepts the correct password and rejects a wrong password.
- Duplicate email and duplicate username inserts are rejected by unique indexes.
- The dashboard query returns only the selected user's items.
- Items are ordered by descending id.
- A second user receives a different item set.
- A user can be found by email for sign-in.
- A prepared statement resists a SQL injection payload.
- Deleting a user cascades to that user's items.
- Deleting one user leaves another user's items intact.
- The deleted user no longer exists.

Result: **17 assertions, 0 failures.**

## 7. HTTP acceptance checks

File: `tests/http.sh`

The suite uses unique accounts in the
`northstar.http-test.*@example.test` namespace and removes them at the start and
end of the run.

Verified:

- The registration page renders with a 64-character CSRF token.
- The sign-in page renders.
- Anonymous dashboard access redirects to `login.php`.
- Direct access to application config, schema, docs, and Apache config returns `403`.
- Security headers are present on responses.
- A valid registration redirects to the dashboard.
- A new dashboard shows the empty state and greets the user.
- The password is stored as a hash.
- An account-owned item is displayed on the dashboard.
- Duplicate email and duplicate username registrations are rejected.
- Mismatched passwords and invalid email addresses are rejected.
- A stale CSRF token is rejected.
- Sign-out with an invalid CSRF token returns `400` and keeps the session.
- Sign-out with a valid CSRF token redirects to login and ends the session.
- A wrong password is rejected.
- A correct password signs the user in and redirects to the dashboard.
- A second account can register and sees only its own item.
- The first account does not see the second account's item.

Result: **51 passed, 0 failed, 0 skipped.**

## 8. Responsive browser check

The registration, sign-in, and dashboard pages were opened in Google Chrome
157.0.8103.38 through the Chrome DevTools protocol at two viewport sizes.

At 360 x 800:

- `document.documentElement.scrollWidth` equalled `window.innerWidth` on all three pages (no horizontal overflow).
- The mobile brand was visible and the story panel was hidden on the auth pages.
- The sign-in form width was 324 px inside a 360 px viewport.
- The dashboard item grid used a single column.

At 1280 x 800:

- `document.documentElement.scrollWidth` equalled `window.innerWidth`.
- The story panel was visible and the mobile brand was hidden.
- The dashboard item grid used three columns.

The dashboard check signed in with the local demo account. The demo account and
its items are development fixtures.

Result: **13 passed, 0 failed.**

## 9. Deployment checks

| Check | Result |
| --- | --- |
| `systemctl is-active apache2` | `active` |
| `systemctl is-active mysql` | `active` |
| `apache2ctl configtest` | `Syntax OK` (`AH00558` server-name warning is local and harmless) |
| `GET /` | `200` |
| Anonymous `GET /home.php` | `302` to `login.php` |
| `GET /config/db.php` | `403` |
| `GET /schema.sql` | `403` |
| `GET /docs/database-setup.md` | `403` |
| `GET /apache/northstar.conf` | `403` |
| Security headers on dynamic response | Present |
| Security headers on static CSS response | Present |

The Apache virtual host serves `/var/www/northstar`, denies application config,
docs, Apache config, tests, dotfiles, `schema.sql`, and `README.md`, and sets the
browser security headers.

## 10. Known limitations and follow-up work

- The browser layout check was run manually with Chrome and `puppeteer-core`; it is not part of `tests/run.sh`.
- Node.js was supplied from a temporary binary for the JavaScript syntax check; the suite skips that check when `node` is unavailable.
- No CI pipeline is configured. `tests/run.sh` is the intended CI entry point.
- The demo account and sample items must be removed or replaced before production.
- Rate limiting, email verification, password reset, multi-factor authentication, centralised audit logging, backups, and monitoring remain out of scope.
- The application is a read-only dashboard; item CRUD is not implemented.
- The deployment targets a single Ubuntu/Debian host with Apache `mod_php` and a local MySQL server.

## 11. Reproduction notes

1. Run `./scripts/setup-local.sh` on an Ubuntu/Debian machine with Apache, PHP, and MySQL installed.
2. Open <http://localhost/> and confirm the registration page loads.
3. Run `./tests/run.sh`.
4. Optionally install Node.js (`sudo apt install nodejs`) to enable the JavaScript syntax check.
5. For a browser check, open the site at a narrow viewport and confirm there is no horizontal scrolling.
