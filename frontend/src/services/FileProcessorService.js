import axios from 'axios';
import Logger from '@/utils/logger';

/**
 * Servicio para procesar archivos de diferentes servicios de importación
 */
export class FileProcessorService {
  static async processFile(file, service) {
    switch (service) {
      case 'palomitacas':
        return await this.processPalomitacasFile(file);
      case 'letterboxd':
        return await this.processLetterboxdFile(file);
      case 'goodreads':
        return await this.processGoodreadsFile(file);
      case 'serialized':
        return await this.processSerializedFile(file);
      default:
        throw new Error(`Servicio no soportado: ${service}`);
    }
  }

  /**
   * Procesa archivos XML de Palomitacas obteniendo datos completos de OMDb
   */
  static async processPalomitacasFile(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = async (e) => {
        try {
          const xmlText = e.target.result;
          const parser = new DOMParser();
          const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
          
          const movies = xmlDoc.querySelectorAll('pelicula');
          const processedMovies = [];
          
          Logger.debug(`Found ${movies.length} movies in Palomitacas XML`);
          
          // Procesar películas en lotes para evitar sobrecargar la API
          const batchSize = 5;
          for (let i = 0; i < movies.length; i += batchSize) {
            const batch = Array.from(movies).slice(i, i + batchSize);
            
            const batchPromises = batch.map(async (movie) => {
              const imdbID = movie.querySelector('id_imdb')?.textContent || '';
              
              // Solo procesar películas que tengan imdbID válido
              if (!imdbID || imdbID.trim() === '') {
                Logger.warn('Película sin imdbID válido, saltando...');
                return null;
              }
              
              try {
                // Obtener datos completos de OMDb via backend proxy
                const apiUrl = process.env.VUE_APP_API_URL || '/index.php';
                const omdbResponse = await axios.post(apiUrl, { action: 'get_movie_details_omdb', imdbId: imdbID, plot: 'short' });
                
                if (omdbResponse.data?.status === 'success' && omdbResponse.data?.data) {
                  const omdbData = omdbResponse.data.data;
                  
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
                    // Campos requeridos por nuestro modelo Movie
                    id: imdbID,
                    title: omdbData.Title || 'Título desconocido',
                    originalTitle: omdbData.Title || null,
                    director: omdbData.Director !== 'N/A' ? omdbData.Director : null,
                    coverUrl: omdbData.Poster !== 'N/A' ? omdbData.Poster : null,
                    rating: userRating,
                    userStatuses: this.mapPalomitacasStatus(movie.querySelector('estado')?.textContent || '0'),
                    addedTimestamp: Date.now(),
                    
                    // Campos adicionales de OMDb
                    year: omdbData.Year,
                    genre: omdbData.Genre,
                    plot: omdbData.Plot,
                    imdbRating: omdbData.imdbRating,
                    
                    // Campos adicionales de Palomitacas
                    palomitacasId: movie.querySelector('id')?.textContent || '',
                    viewedDate: movie.querySelector('fecha_vista')?.textContent || null,
                    tipo: movie.querySelector('tipo')?.textContent || 'película'
                  };
                  
                  return movieData;
                } else {
                  Logger.warn(`No se pudo obtener datos de OMDb para imdbID: ${imdbID}`);
                  return null;
                }
              } catch (error) {
                Logger.error(`Error obteniendo datos de OMDb para ${imdbID}:`, error);
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
            
            Logger.debug(`Procesado lote ${Math.floor(i/batchSize) + 1}/${Math.ceil(movies.length/batchSize)}`);
          }

          Logger.debug(`Procesadas ${processedMovies.length} películas de Palomitacas con datos completos de OMDb`);
          resolve(processedMovies);
        } catch (error) {
          reject(new Error(`Error parsing Palomitacas XML: ${error.message}`));
        }
      };
      
      reader.onerror = () => reject(new Error('Error reading file'));
      reader.readAsText(file, 'UTF-8');
    });
  }

