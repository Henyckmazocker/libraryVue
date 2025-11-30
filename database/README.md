# Base de Datos - Sistema de Sesiones de Lectura

Este directorio contiene respaldos y documentación de la base de datos con el sistema de sesiones de lectura implementado.

## 📁 Estructura

```
database/
├── backups/     # Respaldos automáticos
└── README.md    # Este archivo
```

**Ubicación principal**: `docker/database/init.sql` (todo consolidado)

## 🚀 Aplicar Cambios

### Nueva Instalación

```bash
docker-compose up -d database
```

### Base de Datos Existente

```bash
# Crear respaldo
mysqldump -h localhost -u root -p library_vue > database/backups/backup_$(date +%Y%m%d_%H%M%S).sql

# Ver el init.sql y aplicar cambios necesarios manualmente
```

## 📋 Funcionalidades Implementadas

### Nuevas Tablas

- **`reading_sessions`**: Maneja múltiples lecturas del mismo libro
  - Campos: session_number, started_at, completed_at, status, final_page, session_notes

### Tablas Actualizadas

- **`reading_progress_history`**: Permite progreso negativo
  - Añade: session_id, progress_type, notes
- **`user_books`**: Gestión de sesiones
  - Añade: active_reading_session_id, total_sessions_completed, last_session_completed_at
- **`book_statuses`**: Nuevos estados
  - Añade: 're-reading', 'paused'

### Vistas Creadas

- `user_reading_sessions_view`: Información completa de sesiones
- `user_reading_stats_view`: Estadísticas por usuario
- `recent_reading_activity_view`: Actividad reciente
- `user_books_with_statuses_view`: Libros con estados
- `monthly_reading_stats_view`: Estadísticas mensuales



### Triggers

- Mantenimiento automático de consistencia
- Limpieza automática de sesiones

## ✅ Beneficios

- **Progreso negativo**: Permite retroceder páginas sin errores
- **Relecturas**: Sistema completo para múltiples lecturas
- **Estados inteligentes**: Transiciones automáticas (reading → re-reading → read)
- **Trazabilidad**: Historial completo con tipos de progreso
- **Performance**: Vistas e índices optimizados
- **Estadísticas**: Análisis detallado de patrones de lectura

---

**Versión**: 2.0 - Sistema completo de sesiones de lectura  
**Fecha**: 26 septiembre 2025