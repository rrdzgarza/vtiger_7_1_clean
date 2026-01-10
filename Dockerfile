FROM php:7.2-apache

# 1. Install dependencies for extensions
# FIX: Debian Buster is EOL, so we must use archive repositories
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
    libcurl4-openssl-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql soap zip imap mbstring curl xml json

# 3. Enable Apache Rewrite
RUN a2enmod rewrite

# 4. PHP Configuration (Mimicking user's manual settings)
RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "log_errors = Off" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "short_open_tag = Off" >> /usr/local/etc/php/conf.d/vtiger.ini

# 5. Workdir
WORKDIR /var/www/html

# 6. Copy Maintenance Tools (to a safe location first)
# We copy them to /usr/src/vtiger-tools so the entrypoint can restore them
# even if /var/www/html is overwritten by a volume or manual copy
RUN mkdir -p /usr/src/vtiger-tools
COPY recalculate.php /usr/src/vtiger-tools/
COPY test_debug.php /usr/src/vtiger-tools/

# 7. Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
EXPOSE 80
