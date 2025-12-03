# Production Environment - Quick Reference

## 📁 Files Created for Production

### ✅ New Files (won't affect dev environment)
- `docker-compose.prod.yml` - Production Docker configuration
- `backend/.env.docker-production` - Production backend environment
- `docker/nginx/nginx.prod.conf` - Production Nginx config
- `docker/database/init.prod.sql` - Production database initialization (library_db_prod)
- `DEPLOYMENT_GUIDE.md` - Complete deployment instructions

### ✅ Updated Files
- `docker/backend/Dockerfile.backend.prod` - Production backend image
- `docker/frontend/Dockerfile.frontend.prod` - Production frontend image

---

## 🔑 Critical: Replace These Placeholders

Before deploying, search and replace in ALL production files:

1. **YOUR_SUBDOMAIN.YOUR_DOMAIN.com** → your actual domain
2. **YOUR_PRODUCTION_GOOGLE_CLIENT_ID** → from Google Cloud Console
3. **YOUR_PRODUCTION_GOOGLE_CLIENT_SECRET** → from Google Cloud Console
4. **CHANGE_THIS_SECURE_PASSWORD** → strong MySQL password
5. **CHANGE_THIS_ROOT_PASSWORD** → strong MySQL root password
6. **CHANGE_THIS_TO_VERY_SECURE_RANDOM_STRING** → JWT secret (use: `openssl rand -base64 32`)

---

## 🚀 Quick Deploy Commands

```bash
# On your server:
git clone https://github.com/Henyckmazocker/libraryVue.git
cd libraryVue

# Edit configuration files (replace placeholders)
nano docker-compose.prod.yml
nano backend/.env.docker-production
nano docker/nginx/nginx.prod.conf

# Build and run
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d

# Check logs
docker-compose -f docker-compose.prod.yml logs -f
```

---
## 🔄 Development vs Production

### Development (LOCAL - unchanged):
```bash
docker-compose up -d
```
- Uses: `docker-compose.yml`
- Database: `library_db`
- URL: `http://localhost:8080`
- Hot reload enabled
- Debug mode ON

### Production (SERVER - new):
```bash
docker-compose -f docker-compose.prod.yml up -d
```
- Uses: `docker-compose.prod.yml`
- Database: `library_db_prod` (separate from dev)
- URL: `https://your-domain.com`
- Optimized builds
- Debug mode OFF
- SSL via Cloudflare

**They are completely independent!** ✅
**They are completely independent!** ✅

---

## 📚 Full Documentation

See `DEPLOYMENT_GUIDE.md` for complete step-by-step instructions.
