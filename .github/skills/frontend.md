# Skill: Frontend — Library Vue

## Scope

This skill covers all frontend development: Vue 3 components, Pinia stores, composables, routing, PrimeVue theming, and API communication.

## Tech Stack

- **Framework**: Vue 3 (Composition API + Options API mix, no TypeScript)
- **UI Library**: PrimeVue 4 with Lara theme + custom teal preset (`#1D4E4A`)
- **State Management**: Pinia stores + composables pattern
- **Router**: Vue Router 4 (`createWebHashHistory` for mobile builds, `createWebHistory` for web)
- **HTTP**: Axios with `withCredentials: true`
- **Build**: Vue CLI (`vue.config.js`)
- **Auth**: Google OAuth (`VUE_APP_GOOGLE_CLIENT_ID`) — native Google Sign-In via Capacitor on mobile

## Directory Structure

```
frontend/src/
├── main.js                    # App entry, PrimeVue setup, custom theme preset
├── App.vue                    # Root component
├── router/index.js            # 20+ routes with lazy loading
├── components/
│   ├── Books/                 # 9 components (BookSearch, EditionSelector, EditionNotes, etc.)
│   ├── Movies/                # 5 components (MovieSearch, MovieNotes, SeriesSeasonTracker, etc.)
│   ├── Games/                 # 6 components (GameSearch, GameNotes, etc.)
│   ├── Albums/                # 6 components (AlbumSearch, AlbumNotes, LibraryAlbumItem, ListeningStats, etc.)
│   ├── Videos/                # 6 components (VideoSearch, VideoNotes, LibraryVideoItem, VideoCarouselItem, VideoListItem, etc.)
│   ├── Dashboard/             # 11+ components (UnifiedDashboard, *DashboardContent, charts)
│   ├── common/                # 9 shared components (Header, Sidebar, StatusSelector, TagSelector, MobileNavBar, etc.)
│   ├── shared/                # GenericSearch, HorizontalCarousel, TrendingCarousel
│   ├── import/                # Import workflow components
│   ├── EditItemModal.vue      # Shared edit modal for all entity types
│   ├── ImportModal.vue        # CSV/XML import
│   ├── MyLibrary.vue          # Unified library view
│   └── HomePage.vue           # Landing page
├── composables/               # 30+ composables (see below)
├── store/                     # 9 Pinia stores
├── services/                  # FileProcessorService, ImportService, StatsService
├── utils/                     # logger.js, languageConstants.js, storeHelpers.js
├── views/                     # BookDetailView, MovieDetailView, GameDetailView, AlbumDetailView, VideoDetailView, SeriesDetailView, UserProfileView, NotFoundView
├── config/                    # design-tokens.js, primevue-preset.js
└── assets/styles/             # CSS/SCSS files
```

## Routes

