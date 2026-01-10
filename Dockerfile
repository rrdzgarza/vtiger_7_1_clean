FROM php:7.2-apache

# 1. Install dependencies and extensions
# Added libc-client-dev and libkrb5-dev for IMAP support
# FIX: Debian Buster is EOL, so we switch to archive.debian.org
RUN echo "deb http://archive.debian.org/debian buster main" > /etc/apt/sources.list \
  && echo "deb http://archive.debian.org/debian-security buster/updates main" >> /etc/apt/sources.list \
  && apt-get --allow-releaseinfo-change update \
  && apt-get install -y \
  libpng-dev \
  libjpeg-dev \
  libxml2-dev \
  libzip-dev \
  libfreetype6-dev \
  libc-client-dev \
  libkrb5-dev \
  wget \
  unzip \
  && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
  && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
  && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql soap zip imap mbstring

# Enable Apache Rewrite Module
RUN a2enmod rewrite

# 2. Configure PHP for Vtiger and SSL Fix
# 'short_open_tag = Off' is standard, but Vtiger usually works fine.
# Crucially, we auto_prepend the SSL fix so Vtiger thinks it's strictly on HTTPS if Traefik says so.
COPY vtiger-ssl-fix.php /usr/local/etc/php/vtiger-ssl-fix.php
RUN echo "auto_prepend_file = /usr/local/etc/php/vtiger-ssl-fix.php" > /usr/local/etc/php/conf.d/vtiger-ssl-fix.ini \
  && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/vtiger-recommended.ini \
  && echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/vtiger-recommended.ini \
  && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/vtiger-recommended.ini \
  && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/vtiger-recommended.ini \
  && echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/vtiger-recommended.ini

# 3. Download and Extract Vtiger 7.1
WORKDIR /var/www/html
RUN wget "https://sourceforge.net/projects/vtigercrm/files/vtiger%20CRM%207.1.0/Core%20Product/vtigercrm7.1.0.tar.gz/download" -O vtiger.tar.gz \
  && tar -xzf vtiger.tar.gz \
  && cp -r vtigercrm/* . \
  && cp htaccess.txt .htaccess \
  && rm -rf vtigercrm vtiger.tar.gz

# 4. Set Permissions
RUN chown -R www-data:www-data /var/www/html \
  && chmod -R 755 /var/www/html

# 5. Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80