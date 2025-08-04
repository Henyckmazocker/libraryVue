<template>
  <!-- Import Modal -->
  <div v-if="show" class="modal-overlay" @click="handleClose">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2><i class="fas fa-upload"></i> Importar datos</h2>
        <button @click="handleClose" class="close-button">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="modal-body">
        <div class="form-group">
          <label for="service-select">Selecciona el servicio:</label>
          <select id="service-select" v-model="selectedService" class="service-dropdown">
            <option value="">-- Selecciona un servicio --</option>
            <option value="palomitacas">Palomitacas</option>
            <option value="letterboxd">Letterboxd</option>
            <option value="goodreads">Goodreads</option>
            <option value="serialized">Serialized</option>
          </select>
        </div>

        <div class="form-group">
          <label for="file-input">Archivo de respaldo:</label>
          <input 
            id="file-input"
            type="file" 
            ref="fileInput"
            @change="handleFileSelect"
            accept=".csv,.json,.xml,.txt"
            class="file-input"
          />
          <div v-if="selectedFile" class="file-info">
            <i class="fas fa-file"></i> {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
          </div>
        </div>

        <div v-if="importStatus.message" :class="['import-status', importStatus.type]">
          {{ importStatus.message }}
        </div>
      </div>

      <div class="modal-footer">
        <button @click="handleClose" class="cancel-button">
          <i class="fas fa-times"></i>
        </button>
        <button 
          @click="handleImport" 
          :disabled="!selectedService || !selectedFile || importStatus.loading"
          class="import-submit-button"
        >
          <i v-if="importStatus.loading" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-upload"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import axios from 'axios';

// Props
const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
});

// Emits
const emit = defineEmits(['close', 'import-success']);

// Reactive data
const selectedService = ref('');
const selectedFile = ref(null);
const importStatus = ref({
  message: '',
  type: '', // 'success', 'error', 'info'
  loading: false
});

// Methods
const handleClose = () => {
  resetForm();
  emit('close');
};

const resetForm = () => {
  selectedService.value = '';
  selectedFile.value = null;
  importStatus.value = { message: '', type: '', loading: false };
  // Reset file input
  const fileInput = document.getElementById('file-input');
  if (fileInput) {
    fileInput.value = '';
  }
};

