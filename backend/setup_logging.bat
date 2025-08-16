@echo off
REM Script de configuración rápida para el sistema de logging en Windows
REM Usage: setup_logging.bat [environment]

set ENVIRONMENT=%1
if "%ENVIRONMENT%"=="" set ENVIRONMENT=development

echo === Configurando Sistema de Logging para %ENVIRONMENT% ===

REM Crear directorios necesarios si no existen
echo Creando directorios necesarios...
if not exist "storage\logs" mkdir "storage\logs"
if not exist "storage\cache" mkdir "storage\cache"
if not exist "storage\sessions" mkdir "storage\sessions"
if not exist "storage\uploads" mkdir "storage\uploads"

REM Crear archivos .gitkeep si no existen
echo. > "storage\logs\.gitkeep"
echo. > "storage\cache\.gitkeep"
echo. > "storage\sessions\.gitkeep"
echo. > "storage\uploads\.gitkeep"

REM Copiar archivo de configuración de entorno apropiado
echo Configurando variables de entorno para %ENVIRONMENT%...
if exist ".env.%ENVIRONMENT%" (
    copy ".env.%ENVIRONMENT%" ".env"
    echo Archivo .env configurado desde .env.%ENVIRONMENT%
) else (
    echo Archivo .env.%ENVIRONMENT% no encontrado, usando .env.example
    copy ".env.example" ".env"
)

REM Verificar que Composer esté instalado
where composer >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo Composer no está instalado. Por favor instala Composer primero.
    exit /b 1
)

REM Instalar dependencias si vendor no existe
if not exist "vendor" (
    echo Instalando dependencias de Composer...
    composer install
)

REM Verificar que Monolog esté instalado
composer show monolog/monolog >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo Instalando Monolog...
    composer require monolog/monolog
)

echo.
echo === Configuración Completada ===
echo Sistema de logging configurado para entorno: %ENVIRONMENT%
echo.
echo Archivos creados/actualizados:
echo - .env (configuración de entorno)
echo - storage\logs\ (directorio de logs)
echo - storage\cache\ (directorio de cache)
echo - storage\sessions\ (directorio de sesiones)
echo - storage\uploads\ (directorio de uploads)
echo.
echo Para probar el sistema, ejecuta:
echo php logging_examples.php
echo.
echo Los logs se almacenarán en:
echo - storage\logs\api.log (logs de API)
echo - storage\logs\database.log (logs de base de datos)
echo - storage\logs\auth.log (logs de autenticación)
echo - storage\logs\security.log (logs de seguridad)
echo - storage\logs\application.log (logs de aplicación)
echo - storage\logs\errors.log (todos los errores)
echo.
echo Documentación disponible en:
echo - ..\docs\LOGGING_SYSTEM.md

pause
