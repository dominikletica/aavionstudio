# Web server configuration

Status: Draft  
Updated: 2025-10-30

Correct web server configuration keeps sensitive files out of reach and ensures every request flows through `public/index.php`. aavion Studio is optimised for a rewrite-first setup: point your document root at the `public/` directory and enable URL rewrites. The repository ships fallback configuration files for shared hosting, but they should be a last resort.

## At a glance

| Scenario | Recommended action |
|----------|-------------------|
| Apache with full control | Set `DocumentRoot` to `<project>/public`, enable `mod_rewrite`, allow `.htaccess` in `public/` |
| Apache shared hosting | Upload the bundled `.htaccess` files; the root file blocks protected paths and forwards to `public/` |
| nginx | Point `root` to `<project>/public`; forward PHP requests to `php-fpm`; deny access to non-public paths |
| IIS | Use the bundled `web.config` files; prefer configuring the site root to `public/` |

## Apache

### Preferred setup (rewrite-first)

1. Enable the rewrite module:
   ```bash
   a2enmod rewrite
   systemctl restart apache2
   ```
2. Configure your virtual host:
   ```apache
   <VirtualHost *:80>
       ServerName example.com
       DocumentRoot /var/www/aavionstudio/public

       <Directory /var/www/aavionstudio/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
3. Deploy the project. The bundled `public/.htaccess` file rewrites any non-file request to `index.php`.

### Shared hosting fallback

If you cannot change the document root, keep the project at the repository root and rely on the supplied `.htaccess` files:

- `/.htaccess` blocks access to non-public directories and forwards requests to `public/`.
- `public/.htaccess` provides the front-controller rewrite.

The installer will display a compatibility warning until you migrate to the rewrite-first setup.

## nginx

Add a server block similar to:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/aavionstudio/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
    }

    location ~ ^/(config|src|vendor|var|migrations|tests|docs|modules|assets|templates|translations|bin)/ {
        deny all;
    }

    location ~ /\. {
        deny all;
    }
}
```

Reload nginx after updating the configuration. Ensure the PHP-FPM socket path matches your system.

## IIS

- When possible, set the site to use `<project>\public` as the physical path. The bundled `public\web.config` handles the routing.
- For shared hosting where the root cannot be changed, keep the project at the repository root and include the bundled `web.config`. It denies access to sensitive directories and rewrites requests to `public\`.

Confirm the **URL Rewrite** module is installed and restart IIS after publishing the files.

## Hardening checklist

- Remove write permissions from everything except `var/` and `public/assets/` on production.
- Keep the compatibility loader (`index.php`) only for hosts that cannot point the docroot to `public/`.
- After switching to rewrite-first hosting, delete the root `.htaccess` / `web.config` files if your platform still serves them.
- Run the installer diagnostics at `/setup` to verify rewrite status. The “Docroot & rewrite status” panel should report `enabled`.
