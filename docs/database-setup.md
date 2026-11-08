# MySQL Setup

The application requires PHP 8.0+ with PDO MySQL and a MySQL 5.7+ or MariaDB
10.3+ database. It reads `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and
`DB_PASSWORD` from the PHP process environment. Credentials are not stored in
this repository.

## Create the database and application account

On Ubuntu/Debian, open an administrative MySQL session:

```sh
sudo mysql
```

Run the following SQL there, replacing the example password with a unique,
long random value. Do not use the example text as a real password.

```sql
CREATE USER 'northstar_app'@'127.0.0.1'
    IDENTIFIED BY 'replace-with-a-long-random-password';
GRANT SELECT, INSERT ON web_project_db.* TO 'northstar_app'@'127.0.0.1';
```

Exit MySQL and import the schema as an administrator. The schema creates
`web_project_db`, its tables and indexes, and local demo records.

```sh
sudo mysql < /home/kazim/northstar/schema.sql
```

The sample account is for local verification only. Remove or replace it before
production use. The application database account is limited to the `SELECT`
and `INSERT` operations required by this release.

## Run the PHP development server

Stop any existing development server with `Ctrl+C`. In the same terminal, set
the connection settings and enter the database password without echoing it or
placing it in shell history:

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

Then open `http://127.0.0.1:8000/signin.php`. The settings must be present in
the environment of the PHP server process; setting them in a different
terminal will not update an already-running server.

## Configure Apache

For Apache with mod_php, create a root-owned environment file outside the Git
checkout:

```sh
sudoedit /etc/apache2/conf-available/northstar-env.conf
```

Add the five `SetEnv` directives below, replacing the example password locally:

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