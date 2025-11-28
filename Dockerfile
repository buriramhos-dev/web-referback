# ใช้ Base Image ที่มี FPM
FROM php:8.2-fpm-alpine 

# ติดตั้ง Dependencies และ pdo_mysql 
RUN apk add --no-cache mysql-dev \
    && docker-php-ext-install pdo_mysql \
    && apk del mysql-dev

# คัดลอก custom.ini (แก้ไข Socket)
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini 

WORKDIR /var/www/html
COPY . .

# ⭐ เปลี่ยน CMD: รัน PHP-FPM บนพอร์ต 9000
EXPOSE 9000
CMD ["php-fpm"]