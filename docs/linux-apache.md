# Linux and Apache Setup

Northstar targets Apache 2.4 with PHP 8.0+ and `mod_php`. The application source
may live in a private home directory, so the deployment copies it into Apache's
web root at `/var/www/northstar` and serves it from there.

## Reference environment

The verification environment is Ubuntu 24.04 with:

| Component | Version |
| --- | --- |
| Apache | 2.4.58 |
| PHP | 8.3.6 |
| PHP module | `libapache2-mod-php` with `php_module` |
| PDO driver | `pdo_mysql` |
| MySQL | 8.0.46 |

## Automated setup

From the repository root, run:

```sh
./scripts/setup-local.sh
```

The script is safe to re-run. It provisions the database, writes the Apache
environment file, deploys the application, enables the site, and reloads Apache.
It must be run as your normal user; it invokes `sudo` when needed. Review
`scripts/setup-local.sh` and `scripts/deploy.sh` before running them.

After it finishes, open <http://localhost/>.

## Install prerequisites on another Ubuntu/Debian machine

```sh
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-mysql mysql-server rsync curl openssl
sudo systemctl enable --now apache2
```

Optional: install Node.js if you want `tests/run.sh` to perform the JavaScript
syntax check:

```sh
sudo apt install nodejs
```

## Manual deployment

These are the steps performed by `scripts/deploy.sh`. The database environment
file must already exist; see [database-setup.md](database-setup.md).

```sh
sudo install -d -o root -g www-data -m 0755 /var/www/northstar
sudo rsync -a --delete \
  --exclude='.git' \
  --exclude='scripts' \
  --exclude='tests' \
  /home/kazim/northstar/ /var/www/northstar/
sudo chown -R root:www-data /var/www/northstar
sudo find /var/www/northstar -type d -exec chmod 0755 {} +
sudo find /var/www/northstar -type f -exec chmod 0644 {} +
sudo install -m 0644 /home/kazim/northstar/apache/northstar.conf \
  /etc/apache2/sites-available/northstar.conf
sudo a2enmod headers
sudo a2ensite northstar.conf
sudo a2dissite 000-default.conf
sudo a2enconf northstar-env
sudo apache2ctl configtest
sudo systemctl reload apache2
```

The virtual host denies direct HTTP access to application configuration,
documentation, tests, the SQL schema, the Apache configuration, dotfiles, and
`README.md`. `Options -Indexes` also prevents directory listings. The application
itself must remain readable by `www-data`.

Open <http://localhost/> after deployment. To use
`http://northstar.local/`, add this line to `/etc/hosts`:

```text
127.0.0.1 northstar.local
```

## Verify Apache and PHP

```sh
systemctl is-active apache2
apache2ctl -M | grep -E 'php|headers|mpm_prefork'
php -m | grep -Ei 'pdo|pdo_mysql'
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost/
```

The last command should return `200`. Anonymous access to `/home.php` should
return a redirect to `login.php`:

```sh
curl -sS -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost/home.php
```

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `403 Forbidden` for every page | Confirm `/var/www/northstar` is readable by `www-data` and the virtual host points at it. |
| `Database configuration is incomplete` | Confirm `/etc/apache2/conf-available/northstar-env.conf` is enabled and contains all five `DB_*` variables. |
| Blank page | Check `/var/log/apache2/northstar_error.log` and PHP error logging. |
| Unstyled page | Confirm `assets/style.css` returns `200`. |
| Apache will not reload | Run `sudo apache2ctl configtest` and fix the reported directive before reloading. |
| `AH00558` server-name warning | It is harmless locally; set a global `ServerName` if desired. |

Do not place real database passwords in this repository. Apache environment
configuration and MySQL schema setup are separate, local steps.
