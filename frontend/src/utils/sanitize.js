import DOMPurify from 'dompurify';

/**
 * Saneado del HTML que se inyecta con `v-html`. Existe porque el catálogo
 * externo (Google Books, IGDB) y los títulos que escribe el usuario acaban
 * dentro de un `v-html`, y ahí un `<img onerror>` se ejecuta.
 *
 * `ALLOWED_ATTR: []` es deliberado: ninguna de estas descripciones necesita un
 * solo atributo, y sin atributos no hay `onerror`, ni `href="javascript:"`, ni
 * `style`.
 */

/** Lista blanca de lo que una descripción de catálogo puede traer. */
const RICH = {
  ALLOWED_TAGS: ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'span'],
  ALLOWED_ATTR: [],
};

/** Para el modal: solo saltos de línea y énfasis. */
const PLAIN = { ALLOWED_TAGS: ['br', 'b', 'strong', 'i', 'em'], ALLOWED_ATTR: [] };

export function sanitizeRich(html) {
  return DOMPurify.sanitize(html ?? '', RICH);
}

export function sanitizePlain(html) {
  return DOMPurify.sanitize(html ?? '', PLAIN);
}
