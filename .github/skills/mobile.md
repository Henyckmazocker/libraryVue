# Skill: Mobile — Capacitor para Android/iOS

## Scope

This skill covers all mobile development: Capacitor integration, Android/iOS native builds, JWT auth for mobile, Google Sign-In native, environment configuration, and the web vs. native bifurcation pattern.

---

## Tech Stack

| Tool | Version | Purpose |
|---|---|---|
| `@capacitor/core` | 8.3.1 | Core Capacitor bridge |
| `@capacitor/cli` | 8.3.1 | Build and sync CLI |
| `@capacitor/android` | 8.3.1 | Android project wrapper |
| `@codetrix-studio/capacitor-google-auth` | 3.4.0-rc.4 | Native Google Sign-In |
| `firebase/php-jwt` | — | JWT generation/validation (backend) |

**Build tool**: Vue CLI (`vue-cli-service build --mode mobile`)  
**Emulator**: Pixel 7 API 34 (Android 14), `emulator-5554`  
**Android SDK**: `~/Android/Sdk`  
**Java**: OpenJDK 17 at `/usr/lib/jvm/java-17-openjdk-amd64`

---

## Key Files

| File | Purpose |
|---|---|
| `frontend/capacitor.config.ts` | Capacitor project config (env-aware) |
| `frontend/.env.mobile` | Mobile-specific env vars (gitignored) |
| `frontend/android/` | Generated Android project |
| `frontend/android/app/src/main/AndroidManifest.xml` | `usesCleartextTraffic`, `networkSecurityConfig` |
| `frontend/android/app/src/main/res/xml/network_security_config.xml` | Allow HTTP to `10.0.2.2` |
| `frontend/android/app/src/main/res/values/secrets.xml` | Google OAuth `server_client_id` (gitignored) |
| `frontend/src/composables/useGoogleAuth.js` | Web vs. native Sign-In bifurcation |
| `frontend/src/components/common/MobileNavBar.vue` | Bottom navigation bar |
| `backend/src/Infrastructure/Auth/JWTService.php` | JWT generation and validation |
| `backend/src/Infrastructure/Middleware/AuthenticationMiddleware.php` | Accepts session OR Bearer JWT |
| `backend/src/Infrastructure/Middleware/CSRFMiddleware.php` | Skips CSRF when auth_method=jwt |
| `backend/public/.htaccess` | CORS for `capacitor://localhost`, Authorization header passthrough |

---

## Build Workflow

### Development (emulator)

```bash
# Always load nvm first
export NVM_DIR="$HOME/.nvm" && . "$NVM_DIR/nvm.sh"

cd frontend
npm run build:mobile        # vue-cli-service build --mode mobile → loads .env.mobile
npx cap sync android        # Copy dist/ to android/app/src/main/assets/public/
npx cap open android        # Open Android Studio → Run ▶ on emulator
```

### Production APK

```bash
npm run build:mobile:prod                    # Build with .env.production
CAP_ENV=production npx cap sync android      # Sync with https:// scheme
# Android Studio: Build → Generate Signed APK/AAB
```

### Verify build was picked up

```bash
docker compose logs frontend --tail 10 | grep "Build finished"
```

---

## `capacitor.config.ts` Pattern

Environment-aware configuration — `CAP_ENV=production` switches to production settings:

```typescript
import { CapacitorConfig } from '@capacitor/cli';

const isProd = process.env.CAP_ENV === 'production';

const config: CapacitorConfig = {
  appId: 'com.libraryapp.library',
  appName: 'Library',
  webDir: 'dist',
  server: {
    url: isProd ? 'https://library.dcahomelab.com' : undefined,
    androidScheme: isProd ? 'https' : 'http'
  }
};
```

**CRITICAL**: `androidScheme: 'http'` is mandatory in dev. Using `'https'` causes silent mixed-content blocking when the backend is HTTP.

---

## Environment Variables (`.env.mobile`)

All variables the mobile build needs that differ from the web build:

```dotenv
VUE_APP_API_URL=http://10.0.2.2:8888/index.php   # 10.0.2.2 = host from emulator
VUE_APP_MODE=mobile
VUE_APP_GOOGLE_CLIENT_ID=<oauth_client_id>
VUE_APP_OMDB_API_KEY=<omdb_key>                   # Movies call OMDB directly from frontend
```

