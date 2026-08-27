import { useBooks } from './useBooks'
import { useMovies } from './useMovies'
import { useGames } from './useGames'
import { useAlbums } from './useAlbums'
import { useVideos } from './useVideos'
import Logger from '@/utils/logger'

/**
 * Edición unificada de los cinco medios.
 *
 * Los cinco brazos tienen la misma forma. Vídeos fue el último en tenerla: hasta
 * que `useVideos` existió llamaba a `videosStore.editVideo` con un payload plano,
 * sin `userId`, sin `tags` y sin `notes`, así que editar un vídeo desde
 * `EditItemModal` no podía guardarle etiquetas. El backend ya lo admitía —
 * `EditUserVideoCommand::fromArray` lee el payload anidado y los `tags` de la
 * raíz—; era el frontend el que no lo usaba.
 */
export function useItemEdit() {
  const booksComposable = useBooks()
  const moviesComposable = useMovies()
  const gamesComposable = useGames()
  const albumsComposable = useAlbums()
  const videosComposable = useVideos()
  
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
      } else if (itemType === 'album') {
        result = await albumsComposable.editUserAlbum(id, data.userId || data.user_id, data, tags, notes)
      } else if (itemType === 'video') {
        result = await videosComposable.editUserVideo(id, data.userId || data.user_id, data, tags, notes)
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
