import { useBooks } from './useBooks'
import { useMovies } from './useMovies'
import { useGames } from './useGames'
import Logger from '@/utils/logger'

/**
 * Composable para edición unificada de items (books/movies)
 */
export function useItemEdit() {
  const booksComposable = useBooks()
  const moviesComposable = useMovies()
  const gamesComposable = useGames()
  
  const editItem = async (itemType, id, data, tags = [], notes = []) => {
    try {
      Logger.debug(`[useItemEdit] Editando ${itemType}:`, { id, data, tags, notes })
      
      let result
      if (itemType === 'book') {
        result = await booksComposable.editUserBook(id, data.userId || data.user_id, data, tags, notes)
      } else if (itemType === 'movie') {
        result = await moviesComposable.editUserMovie(id, data.userId || data.user_id, data, tags, notes)
      } else if (itemType === 'game') {
        result = await gamesComposable.editUserGame(id, data.userId || data.user_id, data, tags, notes)
      } else {
        throw new Error(`Tipo de item no soportado: ${itemType}`)
      }
      
      return result
    } catch (error) {
      Logger.error(`[useItemEdit] Error editando ${itemType}:`, error)
      return { 
        success: false, 
        message: error.message || `Error al editar ${itemType}` 
      }
    }
  }
  
  return { editItem }
}
