import { useAuthStore } from '@/store/auth';
import FileProcessorService from './FileProcessorService';
import Logger from '@/utils/logger';
import { t } from '@/config/i18n';

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
            ? t('importer.processingOmdb')
            : t('importer.processing'),
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
          message: t('importer.sendingCount', { n: processedData.length, tipo: elementType }),
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
        const errorMessage = t('importer.importFailed');
        
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
      
      const errorMessage = t('importer.processFailed', { detalle: error.message });
      
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
      'palomitacas': 'importer.itemsMovies',
      'letterboxd': 'importer.itemsMovies',
      'goodreads': 'importer.itemsBooks',
      'serialized': 'importer.itemsGeneric'
    };

    return t(types[service] || 'importer.itemsGeneric');
  }

  /**
   * Construye el mensaje de éxito
   */
  buildSuccessMessage(importData, elementType, service) {
    const successMsg = t('importer.imported', {
      n: importData.imported, tipo: elementType, servicio: service
    });
    const detailMsg = importData.skipped > 0 ? t('importer.skipped', { n: importData.skipped }) : '';

    return successMsg + detailMsg;
  }

  /**
   * Valida que se pueda realizar la importación
   */
  validateImport(service, file) {
    if (!service) {
      return {
        valid: false,
        error: t('importer.pickService')
      };
    }

    if (!file) {
      return {
        valid: false,
        error: t('importer.pickFile')
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
          error: t('importer.extensions', { lista: validation.extensions.join(', ') })
        };
      }

      // Validar tamaño
      if (file.size > validation.maxSize) {
        const maxSizeMB = Math.round(validation.maxSize / (1024 * 1024));
        return {
          valid: false,
          error: t('importer.tooBig', { n: maxSizeMB })
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