  /**
   * Procesa archivos CSV de Letterboxd
   */
  static async processLetterboxdFile(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const csvData = e.target.result;
          const lines = csvData.split('\n');
          const headers = lines[0].split(',').map(h => h.replace(/"/g, '').trim());
          
          const processedMovies = [];
          
          for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            
            const values = line.split(',').map(v => v.replace(/"/g, '').trim());
            const movie = {};
            
            headers.forEach((header, index) => {
              movie[header] = values[index] || '';
            });
            
            // Mapear campos de Letterboxd a nuestro formato
            const processedMovie = {
              id: movie['Letterboxd URI'] || `letterboxd_${Date.now()}_${i}`,
              title: movie['Name'] || '',
              originalTitle: movie['Name'] || '',
              director: movie['Director'] || '',
              rating: movie['Rating'] && movie['Rating'] !== '' ? parseFloat(movie['Rating']) : null,
              user_rating: movie['Rating'] && movie['Rating'] !== '' ? parseFloat(movie['Rating']) : null,
              userStatuses: movie['Watched Date'] ? ['viewed'] : ['in-watchlist'],
              addedTimestamp: movie['Watched Date'] ? new Date(movie['Watched Date']).getTime() : Date.now()
            };
            
            if (processedMovie.title) {
              processedMovies.push(processedMovie);
            }
          }
          
          if (processedMovies.length === 0) {
            throw new Error('No se encontraron películas válidas en el archivo CSV');
          }
          
          resolve(processedMovies);
        } catch (error) {
          reject(new Error(`Error al procesar archivo de Letterboxd: ${error.message}`));
        }
      };
      
      reader.onerror = () => {
        reject(new Error('Error al leer el archivo'));
      };
      
      reader.readAsText(file);
    });
  }

  /**
   * Procesa archivos CSV de Goodreads
   */
  static async processGoodreadsFile(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const csvData = e.target.result;

          // Parse CSV properly handling quoted values with commas
          const lines = csvData.split('\n');
          const headers = this.parseCSVLine(lines[0]);

          const processedBooks = [];

          for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;

            const values = this.parseCSVLine(line);
            if (values.length !== headers.length) {
              Logger.warn(`Line ${i} has ${values.length} values but expected ${headers.length}, skipping`);
              continue;
            }

            const book = {};
            headers.forEach((header, index) => {
              book[header] = values[index] || '';
            });

            // Mapear campos de Goodreads a nuestro formato
            // Mapear estados de Goodreads a nuestro sistema
            let userStatuses = ['owned']; // Default status
            if (book['Exclusive Shelf']) {
              const goodreadsShelf = book['Exclusive Shelf'].toLowerCase().trim();
              const statusMap = {
                'to-read': ['to-read'],
                'currently-reading': ['reading'],
                'read': ['read'],
                'owned': ['owned'],
                'want-to-read': ['to-read']
              };
              userStatuses = statusMap[goodreadsShelf] || ['owned'];
            }

            // Extraer bookshelves personalizadas como tags
            const customTags = [];
            if (book['Bookshelves'] && book['Bookshelves'].trim() !== '') {
              // Goodreads separa las bookshelves con comas
              const shelves = book['Bookshelves'].split(',').map(s => s.trim());
              shelves.forEach(shelf => {
                if (shelf && shelf !== '') {
                  // Normalizar el nombre del tag (lowercase, trim)
                  const normalizedShelf = shelf.toLowerCase().trim();

                  // Solo agregar si no es una shelf exclusiva (que ya se maneja como status)
                  const exclusiveShelves = ['to-read', 'currently-reading', 'read', 'owned', 'want-to-read'];
                  if (!exclusiveShelves.includes(normalizedShelf)) {
                    customTags.push(shelf); // Mantener capitalización original
                  }
                }
              });
            }

            const processedBook = {
              isbn: book['ISBN13'] || book['ISBN'] || `goodreads_${book['Book Id']}`,
              title: book['Title'] || '',
              author: book['Author'] || '',
              publisher: book['Publisher'] || '',
              publicationDate: book['Year Published'] || book['Original Publication Year'] || '',
              pages: book['Number of Pages'] ? parseInt(book['Number of Pages']) : null,
              rating: book['My Rating'] && book['My Rating'] !== '0' ? parseFloat(book['My Rating']) : null,
              user_rating: book['My Rating'] && book['My Rating'] !== '0' ? parseFloat(book['My Rating']) : null,
              userStatuses: userStatuses,
              customTags: customTags, // Nuevo campo para tags personalizados
              addedTimestamp: book['Date Added'] ? new Date(book['Date Added']).getTime() : Date.now()
            };

            if (processedBook.title) {
              processedBooks.push(processedBook);
            }
          }

          if (processedBooks.length === 0) {
            throw new Error('No se encontraron libros válidos en el archivo CSV');
          }

          resolve(processedBooks);
        } catch (error) {
          reject(new Error(`Error al procesar archivo de Goodreads: ${error.message}`));
        }
      };

      reader.onerror = () => {
        reject(new Error('Error al leer el archivo'));
      };

      reader.readAsText(file);
    });
  }

  /**
   * Parse a CSV line correctly handling quoted values
   */
  static parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
      const char = line[i];
      const nextChar = line[i + 1];

      if (char === '"') {
        if (inQuotes && nextChar === '"') {
          // Escaped quote
          current += '"';
          i++; // Skip next quote
        } else {
          // Toggle quote mode
          inQuotes = !inQuotes;
        }
      } else if (char === ',' && !inQuotes) {
        // End of field
        result.push(current.trim());
        current = '';
      } else {
        current += char;
      }
    }

    // Add last field
    result.push(current.trim());

    return result;
  }

  /**
   * Procesa archivos JSON serializados
   */
  static async processSerializedFile(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const jsonData = JSON.parse(e.target.result);
          
          // Verificar si es un array o un objeto con libros/películas
          let processedData = [];
          
          if (Array.isArray(jsonData)) {
            processedData = jsonData;
          } else if (jsonData.books && Array.isArray(jsonData.books)) {
            processedData = [...processedData, ...jsonData.books];
          } else if (jsonData.movies && Array.isArray(jsonData.movies)) {
            processedData = [...processedData, ...jsonData.movies];
          } else if (jsonData.data && Array.isArray(jsonData.data)) {
            processedData = jsonData.data;
          } else {
            throw new Error('Formato de archivo serializado no reconocido');
          }
          
          if (processedData.length === 0) {
            throw new Error('No se encontraron elementos para importar');
          }
          
          resolve(processedData);
        } catch (error) {
          reject(new Error(`Error al procesar archivo serializado: ${error.message}`));
        }
      };
      
      reader.onerror = () => {
        reject(new Error('Error al leer el archivo'));
      };
      
      reader.readAsText(file);
    });
  }

  /**
   * Mapea códigos de estado de Palomitacas a nuestro sistema
   */
  static mapPalomitacasStatus(estadoCode) {
    const statusMap = {
      '0': ['in-watchlist'],        // No vista / Quiere ver -> in-watchlist
      '1': ['owned'],               // Tengo / En biblioteca -> owned
      '3': ['viewed'],              // Vista -> viewed
      '4': ['in-watchlist']         // Abandonada -> in-watchlist
    };

    return statusMap[estadoCode] || ['in-watchlist'];
  }

  /**
   * Formatea el tamaño de archivo en formato legible
   */
  static formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
}

export default FileProcessorService;
