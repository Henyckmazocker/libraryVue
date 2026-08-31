import { t } from '@/config/i18n'

/**
 * Códigos ISO 639-1 (dos letras) y ISO 639-2/T (tres) con su nombre **nativo**.
 *
 * El nombre nativo es un DATO: «Français» se escribe igual en español y en
 * inglés. Lo que sí cambia es cómo la interfaz llama a ese idioma —«Francés» /
 * «French»—, y eso vive en el bloque `language:` del catálogo, no aquí. Hasta el
 * 2026-08-31 este fichero tenía un campo `es:` con esa traducción: era un
 * catálogo paralelo, y una segunda fuente de la misma verdad.
 */
/**
 * Del código que llega al del catálogo: `spa` y `es` son el mismo idioma y
 * comparten rótulo, así que comparten clave.
 */
const CLAVE_DE_CATALOGO = {
  'ar': 'ar',
  'ara': 'ar',
  'ca': 'ca',
  'cat': 'ca',
  'chi': 'zh',
  'cs': 'cs',
  'cze': 'cs',
  'da': 'da',
  'dan': 'da',
  'de': 'de',
  'dut': 'nl',
  'el': 'el',
  'en': 'en',
  'eng': 'en',
  'es': 'es',
  'fa': 'fa',
  'fi': 'fi',
  'fin': 'fi',
  'fr': 'fr',
  'fre': 'fr',
  'ger': 'de',
  'gre': 'el',
  'he': 'he',
  'heb': 'he',
  'hi': 'hi',
  'hin': 'hi',
  'hu': 'hu',
  'hun': 'hu',
  'id': 'id',
  'ind': 'id',
  'it': 'it',
  'ita': 'it',
  'ja': 'ja',
  'jpn': 'ja',
  'ko': 'ko',
  'kor': 'ko',
  'nl': 'nl',
  'no': 'no',
  'nor': 'no',
  'per': 'fa',
  'pl': 'pl',
  'pol': 'pl',
  'por': 'pt',
  'pt': 'pt',
  'ro': 'ro',
  'ru': 'ru',
  'rum': 'ro',
  'rus': 'ru',
  'spa': 'es',
  'sv': 'sv',
  'swe': 'sv',
  'th': 'th',
  'tha': 'th',
  'tr': 'tr',
  'tur': 'tr',
  'uk': 'uk',
  'ukr': 'uk',
  'vi': 'vi',
  'vie': 'vi',
  'zh': 'zh'
}

