import { createMediaStore } from './createMediaStore'

/**
 * Games Store using Pinia
 *
 * La implementación vive en createMediaStore, configurada desde mediaRegistry.
 * Los nombres que usaban los consumidores (fetchGames, addGame, totalGames,
 * getGameById…) se generan como alias, así que nada de fuera cambia.
 *
 * Las acciones de notas (fetchGameNotes, addGameNote, updateGameNote,
 * deleteGameNote) se han eliminado: su lógica vive en useMediaNotes('game')
 * desde el plan de componentes genéricos.
 */
export const useGamesStore = createMediaStore('game')
