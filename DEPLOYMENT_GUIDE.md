# 🚀 Guía de Despliegue en Producción - LibraryVue

## 📋 Prerequisitos

- Servidor con Docker y Docker Compose instalados
- Dominio configurado en Cloudflare
- Credenciales de Google OAuth para producción
- Acceso SSH al servidor

---

## 🔧 Configuración Inicial

### 1. Cloudflare Setup

1. **DNS Configuration**:
   - Añade un registro A apuntando a la IP de tu servidor
   - Ejemplo: `library.tudominio.com` → `IP_DEL_SERVIDOR`
   - Activa el proxy de Cloudflare (nube naranja) ✅

2. **SSL/TLS Settings**:
   - Ve a SSL/TLS → Overview
   - Selecciona **"Full"** o **"Full (strict)"**
   - Cloudflare manejará el certificado SSL automáticamente

3. **Security Settings** (recomendado):
   - Habilita "Always Use HTTPS"
   - Habilita "Auto Minify" (JS, CSS, HTML)
   - Considera habilitar "Brotli" compression

### 2. Google OAuth Configuration

1. Ve a [Google Cloud Console](https://console.cloud.google.com)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la API de Google+ (si no está habilitada)
4. Ve a "Credenciales" → "Crear credenciales" → "ID de cliente de OAuth 2.0"
5. Configura:
   - **Orígenes autorizados**: `https://library.tudominio.com`
   - **URIs de redirección**: `https://library.tudominio.com/auth/google/callback`
6. Guarda el **Client ID** y **Client Secret**

---

## 📦 Deployment Steps

### 3. En tu Servidor

```bash
# 1. Clonar el repositorio
git clone https://github.com/Henyckmazocker/libraryVue.git
cd libraryVue

# 2. Verificar que los archivos de producción existen
ls -la docker-compose.prod.yml
ls -la backend/.env.docker-production
ls -la docker/database/init.prod.sql
```

### 4. Editar Archivos de Configuración

Reemplaza los placeholders en estos archivos:

#### **docker-compose.prod.yml**
```bash
nano docker-compose.prod.yml
```

Buscar y reemplazar:
- `YOUR_SUBDOMAIN.YOUR_DOMAIN.com` → `library.tudominio.com`
- `YOUR_PRODUCTION_GOOGLE_CLIENT_ID` → Tu Google Client ID
- `CHANGE_THIS_SECURE_PASSWORD` → Contraseña segura para MySQL
- `CHANGE_THIS_ROOT_PASSWORD` → Contraseña root de MySQL

#### **backend/.env.docker-production**
```bash
nano backend/.env.docker-production
```

Buscar y reemplazar:
- `YOUR_SUBDOMAIN.YOUR_DOMAIN.com` → `library.tudominio.com`
- `YOUR_PRODUCTION_GOOGLE_CLIENT_ID` → Tu Google Client ID
- `YOUR_PRODUCTION_GOOGLE_CLIENT_SECRET` → Tu Google Client Secret
- `CHANGE_THIS_SECURE_PASSWORD` → La misma contraseña de MySQL
- `CHANGE_THIS_TO_VERY_SECURE_RANDOM_STRING` → String aleatorio para JWT

**Generar JWT Secret:**
```bash
openssl rand -base64 32
```

#### **docker/nginx/nginx.prod.conf**
```bash
nano docker/nginx/nginx.prod.conf
```

Buscar y reemplazar:
- `YOUR_SUBDOMAIN.YOUR_DOMAIN.com` → `library.tudominio.com` (aparece 2 veces)

### 5. Build y Deploy

```bash
# Construir las imágenes
docker-compose -f docker-compose.prod.yml build

# Iniciar los servicios
docker-compose -f docker-compose.prod.yml up -d

# Ver logs
docker-compose -f docker-compose.prod.yml logs -f
```

### 6. Verificar el Despliegue

```bash
# Verificar que los contenedores estén corriendo
docker-compose -f docker-compose.prod.yml ps

# Deberías ver:
# - frontend (running)
# - backend (running)
# - mysql (running)
```

### 7. Acceso a la Aplicación

- Visita: `https://library.tudominio.com`
- El SSL será manejado automáticamente por Cloudflare

---

## 🔄 Actualizaciones

Para actualizar la aplicación en producción:

```bash
# 1. Hacer pull de los cambios
git pull origin master

# 2. Reconstruir y reiniciar
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d
```

---

## 🐛 Troubleshooting

### Logs

```bash
# Todos los servicios
docker-compose -f docker-compose.prod.yml logs -f

# Solo backend
docker-compose -f docker-compose.prod.yml logs -f backend

# Solo frontend
docker-compose -f docker-compose.prod.yml logs -f frontend

# Solo MySQL
docker-compose -f docker-compose.prod.yml logs -f mysql
```

### Acceso al contenedor

```bash
# Backend (PHP)
docker-compose -f docker-compose.prod.yml exec backend bash

# Verificar logs de PHP
docker-compose -f docker-compose.prod.yml exec backend cat storage/logs/app.log
```

### Problemas comunes

**1. Error 502 Bad Gateway**
- Verifica que el backend esté corriendo: `docker-compose -f docker-compose.prod.yml ps`
- Revisa logs del backend: `docker-compose -f docker-compose.prod.yml logs backend`

**2. CORS Errors**
- Verifica que `CORS_ALLOWED_ORIGINS` en `.env.docker-production` coincida con tu dominio
- Asegúrate de incluir `https://`

**3. Google OAuth no funciona**
- Verifica que los orígenes autorizados en Google Console incluyan tu dominio
- Comprueba que el Client ID sea el correcto en ambos archivos de configuración

**4. Base de datos no conecta**
- Espera 30 segundos después del primer inicio para que MySQL inicialice
- Verifica logs de MySQL: `docker-compose -f docker-compose.prod.yml logs mysql`

---

## 🔒 Security Checklist

- ✅ Cambiar todas las contraseñas por defecto
- ✅ Usar contraseñas fuertes y únicas
- ✅ No commitear archivos `.env` con credenciales reales
- ✅ SSL/TLS habilitado vía Cloudflare
- ✅ Debug mode desactivado en producción
- ✅ Logs en formato JSON para mejor parsing
- ✅ Firewall configurado (solo puertos 80, 443, 22)

---

## 📊 Monitoreo

### Espacio en disco

```bash
# Ver uso de volúmenes Docker
docker system df -v

# Limpiar recursos no utilizados
docker system prune -a
```

### Performance

- Cloudflare Analytics para tráfico web
- Docker stats para uso de recursos:
  ```bash
  docker stats
  ```

---

## 🔄 Backup

### Base de datos

**IMPORTANTE**: Producción usa `library_db_prod`, desarrollo usa `library_db`

```bash
# Crear backup de PRODUCCIÓN
docker-compose -f docker-compose.prod.yml exec mysql \
  mysqldump -u library_user -p library_db_prod > backup_$(date +%Y%m%d).sql

# Restaurar backup en PRODUCCIÓN
docker-compose -f docker-compose.prod.yml exec -T mysql \
  mysql -u library_user -p library_db_prod < backup_20250101.sql
```

### Backup manual de storage
```bash
# Listar volúmenes
docker volume ls | grep libraryVue

# Backup de base de datos de producción
docker-compose -f docker-compose.prod.yml exec mysql \
  mysqldump -u library_user -p library_db_prod > backup_prod_$(date +%Y%m%d).sql

# Restaurar backup en producción
docker-compose -f docker-compose.prod.yml exec -T mysql \
  mysql -u library_user -p library_db_prod < backup_prod_20250101.sql

# Backup manual de storage
docker-compose -f docker-compose.prod.yml exec backend \
  tar czf /tmp/storage_backup.tar.gz storage/
```

---

## 🛠️ Maintenance Mode

Para poner la app en mantenimiento temporalmente:

```bash
# Detener solo el frontend
docker-compose -f docker-compose.prod.yml stop frontend

# Reiniciar
docker-compose -f docker-compose.prod.yml start frontend
```

---

## 📝 Variables Críticas a Reemplazar

Antes del primer deploy, asegúrate de cambiar:

1. **docker-compose.prod.yml**:
   - `YOUR_SUBDOMAIN.YOUR_DOMAIN.com`
   - `YOUR_PRODUCTION_GOOGLE_CLIENT_ID`
   - `CHANGE_THIS_SECURE_PASSWORD`
   - `CHANGE_THIS_ROOT_PASSWORD`

2. **backend/.env.docker-production**:
   - `YOUR_SUBDOMAIN.YOUR_DOMAIN.com`
   - `YOUR_PRODUCTION_GOOGLE_CLIENT_ID`
   - `YOUR_PRODUCTION_GOOGLE_CLIENT_SECRET`
   - `CHANGE_THIS_SECURE_PASSWORD`
   - `CHANGE_THIS_TO_VERY_SECURE_RANDOM_STRING`

3. **docker/nginx/nginx.prod.conf**:
   - `YOUR_SUBDOMAIN.YOUR_DOMAIN.com` (2 ocurrencias)

---

## ✅ Quick Start Checklist

- [ ] Dominio configurado en Cloudflare
- [ ] SSL/TLS en modo "Full"
- [ ] Google OAuth credentials creadas
- [ ] Servidor con Docker instalado
- [ ] Git clone del repositorio
- [ ] Variables de entorno configuradas
- [ ] `docker-compose -f docker-compose.prod.yml build`
- [ ] `docker-compose -f docker-compose.prod.yml up -d`
- [ ] Verificar que la app carga en el navegador
- [ ] Probar login con Google

---

## 🆘 Soporte

Si encuentras problemas:
1. Revisa los logs: `docker-compose -f docker-compose.prod.yml logs -f`
2. Verifica que todos los placeholders fueron reemplazados
3. Comprueba la configuración de Cloudflare
4. Asegúrate de que el puerto 80 esté abierto en el firewall
