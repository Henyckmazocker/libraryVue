# Skill: Frontend — Library Vue

## Scope

This skill covers all frontend development: Vue 3 components, Pinia stores, composables, routing, PrimeVue theming, and API communication.

## Tech Stack

- **Framework**: Vue 3 (Composition API + Options API mix, no TypeScript)
- **UI Library**: PrimeVue 4 with Lara theme + custom teal preset (`#1D4E4A`)
- **State Management**: Pinia stores + composables pattern
- **Router**: Vue Router 4 (history mode, no hash)
- **HTTP**: Axios with `withCredentials: true`
- **Build**: Vue CLI (`vue.config.js`)
- **Auth**: Google OAuth (`VUE_APP_GOOGLE_CLIENT_ID`)

## Directory Structure

```
frontend/src/
├── main.js                    # App entry, PrimeVue setup, custom theme preset
├── App.vue                    # Root component
├── router/index.js            # 12 routes with lazy loading
├── components/
│   ├── Books/                 # 9 components (BookSearch, EditionSelector, EditionNotes, etc.)
│   ├── Movies/                # 5 components (MovieSearch, MovieNotes, etc.)
│   ├── Games/                 # 6 components (GameSearch, GameNotes, etc.)
│   ├── Dashboard/             # 11+ components (UnifiedDashboard, *DashboardContent, charts)
│   ├── common/                # 9 shared components (Header, Sidebar, StatusSelector, TagSelector, etc.)
│   ├── shared/                # GenericSearch, HorizontalCarousel
│   ├── import/                # Import workflow components
│   ├── EditItemModal.vue      # Shared edit modal for all entity types
│   ├── ImportModal.vue        # CSV/XML import
│   ├── MyLibrary.vue          # Unified library view
│   └── HomePage.vue           # Landing page
├── composables/               # 24 composables (see below)
├── store/                     # 8 Pinia stores
├── services/                  # FileProcessorService, ImportService, StatsService
├── utils/                     # logger.js, languageConstants.js
├── views/                     # BookDetailView, MovieDetailView, GameDetailView
├── config/                    # App configuration
└── assets/styles/             # CSS/SCSS files
```

## Routes

| Path | Component | Lazy | Auth Required |
|---|---|---|---|
| `/` | HomePage | No | No |
| `/books` | BookSearch | No | No |
| `/movies` | MovieSearch | No | No |
| `/games` | GameSearch | No | No |
| `/library` | MyLibrary | Yes | Yes |
| `/dashboard` | UnifiedDashboard | Yes | Yes |
| `/dashboard/books` | Redirect → `/dashboard?tab=books` | — | — |
| `/books/:isbn` | BookDetailView | Yes | No |
| `/movies/:imdbId` | MovieDetailView | Yes | No |
| `/games/:gameId` | GameDetailView | Yes | No |

Router `afterEach` hook applies dark mode theme from `ui` store.

## State Management Pattern

### Stores (Pinia)

Business logic and API communication live in stores:

| Store | File | State | Key Actions |
|---|---|---|---|
| `auth` | `store/auth.js` | user, isAuthenticated, csrfToken, jwtToken | `apiCall(action, data)`, `authenticatedApiCall()`, `login(token)`, `logout()`, `initializeAuth()` |
| `books` | `store/books.js` | books[], allowedStatuses[], userTags[], searchResults[] | `fetchBooks()`, `addBook()`, `deleteBook()`, `updateBookRating()`, `editUserBook()` |
| `movies` | `store/movies.js` | movies[], allowedStatuses[], userTags[], searchResults[] | `fetchMovies()`, `addMovie()`, `deleteMovie()`, `editUserMovie()` |
| `games` | `store/games.js` | games[], allowedStatuses[], userTags[], gameNotes{}, searchResults[] | `fetchGames()`, `addGame()`, `deleteGame()`, `editUserGame()` |
| `sessions` | `store/sessions.js` | activeSessions{}, sessionHistories{} | `createSession()`, `completeSession()`, `pauseSession()`, `resumeSession()` |
| `ui` | `store/ui.js` | modals{}, notifications[], theme, sidebarOpen, globalLoading | `showConfirmation()`, `showNotification()`, `toggleTheme()` |
| `menu` | `store/menu.js` | menuData, isLoading | `loadMenu()` (from sidebar-menu.json) |

