# MySql Backup

Database Daily Backup, moved to AWS S3 And Update Google Sheet

### Installing
Clone repository and install dependencies with composer.
```
git clone git@github.com:ianautiyal/mysql-backup.git .
composer install
cp .env-sample .env
```

Update credentials and other info in .env

### Running App

```
chmo +x ./databasebackup.sh
./databasebackup.sh
```

## Authors

* [**Ajay Nautiyal**](https://github.com/ianautiyal)
