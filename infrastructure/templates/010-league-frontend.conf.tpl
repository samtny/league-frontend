<VirtualHost *:80>

    ServerAdmin webmaster@${league_frontend_url}
    DocumentRoot ${league_frontend_docroot}/public/
    ServerName ${league_frontend_url}
    ServerAlias *.${league_frontend_url}
    RewriteEngine On
    RewriteOptions inherit

    <Directory "${league_frontend_docroot}/public/">
        Options +FollowSymLinks +Indexes
        AllowOverride All
        order allow,deny
        allow from all
        Require all granted
    </Directory>

    # Defense-in-depth: public/storage is the symlink target for user
    # uploads (storage/app/public). Even if an upload/zip-extraction bug
    # lets a malicious file land there, it must never be executable.
    <Directory "${league_frontend_docroot}/public/storage">
        <FilesMatch \.php$>
            SetHandler none
            Require all denied
        </FilesMatch>
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.5-fpm.sock|fcgi://localhost"
    </FilesMatch>

</VirtualHost>
