# Shop engineer database interface

Entry: `https://shop.huiyitea.vn/engineer-db/` (or `index.php` in that directory).

This standalone wrapper connects only to server `localhost`, database account `huiyitea_shop_huiyi_it`, and database `huiyitea_shop`. Enter that database account's password yourself. The FTP identity `huiyi.it@shop.huiyitea.vn` and ordering-administrator accounts are separate; their passwords are not used here.

The wrapper does not load application configuration, prefill a password, grant privileges, or run database migrations. It retains Adminer's authentication, encrypted session credentials and CSRF validation. Its `adminer_sid` cookie is independent from the ordering application's session and scoped to the current engineer entry path. Both the session and `adminer_key` encryption cookies use Secure/HttpOnly. Permanent login is disabled. HTTPS must be indicated by the origin's HTTPS environment or TLS port; arbitrary forwarded/Cloudflare headers cannot bypass the check.

The account's database grants are the security boundary for SQL commands, including statements which name a database explicitly. Before enabling the public entry, confirm that the dedicated user has privileges only on `huiyitea_shop`. The requested working privileges are SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX; grants must be reviewed separately in cPanel. The wrapper does not promise to sandbox arbitrary SQL independently of those grants. Back up shop before schema/data changes and keep backups outside its public website. Never apply these operations to order.

Deploy the whole `engineer-db/` directory preserving both `.htaccess` files. Apache 2.4 must enforce these rules: `index.php` is the only public file, directory listings are disabled, and the vendor directory is denied. Verify direct requests for `scope.php`, README, the vendor PHP, and license files are denied before handing over access. The official unauthenticated login form can use HTTP 403 while rendering the password/CSRF form; this is not a successful database login. Public plaintext HTTP requests are rejected before invoking Adminer. No deployment or real database login is claimed by the local tests.

Pinned official vendor: **Adminer 6.1.2, MySQL-only multilingual**, released 2026-10-10, 434,129 bytes. The PHP file is unmodified. SHA256:

`30d600b5d8750600b7cf7d8e4652f27d3bcd23ca6c9e1e6399a9b4a274fd7c0e`

This digest was also matched to the official GitHub release asset digest. The entry checks it before inclusion. Updating the vendor requires reviewing its API/authentication changes, updating the pinned digest, and rerunning the scope, compiled-vendor, HTTPS-cookie, and source-denial checks. Delete the interface when it is no longer needed.

Sources: [official Adminer release](https://github.com/vrana/adminer/releases/tag/v6.1.2), [exact vendor download](https://github.com/vrana/adminer/releases/download/v6.1.2/adminer-6.1.2-mysql.php), [official customization API](https://www.adminer.org/en/extension/), [versioned authentication source](https://github.com/vrana/adminer/blob/v6.1.2/adminer/include/auth.inc.php), [versioned session source](https://github.com/vrana/adminer/blob/v6.1.2/adminer/include/bootstrap.inc.php).

Local verification uses synthetic inputs and no real database credentials:

```text
php tests/engineer_db_guards.php
php -d extension=pdo_mysql tests/engineer_db_vendor.php
```

The disposable loopback HTTP harness belongs under `.work/`, must never be deployed, and simulates the origin TLS environment solely to inspect the actual vendor login form and security headers. Apache source denial and real HTTPS/database authentication require separate deployment verification.