### API Communication Pattern

All backend calls go through the `auth` store's `apiCall()`:

```javascript
// In auth store
async apiCall(action, data = {}) {
  const response = await axios.post(VUE_APP_API_URL, {
    action,
    ...data,
    csrf_token: this.csrfToken
  }, { withCredentials: true });
  return response.data;
}

// In other stores
const authStore = useAuthStore();
const result = await authStore.authenticatedApiCall('get_books', { userId: authStore.user.id });
```

### Composables (24)

UI-specific wrappers around stores and reusable logic:

| Composable | Purpose | Pattern |
|---|---|---|
| `useAuth` | Auth state + login/logout | Wraps auth store |
| `useBooks` | Book operations + confirmations | Wraps books store, adds delete confirmation |
| `useMovies` | Movie operations | Wraps movies store |
| `useGames` | Game operations | Wraps games store |
| `useSearch` | Generic search debounce/results | Standalone logic |
| `useWorkSearch` | OpenLibrary work/edition search | Orchestrates book search flow |
| `useTrending` | Trending items across types | Fetches from API |
| `useReadingProgress` | Page progress tracking | Wraps sessions store |
| `useReadingSessions` | Session CRUD | Wraps sessions store |
| `useEditionNotes` | Book edition notes | CRUD operations |
| `useGameNotes` | Game notes | CRUD operations |
| `useMovieNotes` | Movie notes | CRUD operations |
| `useItemEdit` | Edit modal logic | Shared across entity types |
| `useItemModal` | Detail modal | Shared across entity types |
| `useFileImport` | CSV/XML import workflow | File processing |
| `useConfirmationModal` | Promise-based confirm dialog | Returns { confirm, cancel } |
| `useGoogleAuth` | Google OAuth init | Google Sign-In SDK |
| `usePermissions` | Auth guards | Reactive isOwner checks |
| `useDashboardCharts` | Chart.js data building | Data transformation |
| `useLibraryNotifications` | Toast messages | PrimeVue Toast integration |
| `useSessionFeedback` | Session action feedback | UX messages |
| `useSidebarMenu` | Sidebar menu data | Wraps menu store |
| `useTheme` | Dark/light toggle | Wraps ui store |

**Critical pattern**: Always use `storeToRefs()` to get reactive state from stores in composables:

```javascript
import { storeToRefs } from 'pinia';
import { useGamesStore } from '@/store/games';

export function useGames() {
  const store = useGamesStore();
  const { games, allowedStatuses, searchResults } = storeToRefs(store);
  
  // Wrap store actions with UI logic
  const deleteGame = async (gameId) => {
    const confirmed = await useConfirmationModal().confirm('Delete this game?');
    if (confirmed) await store.deleteGame(gameId);
  };
  
  return { games, allowedStatuses, deleteGame, ...store };
}
```

## Component Patterns

### Entity Components (Books/Movies/Games)

Each entity type follows the same component structure:

- **`{Entity}Search.vue`** — Search and discover items from external APIs
- **`Library{Entity}Item.vue`** — Card in user's library view
- **`{Entity}CarouselItem.vue`** — Card in trending/carousel displays
- **`{Entity}ListItem.vue`** — Row in list/table views
- **`{Entity}Notes.vue`** — Notes management (if applicable)

### Dashboard Components

- **`UnifiedDashboard.vue`** — Tab container (books/movies/games tabs via query param)
- **`{Entity}Dashboard.vue`** — Tab wrapper per entity
- **`{Entity}DashboardContent.vue`** — Actual dashboard content with stats and charts
- **`DashboardChartsGrid.vue`** — Reusable charts layout
- **`DashboardStatsGrid.vue`** — Reusable stats cards

### Shared Edit Modal

