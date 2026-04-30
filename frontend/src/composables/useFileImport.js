import { ref, computed } from 'vue';
import { useAuth } from './useAuth';
import { FileProcessorService } from '@/services/FileProcessorService';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de importación de archivos
 * Proporciona funcionalidades para importar datos desde diferentes servicios
 */
export function useFileImport() {
  const { authenticatedApiCall } = useAuth();

  // Estados reactivos
  const selectedService = ref('');
  const selectedFile = ref(null);
  const importStatus = ref('idle'); // idle, processing, success, error
  const importProgress = ref(0);
  const importResults = ref({});
  const error = ref(null);
  const isImporting = ref(false);

  // Servicios disponibles
  const availableServices = ref([
    {
      id: 'palomitacas',
      name: 'Palomitacas',
      description: 'Importar desde archivo XML de Palomitacas',
      acceptedTypes: '.xml',
      icon: 'fas fa-file-code',
      type: 'movies'
    },
    {
      id: 'letterboxd',
      name: 'Letterboxd',
      description: 'Importar desde archivo CSV de Letterboxd',
      acceptedTypes: '.csv',
      icon: 'fas fa-file-csv',
      type: 'movies'
    },
    {
      id: 'goodreads',
      name: 'Goodreads',
      description: 'Importar desde archivo CSV de Goodreads',
      acceptedTypes: '.csv',
      icon: 'fas fa-file-csv',
      type: 'books'
    },
    {
      id: 'serialized',
      name: 'Datos Serializados',
      description: 'Importar desde archivo JSON serializado',
      acceptedTypes: '.json',
      icon: 'fas fa-file-code',
      type: 'mixed'
    }
  ]);

  // Estados computados
  const canImport = computed(() => {
    return selectedService.value && 
           selectedFile.value && 
           !isImporting.value &&
           importStatus.value !== 'processing';
  });

  const currentService = computed(() => {
    return availableServices.value.find(service => service.id === selectedService.value);
  });

  const acceptedFileTypes = computed(() => {
    return currentService.value?.acceptedTypes || '';
  });

  const isSuccess = computed(() => importStatus.value === 'success');
  const isError = computed(() => importStatus.value === 'error');
  const isProcessing = computed(() => importStatus.value === 'processing');

  /**
   * Establece el servicio seleccionado
   * @param {string} serviceId - ID del servicio
   */
  const setService = (serviceId) => {
    if (isImporting.value) return;
    
    selectedService.value = serviceId;
    // Limpiar archivo si no es compatible con el nuevo servicio
    if (selectedFile.value && currentService.value) {
      const fileName = selectedFile.value.name.toLowerCase();
      const acceptedExtensions = currentService.value.acceptedTypes.split(',');
      const isCompatible = acceptedExtensions.some(ext => 
        fileName.endsWith(ext.trim().replace('.', ''))
      );
      
      if (!isCompatible) {
        selectedFile.value = null;
      }
    }
    
    clearError();
    Logger.debug(`[useFileImport] Service selected: ${serviceId}`);
  };

  /**
   * Establece el archivo seleccionado
   * @param {File} file - Archivo seleccionado
   */
  const setFile = (file) => {
    if (isImporting.value) return;
    
    selectedFile.value = file;
    clearError();
    
    if (file) {
      Logger.debug(`[useFileImport] File selected: ${file.name} (${file.size} bytes)`);
      
      // Validar el tipo de archivo si hay un servicio seleccionado
      if (currentService.value) {
        const fileName = file.name.toLowerCase();
        const acceptedExtensions = currentService.value.acceptedTypes.split(',');
        const isCompatible = acceptedExtensions.some(ext => 
          fileName.endsWith(ext.trim().replace('.', ''))
        );
        
        if (!isCompatible) {
          error.value = `Tipo de archivo no compatible. Se esperaba: ${currentService.value.acceptedTypes}`;
          selectedFile.value = null;
        }
      }
    }
  };

  /**
   * Inicia el proceso de importación
   */
  const startImport = async () => {
    if (!canImport.value) {
      const errorMsg = 'Cannot start import: missing service or file';
      error.value = errorMsg;
      Logger.error('[useFileImport]', errorMsg);
      return { success: false, message: errorMsg };
    }

    isImporting.value = true;
    importStatus.value = 'processing';
    importProgress.value = 0;
    error.value = null;
    importResults.value = {};

    try {
      Logger.debug(`[useFileImport] Starting import with service: ${selectedService.value}`);

      // Fase 1: Procesar el archivo localmente
      updateProgress(10, 'Procesando archivo...');
      const processedData = await FileProcessorService.processFile(
        selectedFile.value,
        selectedService.value
      );

      if (!processedData || !Array.isArray(processedData) || processedData.length === 0) {
        throw new Error('No se pudieron extraer datos válidos del archivo');
      }

      Logger.debug(`[useFileImport] File processed successfully. Found ${processedData.length} items`);

      // Fase 2: Enviar datos al backend
      updateProgress(30, 'Enviando datos al servidor...');
      const response = await authenticatedApiCall('import_data', {
        processedData: processedData
      });

      if (response.data.status === 'success') {
        updateProgress(100, 'Importación completada exitosamente');
        importStatus.value = 'success';
        importResults.value = response.data.data || {};
        
        Logger.debug('[useFileImport] Import completed successfully:', importResults.value);
        
        return {
          success: true,
          data: importResults.value,
          message: 'Importación completada exitosamente'
        };
      } else {
        throw new Error(response.data.message || 'Error en el servidor durante la importación');
      }

    } catch (err) {
      const errorMessage = err.message || 'Error desconocido durante la importación';
      error.value = errorMessage;
      importStatus.value = 'error';
      importProgress.value = 0;
      
      Logger.error('[useFileImport] Import failed:', err);
      
      return {
        success: false,
        message: errorMessage
      };
    } finally {
      isImporting.value = false;
    }
  };

  /**
   * Actualiza el progreso de la importación
   * @param {number} progress - Porcentaje de progreso (0-100)
   * @param {string} message - Mensaje descriptivo
   */
  const updateProgress = (progress, message) => {
    importProgress.value = Math.min(progress, 100);
    Logger.debug(`[useFileImport] Progress: ${progress}% - ${message}`);
  };

  /**
   * Valida si un archivo es compatible con el servicio seleccionado
   * @param {File} file - Archivo a validar
   * @returns {boolean} - True si es compatible
   */
  const validateFile = (file) => {
    if (!file || !currentService.value) return false;
    
    const fileName = file.name.toLowerCase();
    const acceptedExtensions = currentService.value.acceptedTypes.split(',');
    
    return acceptedExtensions.some(ext => 
      fileName.endsWith(ext.trim().replace('.', ''))
    );
  };

  /**
   * Obtiene información del servicio por ID
   * @param {string} serviceId - ID del servicio
   * @returns {Object|null} - Información del servicio
   */
  const getServiceInfo = (serviceId) => {
    return availableServices.value.find(service => service.id === serviceId) || null;
  };

  /**
   * Limpia los errores
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * Reinicia todos los estados de importación
   */
  const resetImport = () => {
    selectedService.value = '';
    selectedFile.value = null;
    importStatus.value = 'idle';
    importProgress.value = 0;
    importResults.value = {};
    error.value = null;
    isImporting.value = false;
    
    Logger.debug('[useFileImport] Import state reset');
  };

  /**
   * Cancela una importación en progreso
   */
  const cancelImport = () => {
    if (isImporting.value) {
      isImporting.value = false;
      importStatus.value = 'idle';
      importProgress.value = 0;
      error.value = 'Importación cancelada por el usuario';
      
      Logger.debug('[useFileImport] Import cancelled by user');
    }
  };

  /**
   * Obtiene estadísticas de la última importación
   */
  const getImportStats = () => {
    if (!importResults.value || !isSuccess.value) return null;
    
    return {
      totalItems: importResults.value.total_items || 0,
      successfulItems: importResults.value.successful_items || 0,
      failedItems: importResults.value.failed_items || 0,
      duplicateItems: importResults.value.duplicate_items || 0,
      processingTime: importResults.value.processing_time || 0
    };
  };

  return {
    // Estados
    selectedService,
    selectedFile,
    importStatus,
    importProgress,
    importResults,
    error,
    isImporting,
    availableServices,

    // Estados computados
    canImport,
    currentService,
    acceptedFileTypes,
    isSuccess,
    isError,
    isProcessing,

    // Métodos principales
    setService,
    setFile,
    startImport,
    resetImport,
    cancelImport,

    // Métodos de utilidad
    validateFile,
    getServiceInfo,
    getImportStats,
    clearError,
    updateProgress
  };
}
