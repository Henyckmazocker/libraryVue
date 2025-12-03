# 🔐 Configuración de Variables de Entorno

## ⚠️ IMPORTANTE: Seguridad

Este proyecto ahora usa variables de entorno para evitar subir credenciales a Git.

## 📁 Archivos de Entorno

### Para Desarrollo:
```bash
# 1. Copia el archivo de ejemplo
cp .env.docker.example .env

# 2. Edita .env con tus credenciales reales
nano .env

# 3. Ejecuta docker-compose normalmente
docker-compose up -d
```

### Para Producción:
```bash
# 1. Copia el archivo de ejemplo
cp .env.docker.example .env.prod

# 2. Edita .env.prod con credenciales de producción
nano .env.prod

# 3. Ejecuta docker-compose especificando el archivo .env
docker-compose --env-file .env.prod -f docker-compose.prod.yml up -d
```

## 🔑 Variables Requeridas

### `.env` (Desarrollo):
```env
GOOGLE_CLIENT_ID=tu_google_client_id
MYSQL_ROOT_PASSWORD=root_password
MYSQL_PASSWORD=library_pass
DB_PASSWORD=library_pass
```

### `.env.prod` (Producción):
```env
GOOGLE_CLIENT_ID=tu_google_client_id_produccion
MYSQL_ROOT_PASSWORD=password_seguro_root
MYSQL_PASSWORD=password_seguro_mysql
DB_PASSWORD=password_seguro_mysql
```

## ⚡ Migración desde Credenciales Hardcoded

Si vienes de una versión anterior con credenciales en los archivos:

1. ✅ Ya se crearon archivos `.env` y `.env.prod` con las credenciales actuales
2. ⚠️ **DEBES cambiar las credenciales de producción** antes de hacer deploy
3. ✅ Los archivos `.env*` ya están en `.gitignore`

## 🚨 Rotar Credenciales Expuestas

Si tus credenciales estuvieron en Git público:

### Google OAuth:
1. Ve a [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Elimina el Client Secret actual
3. Genera uno nuevo
4. Actualiza `GOOGLE_CLIENT_ID` en `.env` y `.env.prod`

### JWT Secret:
```bash
# Generar nuevo JWT secret
openssl rand -base64 32

# Actualizar en backend/.env.docker-production
```

### MySQL:
- Cambia `MYSQL_ROOT_PASSWORD` y `MYSQL_PASSWORD` en `.env.prod`
- Actualiza también en `backend/.env.docker-production`

## 📝 Notas

- ❌ **NUNCA** commits archivos `.env` a Git
- ✅ Solo sube `.env.docker.example` como plantilla
- ✅ Comparte credenciales de forma segura (1Password, Bitwarden, etc.)
- ✅ Usa contraseñas diferentes para dev y prod