`EditItemModal.vue` handles editing for ALL entity types. It detects the type and adapts fields:

```vue
<!-- Props pattern -->
<EditItemModal 
  :item="selectedItem" 
  :type="'game'" 
  :visible="showEditModal"
  @save="handleSave" 
  @close="showEditModal = false" 
/>
```

**Frontend sends nested `data` in edit payloads**:
```javascript
// EditItemModal emits:
{ gameId, userId, data: { personalRating, statuses, dateStarted, ... }, tags, notes }
```

### PrimeVue Components Used

Pre-configured and available globally: `MultiSelect`, `DataTable`, `Dialog`, `Toast`, `Button`, `InputText`, `Calendar`, `Rating`, `Dropdown`, `TabView`, `Chart`, etc.

## Key Conventions

1. **File naming**: PascalCase for components (`.vue`), camelCase for JS files
2. **Component naming in templates**: kebab-case (`<book-search />`)
3. **Props**: Use `defineProps()` in `<script setup>` or `props:` in Options API
4. **API URL**: Always via `VUE_APP_API_URL` env var — never hardcode
5. **Action names**: Match backend route names exactly (`add_book`, `get_games`, `update_book_rating`)
6. **Item identifiers**:
   - Books: `isbn` (ISBN-13/10)
   - Movies: `isbn` (TMDb/IMDb ID — legacy naming, do NOT rename)
   - Games: `id` (IGDB ID)
7. **Reactivity for dual-format fields**: Handle both camelCase and snake_case from API:
   ```javascript
   const dateStarted = ref(props.game.dateStarted || props.game.date_started || '');
   ```
8. **Watchers with `immediate: true`** for prop-driven reactivity on mount
9. **Dark mode**: Managed by `useTheme` composable and `ui` store, applied via CSS classes

## Adding a New Feature

### New Component

1. Create in `components/{Entity}/NewComponent.vue`
2. Use Composition API with `<script setup>`
3. Import composable for state: `const { games } = useGames()`
4. Use PrimeVue components for UI

### New Store Action

```javascript
// In store/{entity}.js
async newAction(params) {
  const authStore = useAuthStore();
  const result = await authStore.authenticatedApiCall('action_name', params);
  if (!result.error) {
    // Update local state
  }
  return result;
}
```

### New Composable

```javascript
// In composables/useNewFeature.js
import { ref, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useEntityStore } from '@/store/entity';

export function useNewFeature() {
  const store = useEntityStore();
  const { relevantState } = storeToRefs(store);
  
  const localState = ref(null);
  
  const doSomething = async () => {
    await store.someAction();
  };
  
  return { relevantState, localState, doSomething };
}
```

### New Route

```javascript
// In router/index.js
{
  path: '/new-path/:id',
  name: 'NewView',
  component: () => import('@/views/NewView.vue'),  // Lazy load
  meta: { requiresAuth: true }
}
```

## Services

| Service | File | Purpose |
|---|---|---|
| `FileProcessorService` | `services/FileProcessorService.js` | Parses CSV/XML (Goodreads, Letterboxd, Palomitacas, Serialized formats), enriches via OMDb API |
| `ImportService` | `services/ImportService.js` | Orchestrates file processing → backend `import_data` action |
| `StatsService` | `services/StatsService.js` | Fetches book/movie/game stats via `fetch()` directly |

## Debugging

- **Browser DevTools → Network**: Check API calls, verify action names and payloads
- **Vue DevTools**: Inspect Pinia store state, component tree
- **Console logging**: Use `Logger` from `utils/logger.js` (supports `debug`, `info`, `warn`, `error`)
- **Dual-format check**: If data doesn't show, check both `camelCase` and `snake_case` field names in API response
- **Hot reload**: Frontend runs with HMR in dev mode via Docker volume mounts

## Docker Development

```bash
# Frontend runs on http://localhost:8080
# Hot-reload via volume mount of frontend/src/

# Rebuild frontend container
docker compose up -d --build frontend

# View frontend logs
docker compose logs -f frontend

# Install new npm package
docker compose exec frontend npm install package-name
```
