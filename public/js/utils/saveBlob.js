// Long enough for the browser to start writing a large file before its URL goes away
const REVOKE_DELAY_MS = 60 * 1000

/**
 * Saves a blob to disk through a temporary `<a download>`. Unlike file-saver,
 * it never opens a window, so no tab flashes open when the file is ready.
 *
 * @param {Blob} blob
 * @param {string} [filename]
 */
export const saveBlob = (blob, filename) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename ?? ''
  link.style.display = 'none'
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), REVOKE_DELAY_MS)
}
