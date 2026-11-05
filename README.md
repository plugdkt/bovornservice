# Bovorn Service V2

Billing system for vehicle tax, compulsory insurance (พ.ร.บ.) and inspection (ตรอ.) services — two branches (ดอกคำใต้, ท่าวังทอง). PHP 7.4+/8.x with PDO and MySQL/MariaDB.

## Setup

1. Copy `V2/config.example.php` to `V2/config.local.php` and set the database credentials.
   `config.local.php` is git-ignored; never commit real passwords.
2. Point the web server at this folder and open `/Bovorn/V2/` (or upload `V2/` as the site folder).

## Local development with Docker

```bash
docker compose up -d --build
```

- V2: http://localhost:8080/Bovorn/V2/
- phpMyAdmin: http://localhost:8081/

On first start `docker/initdb/01-import.sh` imports SQL dumps from `database/`. The dumps contain customer data and are **not** in this repository; place them in `database/` locally.

## Features

- Sales entry with customer lookup by licence plate (spaces/dashes ignored) or by name, auto-filling the customer and vehicle fields.
- Receipts, daily tax-forwarding sheet, sales/inspection reports, manager tools.