| Path | Component | Auth Required |
|---|---|---|
| `/` | HomePage | No |
| `/books` | BookSearch | No |
| `/movies` | MovieSearch | No |
| `/games` | GameSearch | No |
| `/albums` | AlbumSearch | No |
| `/videos` | VideoSearch | Yes |
| `/library` | MyLibrary | Yes |
| `/dashboard` | UnifiedDashboard | Yes |
| `/dashboard/books’,’/movies’,’/games’,’/albums’,’/videos` | Redirect → `/dashboard?tab=*` | — |
| `/profile` | UserProfileView | Yes |
| `/books/:isbn` | BookDetailView | Yes |
| `/movies/:imdbId` | MovieDetailView | Yes |
| `/series/:imdbId` | SeriesDetailView | Yes |
| `/games/:gameId` | GameDetailView | Yes |
| `/albums/:albumId` | AlbumDetailView | Yes |
| `/videos/:youtubeId` | VideoDetailView | Yes |
| `/friends` | FriendsView | Yes |
| `/user/:username` | PublicProfileView | Yes |
| `/:pathMatch(.*)` | NotFoundView | No |

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
| `albums` | `store/albums.js` | albums[], allowedStatuses[], userTags[], searchResults[] | `fetchAlbums()`, `addAlbum()`, `deleteAlbum()`, `editUserAlbum()` |
| `videos` | `store/videos.js` | videos[], allowedStatuses[], userTags[], videoNotes{}, searchResults[] | `fetchVideos()`, `addVideo()`, `deleteVideo()`, `editUserVideo()`, `searchYouTubeVideos()` |
| `sessions` | `store/sessions.js` | activeSessions{}, sessionHistories{} | `createSession()`, `completeSession()`, `pauseSession()`, `resumeSession()` |
| `social` | `store/social.js` | friends[], pendingRequests[], feed[], privacySettings, searchResults[] | `fetchFriends()`, `sendFriendRequest()`, `acceptFriendRequest()`, `loadFeed()`, `updatePrivacySettings()` |
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
| `useBooks` | Book operations + confirmations | Wraps books store |
| `useMovies` | Movie operations | Wraps movies store |
| `useGames` | Game operations | Wraps games store |
| `useAlbums` | Album operations | Wraps albums store |
| `useSearch` | Generic search debounce/results | Standalone logic |
| `useWorkSearch` | OpenLibrary work/edition search | Orchestrates book search flow |
| `useTrending` | Trending items across all entity types | Fetches from API |
| `useReadingProgress` | Page progress tracking | Wraps sessions store |
| `useReadingSessions` | Session CRUD | Wraps sessions store |
| `useEditionNotes` | Book edition notes | CRUD operations |
| `useGameNotes` | Game notes | CRUD operations |
| `useMovieNotes` | Movie notes | CRUD operations |
| `useAlbumNotes` | Album notes | CRUD operations |
| `useVideoNotes` | Video notes | CRUD operations |
| `useListeningStats` | Album listening statistics | Wraps StatsService |
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
| `useFeed` | Activity feed with infinite scroll | Wraps social store, uses IntersectionObserver |
| `useFriends` | Friend operations | Wraps social store |
| `useUserSearch` | Search other users | Wraps social store |
| `usePrivacySettings` | Feed privacy preferences | Wraps social store |

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

## Social & Feed Feature

### Stores & Composables

- **`store/social.js`** — Manages friends, pending requests, activity feed, privacy settings, user search
- **`useFeed`** — Activity feed with **IntersectionObserver-based infinite scroll** (`sentinel` ref + observer pattern)
- **`useFriends`** — Friend CRUD operations
- **`useUserSearch`** — Search users by name/username
- **`usePrivacySettings`** — Load/save privacy toggles

### Views

- `/friends` → `FriendsView.vue` — Friend list + pending requests
- `/user/:username` → `PublicProfileView.vue` — Public reading profile

### API Actions (backend routes)

| Action | Auth | CSRF |
|---|---|---|
| `send_friend_request` | Yes | Yes |
| `accept_friend_request` | Yes | Yes |
| `reject_friend_request` | Yes | Yes |
| `remove_friend` | Yes | Yes |
| `update_privacy_settings` | Yes | Yes |
| `get_friends` | Yes | No |
| `get_friend_requests` | Yes | No |
| `search_users` | Yes | No |
| `get_public_profile` | Yes | No |
| `get_feed` | Yes | No |

---

## Social & Feed Feature

### Stores & Composables

- **`store/social.js`** — Manages friends, pending requests, activity feed, privacy settings, user search
- **`useFeed`** — Activity feed with **IntersectionObserver-based infinite scroll** (`sentinel` ref + observer pattern)
- **`useFriends`** — Friend CRUD operations
- **`useUserSearch`** — Search users by name/username
- **`usePrivacySettings`** — Load/save privacy toggles

### Views

- `/friends` → `FriendsView.vue` — Friend list + pending requests
- `/user/:username` → `PublicProfileView.vue` — Public reading profile

### API Actions (backend routes)

| Action | Auth | CSRF |
|---|---|---|
| `send_friend_request` | Yes | Yes |
| `accept_friend_request` | Yes | Yes |
| `reject_friend_request` | Yes | Yes |
| `remove_friend` | Yes | Yes |
| `update_privacy_settings` | Yes | Yes |
| `get_friends` | Yes | No |
| `get_friend_requests` | Yes | No |
| `search_users` | Yes | No |
| `get_public_profile` | Yes | No |
| `get_feed` | Yes | No |

