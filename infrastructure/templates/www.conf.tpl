[www]
user = www-data
group = www-data

listen = /run/php/php8.5-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.allowed_clients = 127.0.0.1

pm = dynamic
pm.max_children = ${php_pm_max_children}
pm.start_servers = ${php_pm_start_servers}
pm.min_spare_servers = ${php_pm_min_spare_servers}
pm.max_spare_servers = ${php_pm_max_spare_servers}

catch_workers_output = yes
