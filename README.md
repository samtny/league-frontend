league-frontend
===============
[![Build Status](https://travis-ci.org/samtny/league-frontend.svg?branch=master)](https://travis-ci.org/samtny/league-frontend) [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

### Summary

This app runs on the Laravel framework: https://laravel.com.

### Requirements

1. PHP >= 7.1.3
1. OpenSSL PHP Extension
1. PDO PHP Extension
1. Mbstring PHP Extension
1. Composer
1. NPM 10.15.3

### Local Development

1. composer install
1. npm install
1. npm run dev
1. copy .env.sqlite.example to .env and modify
1. use e.g. "openssl enc -aes-128-cbc -k secret -P -md sha1" to generate a suitable APP_KEY
1. php artisan migrate
1. php artisan db:seed
1. php artisan storage:link
1. php artisan serve