---

## Component Patterns

### Entity Components (Books/Movies/Games)

Each entity type follows the same component structure:

- **`{Entity}Search.vue`** — Search and discover items from external APIs
- **`Library{Entity}Item.vue`** — Card in user's library view
- **`{Entity}CarouselItem.vue`** — Card in trending/carousel displays
- **`{Entity}ListItem.vue`** — Row in list/table views
- **`{Entity}Notes.vue`** — Notes management (if applicable)

### Dashboard Components

- **`UnifiedDashboard.vue`** — Tab container (books/movies/games/albums/videos tabs via query param)
- **`{Entity}Dashboard.vue`** — Tab wrapper per entity
- **`{Entity}DashboardContent.vue`** — Actual dashboard content with stats and charts. Supported entities: `books`, `movies`, `games`, `albums`, `videos`
- **`DashboardChartsGrid.vue`** — Reusable charts layout
- **`DashboardStatsGrid.vue`** — Reusable stats cards

**`extractMockStats(rawStats, itemType)` in `composables/useDashboardCharts.js`**:
Normalizes raw API stats into display-ready objects. Supported `itemType` values: `'books'`, `'games'`, `'albums'`, `'videos'`, and default (`'movies'`/series). When adding a new entity, add its case here alongside the null-fallback and the populated-stats branches.

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

## Styles Architecture

Frontend SCSS architecture, tokens, theming, and family-coherence master mixins are documented in a dedicated skill:

→ **[`.github/skills/styles.md`](styles.md)** — tokens, layer order, master mixins (`list-item`, `library-item`, `search-page`, `notes-panel`, `detail-view-page`), entity color identity, refactor workflow, common drift patterns.

**Quick reminders** when writing `<style>` blocks:
- Always `<style scoped lang="scss">`, only `@use` / `@forward` (no `@import`)
- No hex/px hardcoded — use `spacing()`, `radius()`, `shadow()`, etc.
- Shadow tokens are `sm/light/medium/heavy/xl` (**NOT** `md`/`lg`)
- PrimeVue overlay overrides go in `_primevue-overrides.scss` without `:deep()`
- Dark mode uses `.app-dark` class — never `prefers-color-scheme`

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
| `StatsService` | `services/StatsService.js` | Fetches stats per entity via `_apiCall()`: `getBookStats()`, `getMovieStats()`, `getGameStats()`, `getAlbumStats()`, `getVideoStats()`. Also provides `transformStatusDataForChart()`, `transformRatingDataForChart()`, `transformGenreDataForChart()`, `transformMonthlyDataForChart()` helpers used by `*DashboardContent` components |

## Debugging

- **Browser DevTools → Network**: Check API calls, verify action names and payloads
- **Vue DevTools**: Inspect Pinia store state, component tree
- **Console logging**: Use `Logger` from `utils/logger.js` (supports `debug`, `info`, `warn`, `error`)
- **Dual-format check**: If data doesn't show, check both `camelCase` and `snake_case` field names in API response
- **Hot reload**: Frontend runs with HMR in dev mode via Docker volume mounts

## Visual Verification (headless screenshots)

**Why this exists**: the Vitest suite runs on jsdom, which **does not evaluate CSS** (`css: false` in
`vitest.config.js`). A whole class of bugs is invisible to it — the one that bit us: Vue's `scoped`
CSS reaches a child component's **root** and its **slot content**, but not the markup the child
renders itself, so a generic view can render completely unstyled while every test stays green.
Screenshots catch that; tests never will.

**Requirements** (already on the dev machine): `firefox` and `geckodriver` (both from snap), plus the
app running (`docker compose up -d`). No npm package is needed — geckodriver speaks the W3C WebDriver
protocol over HTTP, so plain `curl` drives it.

### 1. Start the driver and open a session