const handleFileSelect = (event) => {
  const file = event.target.files[0];
  selectedFile.value = file || null;
  if (file) {
    importStatus.value = { message: '', type: '', loading: false };
  }
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const handleImport = async () => {
  if (!selectedService.value || !selectedFile.value) {
    importStatus.value = {
      message: 'Por favor selecciona un servicio y un archivo.',
      type: 'error',
      loading: false
    };
    return;
  }

  importStatus.value = {
    message: selectedService.value === 'palomitacas' 
      ? 'Procesando archivo y obteniendo datos de OMDb...' 
      : 'Procesando archivo...',
    type: 'info',
    loading: true
  };

  try {
    // Process the file according to the selected service
    let processedData;
    
    switch (selectedService.value) {
      case 'palomitacas': {
        processedData = await processPalomitacasFile(selectedFile.value);
        
        // Actualizar mensaje de estado para la importación al backend
        importStatus.value = {
          message: `Enviando ${processedData.length} películas al servidor...`,
          type: 'info',
          loading: true
        };

        // Llamar inmediatamente al endpoint de importación
        const requestData = {
          action: 'import_data',
          service: selectedService.value,
          processedData: processedData
        };

        const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
        const response = await axios.post(backendApiUrl, requestData, {
          headers: {
            'Content-Type': 'application/json',
          },
        });

        // Manejar la respuesta inmediatamente
        if (response.data && response.data.status === 'success') {
          const importData = response.data.data;
          const successMsg = `${importData.imported} películas importadas correctamente de ${selectedService.value}`;
          const detailMsg = importData.skipped > 0 ? ` (${importData.skipped} omitidas por duplicado)` : '';
          
          importStatus.value = {
            message: successMsg + detailMsg,
            type: 'success',
            loading: false
          };
          
          // Mostrar errores si los hay
          if (importData.errors && importData.errors.length > 0) {
            console.warn('Errores durante la importación:', importData.errors);
          }
          
          // Emit success event to parent component
          emit('import-success', {
            service: selectedService.value,
            fileName: selectedFile.value.name,
            data: response.data,
            count: importData.imported,
            skipped: importData.skipped,
            errors: importData.errors
          });
          
          // Close modal after successful import
          setTimeout(() => {
            handleClose();
          }, 2000);
          
          return; // Salir de la función ya que hemos manejado todo
        } else {
          importStatus.value = {
            message: response.data.message || 'Error al importar los datos.',
            type: 'error',
            loading: false
          };
          return; // Salir de la función en caso de error
        }
      }
      case 'letterboxd':
        processedData = await processLetterboxdFile(selectedFile.value);
        break;
      case 'goodreads':
        processedData = await processGoodreadsFile(selectedFile.value);
        break;
      case 'serialized':
        processedData = await processSerializedFile(selectedFile.value);
        break;
      default:
        throw new Error('Servicio no soportado');
    }

    // Send processed data to backend (solo para otros servicios, Palomitacas se maneja en el switch)
    if (selectedService.value !== 'palomitacas') {
      const requestData = {
        action: 'import_data',
        service: selectedService.value,
        processedData: processedData
      };

      const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
      const response = await axios.post(backendApiUrl, requestData, {
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (response.data && response.data.status === 'success') {
        const importData = response.data.data;
        const successMsg = `${importData.imported} películas importadas correctamente de ${selectedService.value}`;
        const detailMsg = importData.skipped > 0 ? ` (${importData.skipped} omitidas por duplicado)` : '';
        
        importStatus.value = {
          message: successMsg + detailMsg,
          type: 'success',
          loading: false
        };
        
        // Mostrar errores si los hay
        if (importData.errors && importData.errors.length > 0) {
          console.warn('Errores durante la importación:', importData.errors);
        }
        
        // Emit success event to parent component
        emit('import-success', {
          service: selectedService.value,
          fileName: selectedFile.value.name,
          data: response.data,
          count: importData.imported,
          skipped: importData.skipped,
          errors: importData.errors
        });
        
        // Close modal after successful import
        setTimeout(() => {
          handleClose();
        }, 2000);
      } else {
        importStatus.value = {
          message: response.data.message || 'Error al importar los datos.',
          type: 'error',
          loading: false
        };
      }
    }
  } catch (error) {
    console.error('Error importing data:', error);
    importStatus.value = {
      message: `Error procesando archivo: ${error.message}`,
      type: 'error',
      loading: false
    };
  }
};

// Processing functions for different services
const processPalomitacasFile = async (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = async (e) => {
      try {
        const xmlText = e.target.result;
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
        
        const movies = xmlDoc.querySelectorAll('pelicula');
        const processedMovies = [];
        const apiKey = 'f03583fd'; // Misma API key que usamos en MovieSearch
        
        console.log(`Found ${movies.length} movies in Palomitacas XML`);
        
        // Procesar películas en lotes para evitar sobrecargar la API
        const batchSize = 5;
        for (let i = 0; i < movies.length; i += batchSize) {
          const batch = Array.from(movies).slice(i, i + batchSize);
          
          const batchPromises = batch.map(async (movie) => {
            const imdbID = movie.querySelector('id_imdb')?.textContent || '';
            
            // Solo procesar películas que tengan imdbID válido
            if (!imdbID || imdbID.trim() === '') {
              console.warn('Película sin imdbID válido, saltando...');
              return null;
            }
            
            try {
              // Obtener datos completos de OMDb
              const omdbUrl = `https://www.omdbapi.com/?apikey=${apiKey}&i=${imdbID}`;
              const omdbResponse = await axios.get(omdbUrl);
              
              if (omdbResponse.data && omdbResponse.data.Response === 'True') {
                const omdbData = omdbResponse.data;
                
                // Procesar rating de Palomitacas (rating del usuario)
                const rawRating = movie.querySelector('mi_valoracion')?.textContent;
                let userRating = null;
                
                // Convertir rating de Palomitacas (0-10) a nuestro sistema (0.5-5.0)
                if (rawRating && !isNaN(parseFloat(rawRating))) {
                  const palomitacasRating = parseFloat(rawRating);
                  if (palomitacasRating > 0) {
                    // Convertir de escala 0-10 a 0.5-5.0
                    userRating = Math.max(0.5, Math.min(5.0, palomitacasRating / 2));
                    // Redondear a múltiplos de 0.5
                    userRating = Math.round(userRating * 2) / 2;
                  }
                }
                
                const movieData = {
                  // Campos requeridos por nuestro modelo Movie (datos completos de OMDb)
                  id: imdbID,
                  title: omdbData.Title || 'Título desconocido',
                  originalTitle: omdbData.Title || null,
                  director: omdbData.Director !== 'N/A' ? omdbData.Director : null,
                  coverUrl: omdbData.Poster !== 'N/A' ? omdbData.Poster : null,
                  rating: userRating, // Rating del usuario de Palomitacas
                  userStatuses: mapPalomitacasStatus(movie.querySelector('estado')?.textContent || '0'),
                  addedTimestamp: Date.now(),
                  
                  // Campos adicionales de OMDb para contexto
                  year: omdbData.Year,
                  genre: omdbData.Genre,
                  plot: omdbData.Plot,
                  imdbRating: omdbData.imdbRating,
                  
                  // Campos adicionales de Palomitacas para referencia
                  palomitacasId: movie.querySelector('id')?.textContent || '',
                  viewedDate: movie.querySelector('fecha_vista')?.textContent || null,
                  tipo: movie.querySelector('tipo')?.textContent || 'película'
                };
                
                return movieData;
              } else {
                console.warn(`No se pudo obtener datos de OMDb para imdbID: ${imdbID}`);
                return null;
              }
            } catch (error) {
              console.error(`Error obteniendo datos de OMDb para ${imdbID}:`, error);
              return null;
            }
          });
          
          // Esperar a que termine el lote actual
          const batchResults = await Promise.all(batchPromises);
          
          // Filtrar resultados nulos y agregar al array final
          batchResults.forEach(result => {
            if (result) {
              processedMovies.push(result);
            }
          });
          
          // Pausa pequeña entre lotes para no sobrecargar la API
          if (i + batchSize < movies.length) {
            await new Promise(resolve => setTimeout(resolve, 200));
          }
          
          console.log(`Procesado lote ${Math.floor(i/batchSize) + 1}/${Math.ceil(movies.length/batchSize)}`);
        }

        console.log(`Procesadas ${processedMovies.length} películas de Palomitacas con datos completos de OMDb`);
        resolve(processedMovies);
      } catch (error) {
        reject(new Error(`Error parsing Palomitacas XML: ${error.message}`));
      }
    };
    
    reader.onerror = () => reject(new Error('Error reading file'));
    reader.readAsText(file, 'UTF-8');
  });
};

// Map Palomitacas status codes to our status system
const mapPalomitacasStatus = (estadoCode) => {
  const statusMap = {
    '0': ['in watchlist'],        // No vista / Quiere ver -> in watchlist
    '1': ['owned'],               // Tengo / En biblioteca -> owned  
    '3': ['viewed'],              // Vista -> viewed
    '4': ['in watchlist']         // Abandonada -> in watchlist (no tenemos estado "dropped" para películas)
  };
  
  return statusMap[estadoCode] || ['in watchlist'];
};

// Placeholder functions for other services
const processLetterboxdFile = async (file) => { // eslint-disable-line no-unused-vars
  // TODO: Implement Letterboxd CSV processing
  throw new Error('Importación de Letterboxd no implementada aún');
};

const processGoodreadsFile = async (file) => { // eslint-disable-line no-unused-vars
  // TODO: Implement Goodreads CSV processing
  throw new Error('Importación de Goodreads no implementada aún');
};

const processSerializedFile = async (file) => { // eslint-disable-line no-unused-vars
  // TODO: Implement Serialized processing
  throw new Error('Importación de Serialized no implementada aún');
};

// Watch for show prop changes to reset form when modal opens
watch(() => props.show, (newValue) => {
  if (newValue) {
    resetForm();
  }
});
</script>

<style scoped>
/* Modal styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: #2c2c2c;
  border-radius: 20px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 25px 30px 15px;
  border-bottom: 1px solid #444;
}

.modal-header h2 {
  color: #e0e0e0;
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.close-button {
  background: none;
  border: none;
  color: #888;
  font-size: 2rem;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.close-button:hover {
  color: #e0e0e0;
  background: rgba(255, 255, 255, 0.1);
}

.modal-body {
  padding: 25px 30px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  color: #e0e0e0;
  font-weight: 500;
  margin-bottom: 8px;
  font-size: 1rem;
}

.service-dropdown {
  width: 100%;
  padding: 12px 15px;
  font-size: 1rem;
  border: 1px solid #555;
  border-radius: 12px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  cursor: pointer;
  transition: border-color 0.2s ease;
}

.service-dropdown:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}

.file-input {
  width: 100%;
  padding: 12px 15px;
  font-size: 1rem;
  border: 2px dashed #555;
  border-radius: 12px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  cursor: pointer;
  transition: all 0.2s ease;
}

.file-input:hover {
  border-color: #007bff;
  background-color: #404040;
}

.file-input:focus {
  outline: none;
  border-color: #007bff;
  border-style: solid;
}

.file-info {
  margin-top: 10px;
  padding: 10px 15px;
  background: rgba(0, 123, 255, 0.1);
  border: 1px solid rgba(0, 123, 255, 0.3);
  border-radius: 8px;
  color: #007bff;
  font-size: 0.9rem;
}

.import-status {
  padding: 12px 15px;
  border-radius: 8px;
  font-size: 0.9rem;
  margin-top: 15px;
}

.import-status.success {
  background: rgba(40, 167, 69, 0.15);
  color: #28a745;
  border: 1px solid rgba(40, 167, 69, 0.3);
}

.import-status.error {
  background: rgba(220, 53, 69, 0.15);
  color: #dc3545;
  border: 1px solid rgba(220, 53, 69, 0.3);
}

.import-status.info {
  background: rgba(0, 123, 255, 0.15);
  color: #007bff;
  border: 1px solid rgba(0, 123, 255, 0.3);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 15px;
  padding: 20px 30px 25px;
  border-top: 1px solid #444;
}

.cancel-button {
  padding: 10px 20px;
  font-size: 1rem;
  background: transparent;
  color: #888;
  border: 1px solid #555;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cancel-button:hover {
  color: #e0e0e0;
  border-color: #888;
}

.import-submit-button {
  padding: 10px 20px;
  font-size: 1rem;
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
}

.import-submit-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #0056b3, #004085);
  transform: translateY(-1px);
}

.import-submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
</style>
