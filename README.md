# Pimcore Test Project

## Getting started

### Create database

- Create a database for your project
- Create a user for your database
- Grant all privileges to your user

```bash

mysql -u root -p -e "CREATE DATABASE pimcore";
mysql -u root -p -e "CREATE USER 'my_user'@'localhost' IDENTIFIED BY 'pimcore_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON pimcore.* TO 'my_user'@'localhost';"
```

### Install Pimcore

```bash
COMPOSER_MEMORY_LIMIT=-1 composer create-project pimcore/skeleton my-project
cd ./my-project
./vendor/bin/pimcore-install
```

- Point your virtual host to `my-project/public`
- [Only for Apache] Create `my-project/public/.htaccess` according to https://pimcore.com/docs/platform/Pimcore/Installation_and_Upgrade/System_Setup_and_Hosting/Apache_Configuration/ 
- Open https://your-host/admin in your browser
- Done! 😎

```bash
./bin/console pimcore:deployment:classes-rebuild
```

