import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.carbnb.app',
  appName: 'Carbnb',
  webDir: 'www',
  server: {
    // The app loads your LIVE site directly - no PHP files are bundled into the APK.
    url: 'https://carbnb.free.je',
    cleartextTrafficPermitted: true // fallback in case the site is ever served over plain HTTP
  }
};

export default config;