export const LANGUAGE_CODES = {
  // Major languages
  'en': { native: 'English', code3: 'eng' },
  'eng': { native: 'English', code2: 'en' },
  
  'es': { native: 'Español', code3: 'spa' },
  'spa': { native: 'Español', code2: 'es' },
  
  'fr': { native: 'Français', code3: 'fre' },
  'fre': { native: 'Français', code2: 'fr' },
  
  'de': { native: 'Deutsch', code3: 'ger' },
  'ger': { native: 'Deutsch', code2: 'de' },
  
  'it': { native: 'Italiano', code3: 'ita' },
  'ita': { native: 'Italiano', code2: 'it' },
  
  'pt': { native: 'Português', code3: 'por' },
  'por': { native: 'Português', code2: 'pt' },
  
  'ru': { native: 'Русский', code3: 'rus' },
  'rus': { native: 'Русский', code2: 'ru' },
  
  'ja': { native: '日本語', code3: 'jpn' },
  'jpn': { native: '日本語', code2: 'ja' },
  
  'zh': { native: '中文', code3: 'chi' },
  'chi': { native: '中文', code2: 'zh' },
  
  'ar': { native: 'العربية', code3: 'ara' },
  'ara': { native: 'العربية', code2: 'ar' },
  
  'hi': { native: 'हिन्दी', code3: 'hin' },
  'hin': { native: 'हिन्दी', code2: 'hi' },
  
  'ko': { native: '한국어', code3: 'kor' },
  'kor': { native: '한국어', code2: 'ko' },
  
  'nl': { native: 'Nederlands', code3: 'dut' },
  'dut': { native: 'Nederlands', code2: 'nl' },
  
  'pl': { native: 'Polski', code3: 'pol' },
  'pol': { native: 'Polski', code2: 'pl' },
  
  'tr': { native: 'Türkçe', code3: 'tur' },
  'tur': { native: 'Türkçe', code2: 'tr' },
  
  'sv': { native: 'Svenska', code3: 'swe' },
  'swe': { native: 'Svenska', code2: 'sv' },
  
  'no': { native: 'Norsk', code3: 'nor' },
  'nor': { native: 'Norsk', code2: 'no' },
  
  'da': { native: 'Dansk', code3: 'dan' },
  'dan': { native: 'Dansk', code2: 'da' },
  
  'fi': { native: 'Suomi', code3: 'fin' },
  'fin': { native: 'Suomi', code2: 'fi' },
  
  'el': { native: 'Ελληνικά', code3: 'gre' },
  'gre': { native: 'Ελληνικά', code2: 'el' },
  
  'cs': { native: 'Čeština', code3: 'cze' },
  'cze': { native: 'Čeština', code2: 'cs' },
  
  'ro': { native: 'Română', code3: 'rum' },
  'rum': { native: 'Română', code2: 'ro' },
  
  'hu': { native: 'Magyar', code3: 'hun' },
  'hun': { native: 'Magyar', code2: 'hu' },
  
  'th': { native: 'ไทย', code3: 'tha' },
  'tha': { native: 'ไทย', code2: 'th' },
  
  'vi': { native: 'Tiếng Việt', code3: 'vie' },
  'vie': { native: 'Tiếng Việt', code2: 'vi' },
  
  'id': { native: 'Bahasa Indonesia', code3: 'ind' },
  'ind': { native: 'Bahasa Indonesia', code2: 'id' },
  
  'uk': { native: 'Українська', code3: 'ukr' },
  'ukr': { native: 'Українська', code2: 'uk' },
  
  'ca': { native: 'Català', code3: 'cat' },
  'cat': { native: 'Català', code2: 'ca' },
  
  'he': { native: 'עברית', code3: 'heb' },
  'heb': { native: 'עברית', code2: 'he' },
  
  'fa': { native: 'فارسی', code3: 'per' },
  'per': { native: 'فارسی', code2: 'fa' }
};

/**
 * Get language display name
 * @param {string|object} code - ISO 639-1 or 639-2 code, or object with 'key' property, or OpenLibrary path
 * @param {string} displayLang - `'native'` para el nombre del propio idioma, o
 *   `'ui'` para cómo lo llama la interfaz. Antes el segundo valor era un código
 *   de idioma (`'es'`); con catálogo eso ya no es un parámetro, es el activo.
 * @returns {string} Display name or uppercase code if not found
 */
export function getLanguageName(code, displayLang = 'native') {
  if (!code) return '';
  
  // Si es un objeto, extraer el código
  if (typeof code === 'object') {
    code = code.key || code.code || '';
  }
  
  if (!code) return '';
  
  // Si es una ruta de OpenLibrary (/languages/eng), extraer el código
  if (typeof code === 'string' && code.includes('/')) {
    const parts = code.split('/');
    code = parts[parts.length - 1]; // Obtener la última parte
  }
  
  const normalizedCode = String(code).toLowerCase();
  const langData = LANGUAGE_CODES[normalizedCode];
  
  if (!langData) {
    return normalizedCode.toUpperCase();
  }

  if (displayLang === 'ui') {
    // Cae al nombre nativo si el catálogo no conoce ese idioma, igual que
    // `statusLabel` cae al slug: son 60 códigos y el catálogo cubre 30.
    const clave = `language.${CLAVE_DE_CATALOGO[normalizedCode] ?? normalizedCode}`
    const traducido = t(clave)
    return traducido === clave ? langData.native : traducido
  }

  return langData.native;
}

/**
 * Get all unique language codes from array (handles both 2-letter and 3-letter codes)
 * @param {Array} languages - Array of language codes or objects
 * @returns {Array} Normalized unique codes
 */
export function normalizeLanguageCodes(languages) {
  if (!Array.isArray(languages)) return [];
  
  const codes = languages.map(lang => {
    if (typeof lang === 'string') return lang.toLowerCase();
    if (lang?.key) return lang.key.toLowerCase();
    return null;
  }).filter(Boolean);
  
  return [...new Set(codes)];
}
