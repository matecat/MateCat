const CHANNEL_NAME = 'matecat-context-preview'

/**
 * Singleton service that manages a single BroadcastChannel for communication
 * between the CatTool page and the ContextPreview page.
 *
 * Can be used from any context — functional components, class components,
 * Flux actions, or plain utility code.
 *
 * Message protocol:
 * - CatTool -> ContextPreview: {type: 'segments', segments: [{
 *       sid, source, target,
 *       context_url,   // string|null — resolved URL for the context HTML document
 *       resname,       // string|null — node selector/path value from XLIFF resname
 *       restype,       // string|null — one of: x-path | x-tag-id | x-css_class |
 *                      //               x-client_nodepath | x-attribute_name_value
 *   }]}
 *     Sends the full segment mapping so ContextPreview can build the target panel.
 * - CatTool -> ContextPreview: {type: 'highlight', sid: number, source: string, target: string,
 *       context_url: string|null, resname: string|null, restype: string|null}
 *     Highlights a single segment on both source and target panels.
 * - CatTool -> ContextPreview: {type: 'updateTranslation', sid: number, target: string}
 *     Updates the translation for a single segment in the target panel.
 *     (Does not carry metadata — only updates text.)
 * - ContextPreview -> CatTool: {type: 'segmentClicked', sid: number}
 *     Reports which segment was clicked in either panel.
 * - ContextPreview -> CatTool: {type: 'requestSegments'}
 *     Requests the current segment list (used when ContextPreview loads after CatTool).
 * - ContextPreview -> CatTool: {type: 'loadMoreSegments', where: 'before'|'after'}
 *     Requests loading more segments in the given direction when untagged nodes are visible.
 *
 * Usage:
 *   import ContextPreviewChannel from '../../utils/contextPreviewChannel'
 *
 *   // Send from anywhere (class components, hooks, actions, etc.)
 *   ContextPreviewChannel.sendMessage({type: 'highlight', sid: 123, source: '...', target: '...'})
 *
 *   // Listen for incoming messages (cleaned up automatically on close)
 *   const off = ContextPreviewChannel.onMessage((msg) => { ... })
 *   off() // unsubscribe
 */
const ContextPreviewChannel = {
  /** @type {BroadcastChannel|null} */
  _channel: null,

  /** @type {Set<Function>} */
  _listeners: new Set(),

  /**
   * Opens the BroadcastChannel. Safe to call multiple times —
   * subsequent calls are no-ops if already open.
   */
  open() {
    if (this._channel) return

    this._channel = new BroadcastChannel(`${CHANNEL_NAME}-${config.password}`)

    this._channel.onmessage = (event) => {
      this._listeners.forEach((fn) => {
        try {
          fn(event.data)
        } catch (e) {
          console.error('[ContextPreviewChannel] Listener error:', e)
        }
      })
    }
  },

  /**
   * Closes the BroadcastChannel and removes all listeners.
   */
  close() {
    if (this._channel) {
      this._channel.close()
      this._channel = null
    }
    this._listeners.clear()
  },

  /**
   * Sends a message to the other side of the channel.
   * Opens the channel automatically if not already open.
   *
   * @param {Object} message
   */
  sendMessage(message) {
    if (!this._channel) this.open()
    this._channel.postMessage(message)
  },

  /**
   * Registers a listener for incoming messages.
   * Opens the channel automatically if not already open.
   *
   * @param {Function} callback - Called with the message data
   * @returns {Function} Unsubscribe function
   */
  onMessage(callback) {
    if (!this._channel) this.open()
    this._listeners.add(callback)
    return () => this._listeners.delete(callback)
  },
}

export default ContextPreviewChannel
