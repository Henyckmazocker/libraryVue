import { useAuthStore } from '@/store/auth';
import FileProcessorService from './FileProcessorService';
import Logger from '@/utils/logger';

/**
 * Servicio para manejar la importación de datos al backend
 */
export class ImportService {
  constructor() {
    this.authStore = useAuthStore();
  }

  /**
   * Importa un archivo procesado al backend
   */
  async importFile(file, service, onProgress = null) {
    try {
      // Notificar inicio del procesamiento
      if (onProgress) {
        onProgress({
          message: service === 'palomitacas' 
            ? 'Procesando archivo y obteniendo datos de OMDb...' 
            : 'Procesando archivo...',
          type: 'info',
          loading: true
        }, 10);
      }

      // Procesar el archivo según el servicio
      const processedData = await FileProcessorService.processFile(file, service);

      // Notificar progreso del procesamiento
      if (onProgress) {
        const elementType = this.getElementType(service);
        onProgress({
          message: `Enviando ${processedData.length} ${elementType} al servidor...`,
          type: 'info',
          loading: true
        }, 60);
      }

      // Enviar datos al backend
      const requestData = {
        service: service,
        processedData: processedData
      };

      const response = await this.authStore.apiCall('import_data', requestData);

      // Procesar respuesta
      if (response.data && response.data.status === 'success') {
        const importData = response.data.data;
        const elementType = this.getElementType(service);
        
        const successMessage = this.buildSuccessMessage(importData, elementType, service);
        
        // Notificar éxito
        if (onProgress) {
          onProgress({
            message: successMessage,
            type: 'success',
            loading: false
          }, 100);
        }

        // Log de errores si los hay
        if (importData.errors && importData.errors.length > 0) {
          Logger.warn('Errores durante la importación:', importData.errors);
        }

        return {
          success: true,
          data: {
            service: service,
            fileName: file.name,
            responseData: response.data,
            imported: importData.imported,
            skipped: importData.skipped,
            errors: importData.errors
          }
        };
      } else {
        const errorMessage = response.data?.message || 'Error al importar los datos.';
        
        if (onProgress) {
          onProgress({
            message: errorMessage,
            type: 'error',
            loading: false
          }, 100);
        }

        return {
          success: false,
          error: errorMessage
        };
      }
    } catch (error) {
      Logger.error('Error importing data:', error);
      
      const errorMessage = `Error procesando archivo: ${error.message}`;
      
      if (onProgress) {
        onProgress({
          message: errorMessage,
          type: 'error',
          loading: false
        }, 100);
      }

      return {
        success: false,
        error: errorMessage
      };
    }
  }

  /**
   * Obtiene el tipo de elemento según el servicio
   */
  getElementType(service) {
    const types = {
      'palomitacas': 'películas',
      'letterboxd': 'películas',
      'goodreads': 'libros',
      'serialized': 'elementos'
    };
    
    return types[service] || 'elementos';
  }

  /**
   * Construye el mensaje de éxito
   */
  buildSuccessMessage(importData, elementType, service) {
    const successMsg = `${importData.imported} ${elementType} importados correctamente de ${service}`;
    const detailMsg = importData.skipped > 0 ? ` (${importData.skipped} omitidos por duplicado)` : '';
    
    return successMsg + detailMsg;
  }

  /**
   * Valida que se pueda realizar la importación
   */
  validateImport(service, file) {
    if (!service) {
      return {
        valid: false,
        error: 'Por favor selecciona un servicio.'
      };
    }

    if (!file) {
      return {
        valid: false,
        error: 'Por favor selecciona un archivo.'
      };
    }

    // Validaciones específicas por servicio
    const validations = {
      'palomitacas': {
        extensions: ['.xml'],
        maxSize: 50 * 1024 * 1024 // 50MB
      },
      'letterboxd': {
        extensions: ['.csv'],
        maxSize: 10 * 1024 * 1024 // 10MB
      },
      'goodreads': {
        extensions: ['.csv'],
        maxSize: 10 * 1024 * 1024 // 10MB
      },
      'serialized': {
        extensions: ['.json', '.txt'],
        maxSize: 20 * 1024 * 1024 // 20MB
      }
    };

    const validation = validations[service];
    if (validation) {
      // Validar extensión
      const fileName = file.name.toLowerCase();
      const hasValidExtension = validation.extensions.some(ext => fileName.endsWith(ext));
      
      if (!hasValidExtension) {
        return {
          valid: false,
          error: `Archivo debe tener una de estas extensiones: ${validation.extensions.join(', ')}`
        };
      }

      // Validar tamaño
      if (file.size > validation.maxSize) {
        const maxSizeMB = Math.round(validation.maxSize / (1024 * 1024));
        return {
          valid: false,
          error: `El archivo es demasiado grande. Tamaño máximo: ${maxSizeMB}MB`
        };
      }
    }

    return { valid: true };
  }
}

// Instancia singleton del servicio
let importServiceInstance = null;

export function useImportService() {
  if (!importServiceInstance) {
    importServiceInstance = new ImportService();
  }
  return importServiceInstance;
}

export default ImportService;
