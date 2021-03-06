#!/bin/bash
# Vagrant bootstrap file

set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

# Add mariadb 10.0 deb repo
apt-get -y install software-properties-common
apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0xcbcb082a1bb943db
add-apt-repository 'deb http://nyc2.mirrors.digitalocean.com/mariadb/repo/10.0/ubuntu trusty main'

apt-get update
apt-get -y install nginx-extras imagemagick libav-tools php5 php5-cli php5-curl php5-imagick php5-geoip php5-gd php5-fpm redis-server mariadb-server mariadb-client php5-mysql php5-redis graphicsmagick gifsicle libimage-exiftool-perl

# Make sure any imported database is utf8mb4
# http://mathiasbynens.be/notes/mysql-utf8mb4
# Put in /etc/mysql/conf.d/local.cnf
cat - <<EOF123 >/etc/mysql/conf.d/local.cnf
[client]
default-character-set = utf8mb4

[mysql]
default-character-set = utf8mb4

[mysqld]
character-set-client-handshake = FALSE
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
default-storage-engine = innodb
EOF123

mysql -uroot -e \
"CREATE DATABASE IF NOT EXISTS tinyboard; \
GRANT USAGE ON *.* TO tinyboard IDENTIFIED BY ''; \
GRANT ALL PRIVILEGES ON tinyboard.* TO tinyboard; \
FLUSH PRIVILEGES;"

sed \
  -e 's/post_max_size = .*/post_max_size = 15M/' \
  -e 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' \
  -e 's/upload_max_filesize = .*/upload_max_filesize = 15M/' \
  -i /etc/php5/fpm/php.ini

#
# vagrant/development specific stuff follows
#

# Make redis open to connections not from localhost
sed \
  -e 's/^bind/#bind/' \
  -i /etc/redis/redis.conf

# Make mysql open to connections not from localhost
cat - <<EOF123 >/etc/mysql/conf.d/open.cnf
[mysqld]
bind-address = 0.0.0.0
EOF123

service redis-server restart &
service mysql restart &
service php5-fpm restart &
wait

install -m 775 -o www-data -g www-data -d /var/www
ln -sf \
  /vagrant/core/*.php \
  /vagrant/core/js/ \
  /vagrant/core/static/ \
  /vagrant/core/stylesheets/ \
  /vagrant/core/*.md \
  /vagrant/core/offline-page.html \
  /vagrant/core/LICENSE-Macil.txt \
  /vagrant/core/install.sql \
  /var/www/
install -m 775 -o www-data -g www-data -d /var/www/templates
install -m 775 -o www-data -g www-data -d /var/www/templates/cache
ln -sf \
  /vagrant/core/templates/* \
  /var/www/templates/
if ! [ -d /var/www/inc ]; then
  install -m 775 -o www-data -g www-data -d /var/www/inc
  ln -sf \
    /vagrant/core/inc/* \
    /var/www/inc/
  rm -f /var/www/inc/instance-config.php

  # Place default vagrant instance-config.php
  cp /vagrant/vagrant/instance-config.php /var/www/inc/
  chown www-data /var/www/inc/instance-config.php

  # Some default settings for vagrant vm
  ln -s /vagrant/vagrant/site-config.php /var/www/inc/
fi

# VirtualBox shared folders don't play nicely with sendfile.
sed \
  -e 's/sendfile on;/sendfile off;/' \
  -i /etc/nginx/nginx.conf

rm -f /etc/nginx/sites-enabled/* /etc/nginx/sites-available/tinyboard.nginx
# tinyboard.nginx can't be a symlink into /vagrant because nginx starts before
# /vagrant is mounted.
cp /vagrant/vagrant/tinyboard.nginx /etc/nginx/sites-available/
ln -sf /etc/nginx/sites-available/tinyboard.nginx /etc/nginx/sites-enabled/
service nginx restart

echo
echo "Server set up, please browse to http://172.27.0.2/install.php"
echo "to complete the installation. Default database settings will work."
echo "After you complete the installation steps, go to "
echo "http://172.27.0.2/mod.php and log in as admin:password."
