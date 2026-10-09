# Render deploy image for VoltTech (PHP + Apache).
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite pdo_mysql \
    && a2enmod headers rewrite \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/volttech.conf \
    && a2enconf volttech \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .

# The free Render plan uses the container's temporary filesystem. Apache must
# be able to create the fresh SQLite file in data/ at startup.
RUN chown -R www-data:www-data /var/www/html/data

ENV VOLTTECH_CONFIG=/var/www/html/config/render_db_credentials.php
EXPOSE 10000

# Render provides PORT at runtime. Apache otherwise listens on port 80.
CMD ["sh", "-c", "port=${PORT:-10000}; sed -ri \"s/^Listen .*/Listen ${port}/\" /etc/apache2/ports.conf; sed -ri \"s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/\" /etc/apache2/sites-enabled/000-default.conf; exec apache2-foreground"]
