league-frontend
===============
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

### Summary

This app runs on the Laravel framework: https://laravel.com.

### Requirements

1. PHP >= 8.2
1. OpenSSL PHP Extension
1. PDO PHP Extension
1. Mbstring PHP Extension
1. Composer
1. Node.js (see .nvmrc)

### Local Development

1. composer install
1. npm install
1. npm run dev
1. copy .env.sqlite.example to .env and modify
1. touch database/database.sqlite
1. php artisan migrate
1. php artisan db:seed
1. php artisan key:generate
1. php artisan storage:link
1. php artisan serve

### Testing

1. vendor/bin/phpunit -c phpunit.xml
1. npm run test:a11y
