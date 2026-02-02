FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ca-certificates \
    git \
    curl \
    gnupg \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd opcache zip

# Install Google Chrome for Testing (headless) + ChromeDriver (pareados)
RUN apt-get update && apt-get install -y \
    wget \
    jq \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libgbm1 \
    libpango-1.0-0 \
    libasound2 \
    libxshmfence1 \
    fonts-liberation \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    JSON_URL="https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json"; \
    CHROME_URL=$(wget -qO- "$JSON_URL" | jq -r '.channels.Stable.downloads.chrome[] | select(.platform=="linux64") | .url'); \
    DRIVER_URL=$(wget -qO- "$JSON_URL" | jq -r '.channels.Stable.downloads.chromedriver[] | select(.platform=="linux64") | .url'); \
    wget -q "$CHROME_URL" -O /tmp/chrome.zip; \
    wget -q "$DRIVER_URL" -O /tmp/chromedriver.zip; \
    unzip -q /tmp/chrome.zip -d /opt/chrome/; \
    unzip -q /tmp/chromedriver.zip -d /opt/chrome/; \
    ln -sf /opt/chrome/chrome-linux64/chrome /usr/local/bin/google-chrome; \
    ln -sf /opt/chrome/chromedriver-linux64/chromedriver /usr/local/bin/chromedriver; \
    chmod +x /opt/chrome/chrome-linux64/chrome /opt/chrome/chromedriver-linux64/chromedriver; \
    rm -f /tmp/chrome.zip /tmp/chromedriver.zip

ENV PANTHER_CHROME_BINARY=/opt/chrome/chrome-linux64/chrome
ENV PANTHER_CHROME_DRIVER_BINARY=/opt/chrome/chromedriver-linux64/chromedriver
ENV PANTHER_NO_SANDBOX=1

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Install Node.js (needed to build Vite assets)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Setup Nginx & Supervisor
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application code
COPY . /var/www/html

# Install dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
RUN if [ -f package-lock.json ]; then npm ci; else npm install --no-audit --no-fund; fi; \
    npm run build; \
    rm -rf node_modules

# Create Laravel storage symlink at build time via Artisan (avoids needing `php artisan storage:link` on container start)
RUN rm -rf public/storage \
    && APP_ENV=production APP_DEBUG=false APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan storage:link

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

# Start Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
