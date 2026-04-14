import {useState, useRef, useEffect} from 'react'
import ContextPreviewChannel from '../utils/contextPreviewChannel'
import {
  getSegmentNodeMap,
  getSidsFromElement,
  replaceTextContent,
  stripSegmentTags,
  updateNodeTranslation,
} from '../utils/contextPreviewUtils'

/**
 * Subscribes to ContextPreviewChannel and handles all incoming message types.
 *
 * @param {{
 *   onHighlight: (numericSid: number, contextUrl: string|null) => void,
 *   onTranslationUpdate: (numericSid: number, target: string, updatedSegments: Array) => void,
 *   highlightRef: React.RefObject,
 *   targetRef: React.RefObject,
 *   showNodeWarning: (el: HTMLElement) => void,
 *   clearNodeWarning: (el: HTMLElement) => void,
 * }} params
 * @returns {{segments: Array, setSegments: Function, currentContextUrl: string|null}}
 */
const useContextPreviewMessages = ({
  onHighlight,
  onTranslationUpdate,
  highlightRef,
  targetRef,
  showNodeWarning,
  clearNodeWarning,
}) => {
  const [segments, setSegments] = useState([])
  const [currentContextUrl, setCurrentContextUrl] = useState(null)
  const segmentsRef = useRef([])

  // Keep segmentsRef in sync for use inside channel callbacks
  useEffect(() => {
    segmentsRef.current = segments
  }, [segments])

  useEffect(() => {
    const handleMessage = (message) => {
      if (message.type === 'segments') {
        const incoming = message.segments ?? []
        setSegments((prev) => {
          const existingSids = new Set(prev.map((s) => s.sid))
          const newSegments = incoming.filter((s) => !existingSids.has(s.sid))
          return newSegments.length > 0 ? [...prev, ...newSegments] : prev
        })
      }

      if (message.type === 'highlight') {
        const cur = highlightRef.current
        const numericSid = Number(message.sid)
        if (cur?.mode === 'node' && cur.sids.includes(numericSid)) return
        if (message.context_url) {
          setCurrentContextUrl(message.context_url)
        }
        onHighlight(numericSid, message.context_url ?? null)
      }

      if (message.type === 'updateTranslation') {
        const {sid, target} = message
        const numericSid = Number(sid)
        setSegments((prev) => {
          const updated = prev.map((seg) =>
            Number(seg.sid) === numericSid ? {...seg, target} : seg,
          )
          if (targetRef.current) {
            const map = getSegmentNodeMap(targetRef.current)
            const nodeIndices = map?.sidToNodeIndices.get(numericSid) ?? []
            nodeIndices.forEach((nodeIndex) => {
              const el = map.nodes[nodeIndex]
              if (!el) return
              const result = updateNodeTranslation(el, updated)
              if (result === 'mismatch') {
                // Revert the node text to source so stale translation is removed
                const sids = getSidsFromElement(el)
                const sourceSeg = updated.find(
                  (s) => sids.includes(Number(s.sid)) && s.source,
                )
                if (sourceSeg) {
                  replaceTextContent(
                    el,
                    stripSegmentTags(sourceSeg.source).trim(),
                  )
                }
                showNodeWarning(el)
              } else {
                clearNodeWarning(el)
              }
            })
          }
          onTranslationUpdate(numericSid, target, updated)
          return updated
        })
      }
    }

    const off = ContextPreviewChannel.onMessage(handleMessage)
    return off
  }, [
    onHighlight,
    onTranslationUpdate,
    highlightRef,
    targetRef,
    showNodeWarning,
    clearNodeWarning,
  ])

  // Request segments from CatTool on mount
  useEffect(() => {
    ContextPreviewChannel.sendMessage({type: 'requestSegments'})
  }, [])

  return {segments, setSegments, currentContextUrl}
}

export default useContextPreviewMessages