```bash
geckodriver --port 4444 &

SID=$(curl -s -X POST http://127.0.0.1:4444/session \
  -H 'Content-Type: application/json' \
  -d '{"capabilities":{"alwaysMatch":{"browserName":"firefox",
       "moz:firefoxOptions":{"args":["-headless","-width","1400","-height","1100"]}}}}' \
  | python3 -c "import sys,json;print(json.load(sys.stdin)['value']['sessionId'])")
B=http://127.0.0.1:4444/session/$SID
```

### 2. Authenticate

Login is Google OAuth, and **Google refuses to sign in from an automated browser** ("this browser may
not be secure"), so the session has to be borrowed. Two ways, both taken from a browser you are
already logged into:

- **JWT (preferred)** — run `copy(localStorage.getItem('jwt_token'))` in the DevTools console on
  `localhost:8080`. Works since `check_auth` accepts Bearer tokens (fixed 2026-08-19; before that the
  app wiped the token on boot).
- **Session cookie** — `LIBRARY_SESSION` is `httpOnly`, so it is not readable from the console: copy
  it from DevTools → Storage → Cookies → **`http://127.0.0.1:8888`** (the backend origin, *not*
  `localhost:8080`), then `POST $B/cookie` with `{"cookie":{"name":"LIBRARY_SESSION","value":"…",
  "domain":"127.0.0.1","path":"/"}}` while on that origin.

```bash
# You must be on the origin before writing its localStorage.
curl -s -X POST $B/url -H 'Content-Type: application/json' -d '{"url":"http://localhost:8080/"}'
curl -s -X POST $B/execute/sync -H 'Content-Type: application/json' \
  -d '{"script":"localStorage.setItem(\"jwt_token\", arguments[0]); return true","args":["<TOKEN>"]}'
curl -s -X POST $B/refresh -H 'Content-Type: application/json' -d '{}'
```

### 3. Navigate and capture

```bash
shot () {  # shot <url> <file.png> [seconds]
  curl -s -X POST $B/url -H 'Content-Type: application/json' -d "{\"url\":\"$1\"}" > /dev/null
  sleep "${3:-8}"   # external enrichment (OMDb, IGDB, Spotify, Google Books) is not instant
  curl -s $B/screenshot | python3 -c \
    "import sys,json,base64;open('$2','wb').write(base64.b64decode(json.load(sys.stdin)['value']))"
}

shot http://localhost:8080/videos/A9mvuAwl5eo video.png
shot http://localhost:8080/albums/2bhYZTFBsnB3IXItHQBmUV album.png
```

Useful extras:

- **Tall pages**: `POST $B/window/rect` with `{"width":1300,"height":2600}` — the screenshot is
  viewport-sized, so a taller window captures more without stitching.
- **Jump to a section**: `POST $B/execute/sync` with
  `document.querySelector('.library-form-section').scrollIntoView({block:'start'})`.
- **Promises**: use `/execute/async` (the script gets a callback as its last argument);
  `/execute/sync` does not await, and top-level `await` there returns HTTP 500.
- **Check computed styles** rather than eyeballing: `getComputedStyle(el).width`, or read
  `el.attributes` to confirm a `data-v-*` scope id is present — that is how the scoped-CSS bug above
  was diagnosed.

### 4. Close

```bash
curl -s -X DELETE $B
```

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

## Mobile (Capacitor)

The frontend also runs as a native Android app via Capacitor. See **[`.github/skills/mobile.md`](mobile.md)** for full mobile documentation.

**Quick reference**:
- Build: `npm run build:mobile` (loads `.env.mobile`, sets `publicPath: './'`, hash router)
- Platform detection: `Capacitor.isNativePlatform()` — use this to bifurcate web vs. native behavior
- Auth on mobile: JWT Bearer token (not session cookies)
- Navigation on mobile: `MobileNavBar.vue` (bottom nav) replaces `Sidebar.vue`
- `VUE_APP_MODE=mobile` controls build config (router, publicPath) — not runtime behavior

**New actions checklist for mobile**: Any new write action using `CSRFMiddleware` in `config/routes.php` MUST be added to `protectedActions` in `store/auth.js` (web CSRF) — mobile automatically bypasses CSRF via JWT.
