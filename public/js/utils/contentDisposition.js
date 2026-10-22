/**
 * Reads the file name out of a Content-Disposition header.
 *
 * The server sends the name in two forms: `filename*=UTF-8''…` (RFC 5987) holds the original, in any script;
 * `filename="…"` holds an ASCII copy. A header read through fetch is decoded as Latin-1, so only the encoded
 * form survives a non-ASCII name intact: it is preferred, and the quoted form is the fallback.
 *
 * @param {string|null} header
 * @param {string} fallback returned when the header carries no file name
 * @returns {string}
 */
export const getFilenameFromContentDisposition = (header, fallback = '') => {
  if (!header) return fallback

  const encoded = header.match(/filename\*\s*=\s*[^']*'[^']*'([^;]+)/i)
  if (encoded) {
    try {
      return decodeURIComponent(encoded[1].trim())
    } catch {
      // malformed percent-encoding: use the plain parameter
    }
  }

  const quoted = header.match(/filename\s*=\s*"([^"]*)"/i)
  if (quoted) return quoted[1]

  const bare = header.match(/filename\s*=\s*([^;]+)/i)
  if (bare) return bare[1].trim()

  return fallback
}
