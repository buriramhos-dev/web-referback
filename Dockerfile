# 1. ใช้ Base Image
FROM php:8.2-fpm-alpine 

# 2. ติดตั้ง Dependencies และ pdo_mysql (แก้ไขชื่อแพ็กเกจ)
RUN apk add --no-cache mysql-dev \
    && docker-php-ext-install pdo_mysql \
    && apk del mysql-dev

# 3. คัดลอกไฟล์ custom.ini (แก้ไขปัญหา Socket)
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini 

# 4. ตั้งค่า Working Directory และคัดลอกไฟล์โปรเจกต์
WORKDIR /var/www/html
COPY . .

# 5. กำหนดคำสั่งเริ่มต้น
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t /var/www/html"]