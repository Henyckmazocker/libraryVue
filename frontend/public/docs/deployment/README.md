# Deployment Documentation - Library Vue

## Overview

Esta documentación describe los procesos de deployment para el proyecto Library Vue, incluyendo configuraciones para desarrollo, staging y producción.

## Deployment Environments

### Development Environment

#### Requirements
- Docker & Docker Compose
- Node.js 18+ (for local frontend development)
- PHP 8.1+ (for local backend development)
- MySQL 8.0+

#### Setup with Docker Compose

```yaml
# docker-compose.yml (Development)
version: '3.8'

services:
  frontend:
    build:
      context: ./frontend
      dockerfile: ../docker/frontend/Dockerfile.frontend.dev
    volumes:
      - ./frontend:/app
      - /app/node_modules
    ports:
      - "3000:3000"
    environment:
      - NODE_ENV=development
      - VITE_API_BASE_URL=http://localhost:8080/api
    depends_on:
      - backend

  backend:
    build:
      context: ./backend
      dockerfile: ../docker/backend/Dockerfile.backend.dev
    volumes:
      - ./backend:/var/www/html
    ports:
      - "8080:80"
    environment:
      - PHP_ENV=development
      - DB_HOST=database
      - DB_DATABASE=library_db
      - DB_USERNAME=library_user
      - DB_PASSWORD=library_pass
      - LOG_LEVEL=debug
    depends_on:
      - database

  database:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      - MYSQL_ROOT_PASSWORD=root_password
      - MYSQL_DATABASE=library_db
      - MYSQL_USER=library_user
      - MYSQL_PASSWORD=library_pass
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/database/init.sql:/docker-entrypoint-initdb.d/init.sql

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - frontend
      - backend

volumes:
  mysql_data:
```

#### Development Commands

```bash
# Start development environment
docker-compose up -d

# View logs
docker-compose logs -f

# Stop environment
docker-compose down

# Rebuild services
docker-compose up --build

# Access backend container
docker-compose exec backend bash

# Access database
docker-compose exec database mysql -u library_user -p library_db
```

### Staging Environment

#### Docker Configuration

```yaml
# docker-compose.staging.yml
version: '3.8'

services:
  frontend:
    build:
      context: ./frontend
      dockerfile: ../docker/frontend/Dockerfile.frontend.prod
    environment:
      - NODE_ENV=staging
      - VITE_API_BASE_URL=https://api-staging.library.example.com

  backend:
    build:
      context: ./backend
      dockerfile: ../docker/backend/Dockerfile.backend.prod
    environment:
      - PHP_ENV=staging
      - DB_HOST=database
      - DB_DATABASE=library_staging
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - LOG_LEVEL=info
    volumes:
      - backend_logs:/var/www/html/storage/logs

  database:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=library_staging
      - MYSQL_USER=${DB_USERNAME}
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - mysql_staging_data:/var/lib/mysql

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/staging.conf:/etc/nginx/conf.d/default.conf
      - /etc/letsencrypt:/etc/letsencrypt:ro
    depends_on:
      - frontend
      - backend

volumes:
  mysql_staging_data:
  backend_logs:
```

### Production Environment

#### Docker Configuration

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  frontend:
    build:
      context: ./frontend
      dockerfile: ../docker/frontend/Dockerfile.frontend.prod
    environment:
      - NODE_ENV=production
      - VITE_API_BASE_URL=https://api.library.example.com
    restart: unless-stopped

  backend:
    build:
      context: ./backend
      dockerfile: ../docker/backend/Dockerfile.backend.prod
    environment:
      - PHP_ENV=production
      - DB_HOST=database
      - DB_DATABASE=library_production
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - LOG_LEVEL=warning
    volumes:
      - backend_logs:/var/www/html/storage/logs
      - backend_uploads:/var/www/html/storage/uploads
    restart: unless-stopped

  database:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=library_production
      - MYSQL_USER=${DB_USERNAME}
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - mysql_prod_data:/var/lib/mysql
    restart: unless-stopped
    command: --innodb-buffer-pool-size=256M --max-connections=200

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/production.conf:/etc/nginx/conf.d/default.conf
      - /etc/letsencrypt:/etc/letsencrypt:ro
    restart: unless-stopped
    depends_on:
      - frontend
      - backend

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - redis_data:/data

volumes:
  mysql_prod_data:
  backend_logs:
  backend_uploads:
  redis_data:
```

## Docker Images

### Frontend Dockerfile

#### Development

```dockerfile
# docker/frontend/Dockerfile.frontend.dev
FROM node:18-alpine

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm ci

# Copy source code
COPY . .

EXPOSE 3000

# Start development server with hot reload
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
```

#### Production

```dockerfile
# docker/frontend/Dockerfile.frontend.prod
FROM node:18-alpine AS builder

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm ci

# Copy source and build
COPY . .
RUN npm run build

# Production stage
FROM nginx:alpine

# Copy built assets
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY docker/nginx/frontend.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

### Backend Dockerfile

#### Development

```dockerfile
# docker/backend/Dockerfile.backend.dev
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer*.json ./

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader

# Copy source code
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage
RUN chmod -R 775 /var/www/html/storage

# Copy Apache configuration
COPY docker/backend/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
```

#### Production

```dockerfile
# docker/backend/Dockerfile.backend.prod
FROM php:8.1-apache AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer*.json ./

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy source code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage
RUN chmod -R 775 /var/www/html/storage

# Copy Apache configuration
COPY docker/backend/apache-prod.conf /etc/apache2/sites-available/000-default.conf

# Create non-root user
RUN groupadd -r appuser && useradd -r -g appuser appuser
USER appuser

EXPOSE 80

CMD ["apache2-foreground"]
```

