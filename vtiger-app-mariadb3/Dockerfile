FROM php:8.1-apache

# 1. Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
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
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure and install PHP extensions
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
    intl \
    opcache

# 3. Enable Apache Rewrite
RUN a2enmod rewrite headers expires

# 4. PHP Configuration
RUN echo "date.timezone = America/Monterrey" > /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "short_open_tag = Off" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/vtiger.ini

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
