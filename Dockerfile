# ใช้ PHP 8.2 FPM แบบ Alpine
FROM php:8.2-fpm-alpine

# ติดตั้ง extension สำหรับ MySQL
RUN docker-php-ext-install pdo_mysql

# ตั้ง working directory
WORKDIR /var/www/html

# คัดลอกไฟล์ project เข้า container
COPY . .

# ใช้ built-in PHP server ของ PHP สำหรับ Railway
# Railway จะตั้ง environment variable $PORT ให้อัตโนมัติ
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t /var/www/html"]