**APIs called directly from frontend** (need key in `.env.mobile`):
- `VUE_APP_OMDB_API_KEY` → `MovieSearch.vue`, `SeriesSeasonTracker.vue`, `FileProcessorService.js`
- `VUE_APP_GOOGLE_CLIENT_ID` → `useGoogleAuth.js`

**APIs proxied through backend** (no key needed in `.env.mobile`):
- IGDB (games), GoogleBooks, OpenLibrary, Spotify, Last.fm — all server-side

---

## Platform Detection Pattern

Use `Capacitor.isNativePlatform()` to bifurcate web vs. native behavior:

```javascript
import { Capacitor } from '@capacitor/core';

const isNative = computed(() => Capacitor.isNativePlatform());

// In template
<button v-if="isNative" @click="nativeSignIn">Sign in with Google</button>
<div v-else id="g_id_signin"></div>
```

Never use user-agent sniffing. Never use `VUE_APP_MODE === 'mobile'` for platform detection — it can't tell dev build from prod build at runtime.

---

## Google Sign-In Native Flow

Plugin: `@codetrix-studio/capacitor-google-auth`

### Frontend (`useGoogleAuth.js`)

```javascript
import { GoogleAuth } from '@codetrix-studio/capacitor-google-auth';

const nativeSignIn = async () => {
  await GoogleAuth.initialize({
    clientId: process.env.VUE_APP_GOOGLE_CLIENT_ID,
    scopes: ['profile', 'email'],
    grantOfflineAccess: true
  });
  const googleUser = await GoogleAuth.signIn();
  // googleUser.authentication.idToken is a JWT signed by Google
  await authStore.login(googleUser.authentication.idToken);
};
```

### Android configuration

`android/app/src/main/res/values/secrets.xml` (must be gitignored):
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <string name="server_client_id">YOUR_GOOGLE_OAUTH_CLIENT_ID</string>
</resources>
```

**DO NOT** put the client ID in `strings.xml` — that file is committed. Use `secrets.xml` added to `.gitignore`.

### Backend (no changes needed)

The backend validates the `idToken` from Google the same way as in web. No code changes required for the Google auth flow itself.

---

## JWT Authentication Architecture

Mobile auth uses JWT Bearer tokens because the WebView cannot send cross-origin cookies.

### Flow

1. User signs in → backend `login()` returns `{ jwt_token: "..." }`
2. Frontend stores JWT in `localStorage`
3. Every API call sends `Authorization: Bearer <token>` header
4. `AuthenticationMiddleware.php` validates JWT and populates `$_SESSION['user_data']`
5. `CSRFMiddleware.php` skips when `auth_method === 'jwt'`

### Frontend (`store/auth.js`)

```javascript
// After login:
if (response.jwt_token) {
  this.jwtToken = response.jwt_token;
  localStorage.setItem('jwt_token', response.jwt_token);
}

// In authenticatedApiCall():
const headers = {};
if (this.jwtToken) {
  headers['Authorization'] = `Bearer ${this.jwtToken}`;
}
```

### Backend — reading Authorization header

Apache strips `Authorization` headers by default. The `.htaccess` fix:
```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

`AuthenticationMiddleware.php` reads from multiple fallbacks:
```php
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? (getallheaders()['Authorization'] ?? null);
```

---

## CORS for Capacitor

Capacitor on Android uses `capacitor://localhost` as the Origin (with `androidScheme: 'https'`) or `http://localhost` (with `androidScheme: 'http'`).

Add to `backend/public/.htaccess`:
```apache
SetEnvIf Origin "^capacitor://localhost$" AccessControlAllowOrigin=$0
SetEnvIf Origin "^http://localhost$" AccessControlAllowOrigin=$0
```

---

## Network Security (Android)

`android/app/src/main/AndroidManifest.xml` — allow cleartext to emulator host only:
```xml
<application
    android:usesCleartextTraffic="true"
    android:networkSecurityConfig="@xml/network_security_config">
```

`android/app/src/main/res/xml/network_security_config.xml`:
```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">10.0.2.2</domain>
    </domain-config>
</network-security-config>
```

