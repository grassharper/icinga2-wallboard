# icinga2-wallboard
Icinga2 status wallboard for operations room

Make use of Icinga2's API to display status. Suitable for data room screens.

Inspired by Naglite3 (https://github.com/saz/Naglite3) and Icinga2 API example (https://github.com/Icinga/icinga2-api-examples/blob/master/scripts/objects/services/problems.php)

## screenshots

![ScreenShot](https://i.postimg.cc/BnPQGnt2/wallboard-clean.png)

</br > 
</br >

![ScreenShot](https://i.postimg.cc/0Ng551JF/wallboard-problems.png)

## installation

Steps:
1. git clone git://github.com/grassharper/icinga2-wallboard.git or __*Download ZIP*__ and extract it to some desired location.
2. Copy config.php.example to config.php. Edit the config and add your Icinga2 host, credentials and the custom heading you require.
3. Open a browser and point it to your installation.

```
$ mkdir /usr/local/www 
$ cd /usr/local/www
$ git clone https://github.com/grassharper/icinga2-wallboard.git wallboard
$ cd wallboard
$ cp config.php.sample config.php
$ # Change ApiHost, ApiUser and ApiPass to match your credentials.
$ vim config.php 
```

In order to configure a new API user you’ll need to add a new ApiUser configuration object in /etc/icinga2/conf.d/api-users.conf:
```
# vim /etc/icinga2/conf.d/api-users.conf

object ApiUser "root" {
  password = "icinga"
}
```

NGINX and PHP-FPM:
```
$ cat monitoring.conf 
server {
    listen 127.0.0.1:80;
    server_name <your_host>;

    location /wallboard {
      alias /usr/local/www/wallboard;
      index index.php;
      location ~* "\.php$" {
        try_files	$uri =404;
        fastcgi_pass	127.0.0.1:9000;
        fastcgi_param	SCRIPT_FILENAME $request_filename;
        include		fastcgi_params;
      }
    }
}
```
