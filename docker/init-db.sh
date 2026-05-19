#!/bin/bash
set -e

mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld

if [ ! -d "/var/lib/mysql/mysql" ]; then
    mkdir -p /var/lib/mysql
    chown -R mysql:mysql /var/lib/mysql
    mysql_install_db --user=mysql
fi

# 以跳过权限表方式启动 mysqld
mysqld --user=mysql --skip-grant-tables --skip-networking &
mysql_pid=$!

for i in $(seq 1 30); do
    if mysqladmin ping --silent 2>/dev/null; then
        break
    fi
    sleep 1
done

mysql -u root <<EOF
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\`;
FLUSH PRIVILEGES;
EOF

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" < /var/www/html/backend/database.sql

kill $mysql_pid
wait $mysql_pid
