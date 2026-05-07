import type { CapacitorConfig } from '@capacitor/cli';

// CAP_ENV=production  → build de producción (androidScheme https, url del backend real)
// CAP_ENV sin definir → desarrollo local con emulador (androidScheme http, 10.0.2.2)
const isProd = process.env.CAP_ENV === 'production';

const config: CapacitorConfig = {
  appId: 'com.libraryapp.library',
  appName: 'LibraryVue',
  webDir: 'dist',
  server: {
    url: isProd ? 'https://library.dcahomelab.com' : undefined,
    androidScheme: isProd ? 'https' : 'http'
  }
};

export default config;
