FROM php:8.2-apache

RUN a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql

# 安装 MariaDB 和 Supervisor
RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        mariadb-server \
        supervisor \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html

ENV DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_NAME=confession_wall \
    DB_USER=root \
    DB_PASS=rootpass \
    JWT_SECRET=change-this-to-a-random-secret-in-production \
    MYSQL_ROOT_PASSWORD=rootpass \
    MYSQL_DATABASE=confession_wall

RUN mkdir -p /etc/supervisor/conf.d
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY docker/init-db.sh /usr/local/bin/init-db.sh
RUN chmod +x /usr/local/bin/init-db.sh

VOLUME /var/lib/mysql

EXPOSE 80 3306

CMD ["/bin/bash", "-c", "/usr/local/bin/init-db.sh && /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf"]
