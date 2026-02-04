/**
 * ISO 639-1 (2-letter) and ISO 639-2/T (3-letter) language codes
 * with native names and Spanish translations for display
 */
export const LANGUAGE_CODES = {
  // Major languages
  'en': { native: 'English', es: 'Inglés', code3: 'eng' },
  'eng': { native: 'English', es: 'Inglés', code2: 'en' },
  
  'es': { native: 'Español', es: 'Español', code3: 'spa' },
  'spa': { native: 'Español', es: 'Español', code2: 'es' },
  
  'fr': { native: 'Français', es: 'Francés', code3: 'fre' },
  'fre': { native: 'Français', es: 'Francés', code2: 'fr' },
  
  'de': { native: 'Deutsch', es: 'Alemán', code3: 'ger' },
  'ger': { native: 'Deutsch', es: 'Alemán', code2: 'de' },
  
  'it': { native: 'Italiano', es: 'Italiano', code3: 'ita' },
  'ita': { native: 'Italiano', es: 'Italiano', code2: 'it' },
  
  'pt': { native: 'Português', es: 'Portugués', code3: 'por' },
  'por': { native: 'Português', es: 'Portugués', code2: 'pt' },
  
  'ru': { native: 'Русский', es: 'Ruso', code3: 'rus' },
  'rus': { native: 'Русский', es: 'Ruso', code2: 'ru' },
  
  'ja': { native: '日本語', es: 'Japonés', code3: 'jpn' },
  'jpn': { native: '日本語', es: 'Japonés', code2: 'ja' },
  
  'zh': { native: '中文', es: 'Chino', code3: 'chi' },
  'chi': { native: '中文', es: 'Chino', code2: 'zh' },
  
  'ar': { native: 'العربية', es: 'Árabe', code3: 'ara' },
  'ara': { native: 'العربية', es: 'Árabe', code2: 'ar' },
  
  'hi': { native: 'हिन्दी', es: 'Hindi', code3: 'hin' },
  'hin': { native: 'हिन्दी', es: 'Hindi', code2: 'hi' },
  
  'ko': { native: '한국어', es: 'Coreano', code3: 'kor' },
  'kor': { native: '한국어', es: 'Coreano', code2: 'ko' },
  
  'nl': { native: 'Nederlands', es: 'Neerlandés', code3: 'dut' },
  'dut': { native: 'Nederlands', es: 'Neerlandés', code2: 'nl' },
  
  'pl': { native: 'Polski', es: 'Polaco', code3: 'pol' },
  'pol': { native: 'Polski', es: 'Polaco', code2: 'pl' },
  
  'tr': { native: 'Türkçe', es: 'Turco', code3: 'tur' },
  'tur': { native: 'Türkçe', es: 'Turco', code2: 'tr' },
  
  'sv': { native: 'Svenska', es: 'Sueco', code3: 'swe' },
  'swe': { native: 'Svenska', es: 'Sueco', code2: 'sv' },
  
  'no': { native: 'Norsk', es: 'Noruego', code3: 'nor' },
  'nor': { native: 'Norsk', es: 'Noruego', code2: 'no' },
  
  'da': { native: 'Dansk', es: 'Danés', code3: 'dan' },
  'dan': { native: 'Dansk', es: 'Danés', code2: 'da' },
  
  'fi': { native: 'Suomi', es: 'Finlandés', code3: 'fin' },
  'fin': { native: 'Suomi', es: 'Finlandés', code2: 'fi' },
  
  'el': { native: 'Ελληνικά', es: 'Griego', code3: 'gre' },
  'gre': { native: 'Ελληνικά', es: 'Griego', code2: 'el' },
  
  'cs': { native: 'Čeština', es: 'Checo', code3: 'cze' },
  'cze': { native: 'Čeština', es: 'Checo', code2: 'cs' },
  
  'ro': { native: 'Română', es: 'Rumano', code3: 'rum' },
  'rum': { native: 'Română', es: 'Rumano', code2: 'ro' },
  
  'hu': { native: 'Magyar', es: 'Húngaro', code3: 'hun' },
  'hun': { native: 'Magyar', es: 'Húngaro', code2: 'hu' },
  
  'th': { native: 'ไทย', es: 'Tailandés', code3: 'tha' },
  'tha': { native: 'ไทย', es: 'Tailandés', code2: 'th' },
  
  'vi': { native: 'Tiếng Việt', es: 'Vietnamita', code3: 'vie' },
  'vie': { native: 'Tiếng Việt', es: 'Vietnamita', code2: 'vi' },
  
  'id': { native: 'Bahasa Indonesia', es: 'Indonesio', code3: 'ind' },
  'ind': { native: 'Bahasa Indonesia', es: 'Indonesio', code2: 'id' },
  
  'uk': { native: 'Українська', es: 'Ucraniano', code3: 'ukr' },
  'ukr': { native: 'Українська', es: 'Ucraniano', code2: 'uk' },
  
  'ca': { native: 'Català', es: 'Catalán', code3: 'cat' },
  'cat': { native: 'Català', es: 'Catalán', code2: 'ca' },
  
  'he': { native: 'עברית', es: 'Hebreo', code3: 'heb' },
  'heb': { native: 'עברית', es: 'Hebreo', code2: 'he' },
  
  'fa': { native: 'فارسی', es: 'Persa', code3: 'per' },
  'per': { native: 'فارسی', es: 'Persa', code2: 'fa' }
};

/**
 * Get language display name
 * @param {string|object} code - ISO 639-1 or 639-2 code, or object with 'key' property, or OpenLibrary path
 * @param {string} displayLang - Language for display ('native', 'es')
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
  
  return langData[displayLang] || langData.native;
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
