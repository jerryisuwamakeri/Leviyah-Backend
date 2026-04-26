# Leviyah — Backend API

Laravel 11 REST API powering the Leviyah beauty & hair e-commerce platform.

## Requirements

- PHP 8.2+
- Composer
- MySQL or SQLite

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

## Features

- Dual-guard authentication (customers via Sanctum, staff via separate guard)
- Role-based access control (super_admin, admin, manager, cashier, support)
- Product catalog with variants (color, length)
- Cart with guest session isolation
- Orders, transactions, Paystack payment verification
- POS with QR code staff clock-in/out
- Promotions with auto price discounting
- Customer support chat
- Activity logging
- Admin dashboard analytics

## Tech Stack

- Laravel 11
- Laravel Sanctum
- Spatie Permission
- Spatie ActivityLog
