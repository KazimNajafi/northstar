# Northstar Tests

The test suite has three layers:

| File | What it checks |
| --- | --- |
| `unit.php` | Output escaping, request helpers, CSRF tokens, password hashing, error-message redaction, and session sign-in. |
| `integration.php` | MySQL uniqueness, prepared statements, password verification, account-scoped queries, ordering, SQL-injection resistance, and cascade deletion. |
| `http.sh` | Registration, login, dashboard protection, CSRF rejection, sign-out, validation errors, and multi-user isolation over HTTP. |

`run.sh` is the entry point. It also runs PHP syntax checks and an optional
JavaScript syntax check when Node.js is installed.

## Prerequisites

- PHP 8.0+ with `pdo_mysql`.
- MySQL/MariaDB running with `web_project_db` imported from `schema.sql`.
- The application deployed and reachable at `BASE_URL`.
- A test database account with `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on
  `web_project_db`.

`scripts/setup-local.sh` creates the test account and writes `tests/.env`.

## Configuration

The tests read `tests/.env` when it exists. It is ignored by Git. Copy the
example and edit it if you are not using the setup script:

```sh
cp tests/.env.example tests/.env
chmod 600 tests/.env
```

Required values:

| Variable | Purpose |
| --- | --- |
| `DB_HOST` | MySQL host. |
| `DB_PORT` | MySQL port. |
| `DB_NAME` | Database name, normally `web_project_db`. |
| `DB_USER` | Application account, used by the integration-test fallback. |
| `DB_PASSWORD` | Application account password. |
| `TEST_DB_USER` | Local test account. |
| `TEST_DB_PASSWORD` | Local test account password. |
| `BASE_URL` | Site address, for example `http://localhost`. |

You can also export these variables in the shell instead of using `tests/.env`.

## Run everything

```sh
./tests/run.sh
```

## Run one layer

```sh
php tests/unit.php
php tests/integration.php
bash tests/http.sh
```

## What the HTTP tests create

The HTTP suite uses the email namespace
`northstar.http-test.*@example.test` and unique usernames for each run. It deletes
those accounts at the start and end of the run when the test database
credentials are available. Items are removed by the `ON DELETE CASCADE`
constraint.

If `TEST_DB_USER` or `TEST_DB_PASSWORD` is missing, database-backed checks are
marked `SKIP`; the rest of the HTTP checks still run.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `Connection refused` or `200` instead of the expected page | Confirm Apache is running and `BASE_URL` is correct. |
| HTTP checks fail with `403` on the home page | Confirm the Apache document root and access rules. |
| Database checks are skipped | Confirm `tests/.env` exists and the test account can connect. |
| Test users remain in MySQL | Run the suite with a test account that has `DELETE`, or remove the test accounts manually. |
| JavaScript check is skipped | Install Node.js with `sudo apt install nodejs`. |
