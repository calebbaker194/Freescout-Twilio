# Freescout-Twilio
A freescout integration for Twilio it works very similar to the facebook module. It allows you to recive texts and respond to them in the standart ticket system

# Install
This module installs like many of the other custom modules. The goal is to download the code place it into the /var/www/html/Modules/ directory and change the ownership and group to www-data. you can then configure your Twilio credentials the same way the facebook module works. 

Generall Guidelines 
```
git clone https://github.com/calebbaker194/Freescout-Twilio.git
sudo mkdir /var/www/html/Modules/Twilio
sudo cp ./Freescout-Twilio/* /var/www/html/Modules/Twilio/ -r
sudo chown www-data /var/www/html/Modules/Twilio -R
sudo chgrp www-data /var/www/html/Modules/Twilio -R
sudo chmod 755 /var/www/html/Modules/Twilio -R
```
