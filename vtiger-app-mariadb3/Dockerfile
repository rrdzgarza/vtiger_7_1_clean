FROM php:8.1-apache

# 1. Install dependencies
# Eliminamos el FIX de Buster porque 7.4-apache usa Bullseye por defecto
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libc-client-dev \
    libkrb5-dev \
    libcurl4-openssl-dev \
    libgmp-dev \
    libicu-dev \
    nano \
    vim \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure and install PHP extensions
# NOTA: En PHP 7.4+ los flags de GD cambiaron:
# --with-freetype-dir pasa a --with-freetype
# --with-jpeg-dir pasa a --with-jpeg
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo_mysql \
    soap \
    zip \
    imap \
    bcmath \
    exif \
    gmp \
    intl

# 3. Enable Apache Rewrite
RUN a2enmod rewrite

# 4. PHP Configuration
RUN echo "date.timezone = America/Monterrey" > /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "log_errors = Off" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "short_open_tag = Off" >> /usr/local/etc/php/conf.d/vtiger.ini

# 5. System Timezone
RUN ln -snf /usr/share/zoneinfo/America/Monterrey /etc/localtime && echo America/Monterrey > /etc/timezone

# 6. Apache Configuration
RUN echo '<Directory /var/www/html/>\n\
    Options +FollowSymlinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>' > /etc/apache2/conf-available/vtiger.conf \
    && a2enconf vtiger

WORKDIR /var/www/html

# 8. Copy Maintenance Tools
RUN mkdir -p /usr/src/vtiger-tools
COPY recalculate.php /usr/src/vtiger-tools/
COPY test_debug.php /usr/src/vtiger-tools/
COPY debug_module.php /usr/src/vtiger-tools/

# 9. Entrypoint
COPY ./docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
COPY ./docker-entrypoint-init.d /docker-entrypoint-init.d
COPY ./WebUI.php /docker-entrypoint-init.d/
RUN chmod +x /docker-entrypoint-init.d/*.sh

ENTRYPOINT ["docker-entrypoint.sh"]
EXPOSE 80