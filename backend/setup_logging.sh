#!/bin/bash

# Script de configuración rápida para el sistema de logging
# Usage: ./setup_logging.sh [environment]

ENVIRONMENT=${1:-development}

echo "=== Configurando Sistema de Logging para $ENVIRONMENT ==="

# Crear directorios necesarios si no existen
echo "Creando directorios necesarios..."
mkdir -p storage/logs
mkdir -p storage/cache
mkdir -p storage/sessions
mkdir -p storage/uploads

# Crear archivos .gitkeep si no existen
touch storage/logs/.gitkeep
touch storage/cache/.gitkeep
touch storage/sessions/.gitkeep
touch storage/uploads/.gitkeep

# Copiar archivo de configuración de entorno apropiado
echo "Configurando variables de entorno para $ENVIRONMENT..."
if [ -f ".env.$ENVIRONMENT" ]; then
    cp ".env.$ENVIRONMENT" .env
    echo "Archivo .env configurado desde .env.$ENVIRONMENT"
else
    echo "Archivo .env.$ENVIRONMENT no encontrado, usando .env.example"
    cp .env.example .env
fi

# Configurar permisos para directorios de storage
echo "Configurando permisos..."
chmod -R 755 storage/
chmod -R 775 storage/logs/
chmod -R 775 storage/cache/
chmod -R 775 storage/sessions/
chmod -R 775 storage/uploads/

# Verificar que Composer esté instalado
if ! command -v composer &> /dev/null; then
    echo "Composer no está instalado. Por favor instala Composer primero."
    exit 1
fi

# Instalar dependencias si vendor no existe
if [ ! -d "vendor" ]; then
    echo "Instalando dependencias de Composer..."
    composer install
fi

# Verificar que Monolog esté instalado
if ! composer show monolog/monolog &> /dev/null; then
    echo "Instalando Monolog..."
    composer require monolog/monolog
fi

echo ""
echo "=== Configuración Completada ==="
echo "Sistema de logging configurado para entorno: $ENVIRONMENT"
echo ""
echo "Archivos creados/actualizados:"
echo "- .env (configuración de entorno)"
echo "- storage/logs/ (directorio de logs)"
echo "- storage/cache/ (directorio de cache)"
echo "- storage/sessions/ (directorio de sesiones)"
echo "- storage/uploads/ (directorio de uploads)"
echo ""
echo "Para probar el sistema, ejecuta:"
echo "php logging_examples.php"
echo ""
echo "Los logs se almacenarán en:"
echo "- storage/logs/api.log (logs de API)"
echo "- storage/logs/database.log (logs de base de datos)"
echo "- storage/logs/auth.log (logs de autenticación)"
echo "- storage/logs/security.log (logs de seguridad)"
echo "- storage/logs/application.log (logs de aplicación)"
echo "- storage/logs/errors.log (todos los errores)"
echo ""
echo "Documentación disponible en:"
echo "- ../docs/LOGGING_SYSTEM.md"
