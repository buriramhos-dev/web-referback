FROM php:8.2-cli-alpine

# Install extensions
RUN apk add --no-cache mysql-dev \
    && docker-php-ext-install pdo_mysql \
    && apk del mysql-dev

# Custom php settings (optional)
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini

# App folder
WORKDIR /var/www/html
COPY . .

# Expose default port (not required but OK)
EXPOSE 8080

# Correct CMD with Railway's $PORT
CMD sh -c "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"