## Nginx Configuration

### Development

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    server_name localhost;

    # Frontend
    location / {
        proxy_pass http://frontend:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket support for HMR
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    # API
    location /api {
        proxy_pass http://backend:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Production

```nginx
# docker/nginx/production.conf
# Rate limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=login:10m rate=1r/s;

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name library.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name library.example.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/library.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/library.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Frontend
    location / {
        proxy_pass http://frontend:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Cache static assets
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }

    # API with rate limiting
    location /api {
        limit_req zone=api burst=20 nodelay;
        
        proxy_pass http://backend:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeout settings
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }

    # Stricter rate limiting for auth endpoints
    location /api/auth {
        limit_req zone=login burst=5 nodelay;
        
        proxy_pass http://backend:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Environment Variables

### Development (.env.development)

```bash
# Database
DB_HOST=database
DB_DATABASE=library_db
DB_USERNAME=library_user
DB_PASSWORD=library_pass

# Application
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=debug
LOG_CHANNEL=app

# Frontend
VITE_API_BASE_URL=http://localhost:8080/api
VITE_APP_NAME=Library Vue
VITE_APP_VERSION=2.0.0

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
```

### Production (.env.production)

```bash
# Database
DB_HOST=database
DB_DATABASE=library_production
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

# Application
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
LOG_CHANNEL=app

# Security
SESSION_SECURE=true
SESSION_SAMESITE=strict

# Frontend
VITE_API_BASE_URL=https://api.library.example.com
VITE_APP_NAME=Library Vue
VITE_APP_VERSION=2.0.0

# Google OAuth
GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}

# SSL
SSL_CERT_PATH=/etc/letsencrypt/live/library.example.com/fullchain.pem
SSL_KEY_PATH=/etc/letsencrypt/live/library.example.com/privkey.pem
```

## CI/CD Pipeline

### GitHub Actions Workflow

```yaml
# .github/workflows/ci-cd.yml
name: CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  test-backend:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: library_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql, zip
        
    - name: Install dependencies
      run: |
        cd backend
        composer install --prefer-dist --no-progress
        
    - name: Run tests
      run: |
        cd backend
        php vendor/bin/phpunit

  test-frontend:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: frontend/package-lock.json
        
    - name: Install dependencies
      run: |
        cd frontend
        npm ci
        
    - name: Run linter
      run: |
        cd frontend
        npm run lint
        
    - name: Run tests
      run: |
        cd frontend
        npm run test:unit

  build-and-push:
    needs: [test-backend, test-frontend]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Log in to Container Registry
      uses: docker/login-action@v2
      with:
        registry: ${{ env.REGISTRY }}
        username: ${{ github.actor }}
        password: ${{ secrets.GITHUB_TOKEN }}
        
    - name: Extract metadata
      id: meta
      uses: docker/metadata-action@v4
      with:
        images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
        tags: |
          type=ref,event=branch
          type=sha
          
    - name: Build and push Docker images
      uses: docker/build-push-action@v4
      with:
        context: .
        file: docker/backend/Dockerfile.backend.prod
        push: true
        tags: ${{ steps.meta.outputs.tags }}
        labels: ${{ steps.meta.outputs.labels }}

  deploy-staging:
    needs: build-and-push
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/develop'
    
    steps:
    - name: Deploy to staging
      run: |
        echo "Deploying to staging environment..."
        # Add staging deployment commands here

  deploy-production:
    needs: build-and-push
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    environment: production
    
    steps:
    - name: Deploy to production
      run: |
        echo "Deploying to production environment..."
        # Add production deployment commands here
```

## Deployment Commands

### Quick Start

```bash
# Clone repository
git clone https://github.com/your-org/library-vue.git
cd library-vue

# Copy environment file
cp .env.example .env.development

# Start development environment
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f backend
```

### Production Deployment

```bash
# Pull latest changes
git pull origin main

# Copy production environment
cp .env.example .env.production

# Build and start production containers
docker-compose -f docker-compose.prod.yml up -d --build

# Run database migrations
docker-compose -f docker-compose.prod.yml exec backend php migrations/run.php

# Check application health
curl -f http://localhost/api/health || exit 1
```

### SSL Certificate Setup

```bash
# Install certbot
sudo apt-get install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d library.example.com

# Setup auto-renewal
echo "0 12 * * * /usr/bin/certbot renew --quiet" | sudo crontab -
```

## Monitoring & Logging

### Health Checks

```bash
# Application health check
curl -f http://localhost/api/health

# Database health check
docker-compose exec database mysqladmin ping -h localhost

# View application logs
docker-compose logs backend | tail -f

# View error logs
docker-compose exec backend tail -f storage/logs/errors-$(date +%Y-%m-%d).log
```

### Performance Monitoring

```bash
# Monitor container resources
docker stats

# Monitor disk usage
df -h

# Monitor application performance
curl -s http://localhost/api/health | jq '.response_time'
```

## Backup & Recovery

### Database Backup

```bash
# Create backup
docker-compose exec database mysqldump -u library_user -p library_production > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore backup
docker-compose exec -T database mysql -u library_user -p library_production < backup_file.sql
```

### Application Backup

```bash
# Backup uploads and logs
tar -czf app_backup_$(date +%Y%m%d).tar.gz backend/storage/uploads backend/storage/logs

# Backup configuration
tar -czf config_backup_$(date +%Y%m%d).tar.gz .env* docker/nginx/
```

---

*Documentación actualizada: 18 de Agosto de 2025*
