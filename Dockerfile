FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY src ./src
RUN npm run build

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_mysql curl mbstring \
    && pecl install redis-6.1.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY docker/apache.conf /etc/apache2/conf-available/portal.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/production-portal.ini
RUN a2enconf portal
WORKDIR /var/www/html
COPY *.php ./
COPY admin ./admin
COPY assets ./assets
COPY functions ./functions
COPY includes ./includes
COPY login ./login
COPY teacher ./teacher
COPY --from=frontend /build/assets/dist ./assets/dist
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD curl -fsS http://127.0.0.1/health.php || exit 1
