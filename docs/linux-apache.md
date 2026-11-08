# Linux Apache Setup

## Current machine

The development machine already has Apache 2.4, PHP 8.3, the Apache PHP module,
and PDO MySQL installed. Apache is running. The project source lives under
`/home/kazim/northstar`, but `/home/kazim` is private to the login user, so the
Apache service cannot read it directly. The configured web root is
`/var/www/northstar`.

## Install prerequisites on another Ubuntu/Debian machine

Run these commands in a terminal with sudo access:

```sh
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-mysql
sudo systemctl enable --now apache2
```

MySQL/MariaDB and its database will be configured in a later setup step.

## Deploy and enable the site

From the project checkout, copy the files into Apache's web root. Do not copy
the Git metadata. Repeat this after application updates.

```sh
sudo install -d -o root -g www-data -m 0755 /var/www/northstar
sudo rsync -a --exclude='.git' /home/kazim/northstar/ /var/www/northstar/
sudo chown -R root:www-data /var/www/northstar
sudo find /var/www/northstar -type d -exec chmod 0755 {} +
sudo find /var/www/northstar -type f -exec chmod 0644 {} +
sudo install -m 0644 /var/www/northstar/apache/northstar.conf /etc/apache2/sites-available/northstar.conf
sudo a2ensite northstar.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

The site config denies direct HTTP access to application configuration, Git
metadata, documentation, the SQL schema, and dotfiles. `Options -Indexes` also
prevents directory listings.

Open `http://localhost/` after the application files have been deployed. To use
`http://northstar.local/`, add `127.0.0.1 northstar.local` to the machine's
hosts file.

## Verify Apache and PHP

```sh
systemctl is-active apache2
apache2ctl -M | grep -E 'php|mpm_prefork'
php -m | grep -Ei 'PDO|pdo_mysql'
```

Do not put database passwords in this repository. Apache database environment
configuration and MySQL schema setup are separate steps.