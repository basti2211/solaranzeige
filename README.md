Solaranzeige PHP source code

Installation
* `sudo apt update && sudo apt upgrade`
* `sudo apt install php php-xml package php-curl`
* `sudo cp html /var/www/.`
* Create empty files under `/var/www/log/solaranzeige.log`, `/var/www/log/wartung.log`
* Change permissions `sudo chown www-data:www-data /var/www/log/*log`
* Apply settings in crontab from `sample_crontab`
* reboot