**Note**: `10.0.2.2` is the special Android emulator IP that routes to the host machine's `localhost`. Do NOT use `localhost` inside the emulator — it refers to the emulator itself.

---

## Adding New Capacitor Plugins

1. Install: `npm install @capacitor/plugin-name`
2. Sync: `npx cap sync android`
3. If plugin needs Android native config: add to `AndroidManifest.xml` or `build.gradle`
4. For plugins that call native APIs: use `Capacitor.isNativePlatform()` guard

---

## Adding a New Protected Backend Action for Mobile

When adding a new write action to the backend:

1. Add it to `config/routes.php` with `CSRFMiddleware`
2. **Add it to `protectedActions` in `frontend/src/store/auth.js`** — missing this causes `400 Invalid CSRF token` on web even though mobile uses JWT bypass

```javascript
// store/auth.js — protectedActions list
const protectedActions = [
  'add_book', 'delete_book', /* ... */
  'add_new_action',   // ← ADD HERE
];
```

---

## Debugging Mobile Issues

### Check emulator logs in backend

```bash
docker compose logs backend --tail 50 2>/dev/null | grep "Android\|10.0.2.2\|capacitor"
```

### Common issues

| Symptom | Cause | Fix |
|---|---|---|
| `401 Unauthorized` on all calls | JWT not being sent | Check `localStorage.jwt_token` exists and `Authorization` header is set |
| `400 Invalid CSRF token` | New action not in `protectedActions` | Add to list in `store/auth.js` |
| Network error on all requests | `androidScheme: 'https'` with HTTP backend | Change to `androidScheme: 'http'` in `capacitor.config.ts` |
| API call fails with `apikey=undefined` | Missing `VUE_APP_*` in `.env.mobile` | Add the key, rebuild |
| Google Sign-In crashes | `secrets.xml` missing or wrong client ID | Check `android/app/src/main/res/values/secrets.xml` |
| Assets not loading | `publicPath: '/'` in mobile build | Ensure `vue.config.js` sets `'./'` when `VUE_APP_MODE=mobile` |
| Routes not found | `createWebHistory` in mobile build | Ensure `router/index.js` uses `createWebHashHistory` when `VUE_APP_MODE=mobile` |

### Check what URL a failing API call is hitting

Look at browser console or frontend logs in backend:
```bash
docker compose logs backend --tail 20 | grep "AxiosError\|ERR_BAD_REQUEST\|401\|403"
```
The `config.url` in the error stack trace shows the exact URL being called.

---

## `.gitignore` Rules for Mobile

These files must NOT be committed:
```
frontend/.env.mobile
frontend/android/app/src/main/res/values/secrets.xml
frontend/android/app/google-services.json   # if added for FCM
```

These SHOULD be committed (generated but stable):
```
frontend/android/
frontend/capacitor.config.ts
```

---

## SCSS / Responsive — Trabajo pendiente

The SCSS system already has breakpoints (`sm:480`, `md:768`, `lg:1024`). These are adjustments, not rewrites.

### Mobile-first adjustments needed

- **`Header.vue`**: simplify on small screens (hide text labels, icons only)
- **`*Search.vue`** (use `_search.scss`): verify results grid on narrow screens
- **`*DetailView.vue`** (use `_detail-view.scss`): hero header must work without hover
- **`EditItemModal.vue`**: verify it doesn't get cut off by the virtual keyboard

### Keyboard avoidance

When the virtual keyboard opens, some form fields get hidden behind it.

Fix: use `height: 100dvh` instead of `height: 100vh` on the root layout element (`100dvh` accounts for the dynamic viewport change when the keyboard appears).

---

## Roadmap (futuros plugins)

| Feature | Plugin | Integration point |
|---|---|---|
| Offline mode | `@capacitor/network` + `@capacitor/preferences` | Detect connectivity, cache library data locally |
| ISBN scanner | `@capacitor/camera` + `@zxing/library` | `BookSearch.vue` scan button |
| Push notifications | `@capacitor/push-notifications` | Reading reminders |
| iOS support | Xcode + CocoaPods | `ios/App/App/Info.plist`: add `GIDClientID` + reverse URL scheme |
