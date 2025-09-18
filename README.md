# Pimcore Test Project

This repository contains a ready-to-run Pimcore project with Docker. Follow the steps below to start it locally and to use the custom console command for importing products.

## Prerequisites
- Docker Desktop (or Docker Engine) and Docker Compose v2
- Git

## Quick start (Docker)

1) Clone the repository
```bash
git clone https://github.com/Mister-twisteR/pimcore-test.git
cd pimcore-test
```

2) Start the containers (db, php, nginx, etc.)
```bash
docker compose up -d
```
This will expose the site on http://localhost (nginx listens on port 80).

3) Install PHP dependencies
```bash
docker compose exec php composer install
```

4) Install Pimcore (database schema, admin user, assets, etc.)

Interactive install:
```bash
docker compose exec php vendor/bin/pimcore-install
```

MySQL connection is preconfigured via docker-compose (host: db, db: pimcore, user: pimcore, pass: pimcore). If prompted, use these values.

5) Rebuild data object classes
```bash
docker compose exec php bin/console pimcore:deployment:classes-rebuild
```

6) Open the app
- Frontend: http://localhost/
- Admin: http://localhost/admin (use the admin credentials you set during install)

## Product class
Product class I've created using an admin panel. It has the next fields:
- name (string, required)
- gtin (integer, required, unique) - is used as the key for the product
- image (relation field with an asset type)
- date (date field)

I've extended Product class and overrode the setName method to make sure that the name is always saved in uppercase. (/src/Model/DataObject/Product.php)

## Product import command
This project provides a console command to import products from a JSON URL, as per the task requirements.

Usage:
```bash
docker compose exec php bin/console app:products:import "https://example.com/products.json"
```
Expected JSON structure:
```json
{
  "products": [
    {
      "name": "product",
      "gtin": "123456789012",
      "image": "/path/to/asset.jpg",
      "date": "2024-03-25"
    }
  ]
}
```

Notes:
- command accepts URL or local file path as an argument
- validating json structure
- checking for required fields; if any is missing, the product is skipped
- checking for existing product by gtin; if it exists, we update it; otherwise, we create a new one
- gtin is treated as the key; non-digits are stripped before storing into the numeric field
- name is always saved in UPPERCASE on import
- date is parsed and stored as a Date object
- image: if it is an external URL, the command downloads it, creates an Asset under /product-images, and links it; if it is a Pimcore asset path, it links it directly


## Stopping the stack
```bash
docker compose down
```

