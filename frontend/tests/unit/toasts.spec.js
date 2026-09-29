// Los doce avisos que no se veían (Plan «Catálogo de Eventos de Producto», M2).
//
// Doce componentes hacían `inject('notifications', null)` y en `src/` no había ningún
// `provide('notifications')`: sus `showError` / `showSuccess` no llegaban a nada. Solo el helper
// de montaje de los tests lo proveía, así que las pruebas pasaban y la app callaba. Ahora los doce
// hablan con `uiStore`, que es lo que `Layout.vue` saca por pantalla, y aquí se comprueba uno a
// uno que el aviso LLEGA a `uiStore.notifications`.
//
// Cada caso llama directamente al manejador del componente (`wrapper.vm.<handler>`) con la acción
// del store sustituida por un doble: lo que se prueba es el camino del aviso, no el del backend.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, RouterLinkStub } from '@vue/test-utils'
import { mountComponent } from './helpers/mount'
import { useUIStore } from '@/store/ui'
import { useListsStore } from '@/store/lists'
import { useClubsStore } from '@/store/clubs'
import { useSocialStore } from '@/store/social'
import { useInboxStore } from '@/store/inbox'

import EditItemModal from '@/components/EditItemModal.vue'
import InviteToClubDialog from '@/components/Clubs/InviteToClubDialog.vue'
import InviteCollaboratorDialog from '@/components/Lists/InviteCollaboratorDialog.vue'
import AddToListDialog from '@/components/Lists/AddToListDialog.vue'
import AddToClubDialog from '@/components/Clubs/AddToClubDialog.vue'
import MediaNotes from '@/components/shared/MediaNotes.vue'
import RecommendDialog from '@/components/Social/RecommendDialog.vue'
import ClubsView from '@/views/ClubsView.vue'
import ClubDetailView from '@/views/ClubDetailView.vue'
import ListsView from '@/views/ListsView.vue'
import ListDetailView from '@/views/ListDetailView.vue'
import InboxView from '@/views/InboxView.vue'

// Ninguna acción llega al backend: sin sesión, `authenticatedApiCall` lanza y los stores lo
// convierten en `{ success: false }`. Y axios se falsea por si alguna se colara.
const axios = vi.hoisted(() => ({ post: vi.fn(() => Promise.reject(new Error('sin red en los tests'))) }))
vi.mock('axios', () => ({ default: axios }))

const falla = () => vi.fn().mockResolvedValue({ success: false, message: 'Fallo traducido' })
const va = (extra = {}) => vi.fn().mockResolvedValue({ success: true, ...extra })

const item = { entityType: 'movie', entityId: 'tt0111161', entityTitle: 'Cadena perpetua', entityCover: null }

/**
 * `setup` prepara los stores ANTES de montar (el componente recibe esa misma instancia), `act`
 * dispara el manejador y `type` es el aviso que tiene que aparecer.
 */
const casos = [
  {
    name: 'EditItemModal (etiqueta que no se crea)',
    component: EditItemModal,
    props: { item: { id: 1, title: 'Prequelle' }, itemType: 'album' },
    act: (w) => w.vm.handleAddTag('nueva'),
    type: 'error',
  },
  {
    name: 'InviteToClubDialog (invitación enviada)',
    component: InviteToClubDialog,
    props: { modelValue: true, clubId: 1 },
    setup: () => {
      useSocialStore().fetchFriends = vi.fn()
      useClubsStore().inviteToClub = va()
    },
    act: (w) => w.vm.submit(),
    type: 'success',
  },
  {
    name: 'InviteCollaboratorDialog (invitación enviada)',
    component: InviteCollaboratorDialog,
    props: { modelValue: true, listId: 1 },
    setup: () => {
      useSocialStore().fetchFriends = vi.fn()
      useListsStore().inviteCollaborator = va()
    },
    act: (w) => w.vm.submit(),
    type: 'success',
  },
  {
    name: 'AddToListDialog (no se pudo añadir)',
    component: AddToListDialog,
    props: { modelValue: true, ...item },
    setup: () => {
      const lists = useListsStore()
      lists.fetchMyLists = vi.fn()
      lists.addItem = falla()
    },
    act: (w) => { w.vm.listId = 1; return w.vm.submit() },
    type: 'error',
  },
  {
    name: 'AddToClubDialog (no se pudo añadir)',
    component: AddToClubDialog,
    props: { modelValue: true, ...item },
    setup: () => {
      const clubs = useClubsStore()
      clubs.fetchMyClubs = vi.fn()
      clubs.setPick = falla()
    },
    act: (w) => { w.vm.clubId = 1; return w.vm.submit() },
    type: 'error',
  },
  {
    name: 'MediaNotes (nota vacía)',
    component: MediaNotes,
    props: { media: 'album', itemId: 13 },
    act: (w) => w.vm.saveNote(),
    type: 'error',
  },
  {
    name: 'RecommendDialog (recomendación enviada)',
    component: RecommendDialog,
    props: { modelValue: true, ...item },
    setup: () => {
      useSocialStore().fetchFriends = vi.fn()
      useInboxStore().sendRecommendation = va()
    },
    act: (w) => w.vm.send(),
    type: 'success',
  },
  {
    name: 'ClubsView (no se pudo crear)',
    component: ClubsView,
    setup: () => {
      const clubs = useClubsStore()
      clubs.fetchMyClubs = vi.fn()
      clubs.createClub = falla()
    },
    act: (w) => w.vm.handleCreate({ name: 'Club', description: '' }),
    type: 'error',
  },
  {
    name: 'ClubDetailView (no se pudo salir)',
    component: ClubDetailView,
    props: { clubId: '1' },
    setup: () => {
      const clubs = useClubsStore()
      clubs.fetchClub = vi.fn().mockResolvedValue({ success: false })
      clubs.leaveClub = falla()
    },
    act: (w) => w.vm.handleLeave(),
    type: 'error',
  },
  {
    name: 'ListsView (no se pudo crear)',
    component: ListsView,
    setup: () => {
      const lists = useListsStore()
      lists.fetchMyLists = vi.fn()
      lists.createList = falla()
    },
    act: (w) => w.vm.handleCreate({ name: 'Lista', description: '', visibility: 'private' }),
    type: 'error',
  },
  {
    name: 'ListDetailView (no se pudo guardar)',
    component: ListDetailView,
    props: { listId: '1' },
    setup: () => {
      const lists = useListsStore()
      lists.fetchList = vi.fn()
      lists.updateList = falla()
    },
    act: (w) => w.vm.handleEdit({ name: 'Lista', description: '', visibility: 'private' }),
    type: 'error',
  },
  {
    name: 'InboxView (no se pudo añadir)',
    component: InboxView,
    setup: () => {
      const inbox = useInboxStore()
      inbox.fetchInbox = vi.fn()
      inbox.addToLibrary = falla()
    },
    act: (w) => w.vm.handleAdd({ id: 1 }),
    type: 'error',
  },
]

describe('los doce avisos salen por uiStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('son doce, uno por componente de la lista del plan', () => {
    expect(casos).toHaveLength(12)
  })

  it.each(casos.map((c) => [c.name, c]))('%s', async (_name, caso) => {
    caso.setup?.()
    const w = mountComponent(caso.component, {
      props: caso.props ?? {},
      global: { stubs: { RouterLink: RouterLinkStub, 'router-link': RouterLinkStub } },
    })
    await flushPromises()

    await caso.act(w)
    await flushPromises()

    const avisos = useUIStore().notifications
    expect(avisos.map((n) => n.type)).toContain(caso.type)
    expect(avisos.find((n) => n.type === caso.type).message).toBeTruthy()
  })
})
