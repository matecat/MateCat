import {saveBlob} from '../../utils/saveBlob'

/**
 * Reads the filename from a Content-Disposition header, preferring the RFC 5987
 * `filename*` form over the plain `filename` one.
 *
 * @param {string|null} header
 * @returns {string|undefined}
 */
export const getFilenameFromContentDisposition = (header) => {
  if (!header) return undefined

  const encoded = /filename\*\s*=\s*(?:[\w-]+'[\w-]*')?([^;]+)/i.exec(header)
  if (encoded) {
    try {
      return decodeURIComponent(encoded[1].trim().replace(/^"|"$/g, ''))
    } catch {
      // Malformed percent-encoding: use the plain filename
    }
  }

  const plain = /filename\s*=\s*(?:"([^"]*)"|([^;]+))/i.exec(header)
  if (!plain) return undefined
  return (plain[1] ?? plain[2]).trim()
}

/**
 * Reads the response body chunk by chunk so the caller can show how much has
 * arrived. `total` is 0 when the size is unknown: no Content-Length, or a body
 * that grew past it because it was compressed in transit.
 */
const readBody = async (response, onProgress) => {
  if (!onProgress || !response.body) return response.blob()

  const contentLength = Number(response.headers.get('Content-Length')) || 0
  const reader = response.body.getReader()
  const chunks = []
  let loaded = 0

  onProgress({loaded, total: contentLength})
  for (;;) {
    const {done, value} = await reader.read()
    if (done) break
    chunks.push(value)
    loaded += value.length
    onProgress({loaded, total: loaded > contentLength ? 0 : contentLength})
  }

  return new Blob(chunks, {
    type: response.headers.get('Content-Type') ?? '',
  })
}

/**
 * Downloads a file the server sends as an attachment and saves it to disk
 *
 * @param {string} url
 * @param {function({loaded: number, total: number})} [onProgress] called as the file arrives
 * @param {AbortSignal} [signal] aborts the download
 * @returns {Promise<void>}
 */
export const downloadFromUrl = async ({url, onProgress, signal}) => {
  const response = await fetch(url, {credentials: 'include', signal})
  if (!response.ok) return Promise.reject(response)
  const filename = getFilenameFromContentDisposition(
    response.headers.get('Content-Disposition'),
  )
  const blob = await readBody(response, onProgress)
  saveBlob(blob, filename)
}
