Solaranzeige PHP source code

Installation
* `sudo apt update && sudo apt upgrade`
* `sudo apt install php php-xml php-curl`
* `sudo apt install influxdb influxdb-client`
* `sudo cp html /var/www/.`
* Create empty files under `/var/www/log/solaranzeige.log`, `/var/www/log/wartung.log`
* Change permissions `sudo chown www-data:www-data /var/www/log/*log`
* Change `html/1.user.config.php` and `html/2.user.config.php` to your needs
* Create influx databases: 
```
influxdb
> create database solaranzeige
> exit
```
* Apply settings in crontab from `sample_crontab`
* reboot
