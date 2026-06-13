FROM php:8.4-apache

ARG SERVICE_DIR=task-service
ARG FRONT_CONTROLLER=task_index.php

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:8080>
    ServerName localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options -Indexes
        AllowOverride None
        Require all granted
        FallbackResource /${FRONT_CONTROLLER}
    </Directory>

    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>
EOF

COPY ${SERVICE_DIR}/public/ /var/www/html/public/
COPY ${SERVICE_DIR}/src/ /var/www/html/src/

RUN rm -f /var/www/html/src/*_config_local.php \
    && mkdir -p /var/www/html/logs /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && chown -R www-data:www-data /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2

USER www-data
WORKDIR /var/www/html
EXPOSE 8080

CMD ["apache2-foreground"]
