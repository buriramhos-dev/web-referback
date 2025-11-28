# ใช้ Base Image ที่มี FPM
FROM php:8.2-cli-alpine

# ติดตั้ง Dependencies และ pdo_mysql
RUN apk add --no-cache mysql-dev \
    && docker-php-ext-install pdo_mysql \
    && apk del mysql-dev

# คัดลอก custom.ini (ถ้ามี)
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
COPY . .

# รัน PHP built-in server บนพอร์ตที่ Railway กำหนดผ่าน $PORT (fallback เป็น 8080)
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]