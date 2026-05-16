import {useState, useEffect, useRef} from 'react'
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
 *   targetRef: React.RefObject,
 *   showNodeWarning: (el: HTMLElement) => void,
 *   clearNodeWarning: (el: HTMLElement) => void,
 * }} params
 * @returns {{segments: Array, setSegments: Function, currentContextUrl: string|null}}
 */
const useContextPreviewMessages = ({
  onHighlight,
  onTranslationUpdate,
  targetRef,
  showNodeWarning,
  clearNodeWarning,
}) => {
  const [segments, setSegments] = useState([])
  const [currentContextUrl, setCurrentContextUrl] = useState(null)
  const currentContextUrlRef = useRef(null)
  const segmentsRef = useRef([])

  useEffect(() => {
    const handleMessage = (message) => {
      if (message.type === 'segments') {
        const incoming = message.segments ?? []
        const existingSids = new Set(segmentsRef.current.map((s) => Number(s.sid)))
        const newSegments = incoming.filter((s) => !existingSids.has(Number(s.sid)))
        if (newSegments.length > 0) {
          const next = [...segmentsRef.current, ...newSegments]
          segmentsRef.current = next
          setSegments(next)
        }
      }

      if (message.type === 'highlight') {
        const numericSid = Number(message.sid)
        const seg = segmentsRef.current.find((s) => Number(s.sid) === numericSid)
        const contextUrl = seg?.context_url ?? null
        if (contextUrl !== currentContextUrlRef.current) {
          currentContextUrlRef.current = contextUrl
          setCurrentContextUrl(contextUrl)
        }
        onHighlight(numericSid, contextUrl)
      }

      if (message.type === 'updateTranslation') {
        const {sid, target} = message
        const numericSid = Number(sid)
        const prev = segmentsRef.current
        const idx = prev.findIndex((seg) => Number(seg.sid) === numericSid)
        if (idx !== -1 && prev[idx].target !== target) {
          const updated = prev.map((seg) =>
            Number(seg.sid) === numericSid ? {...seg, target} : seg,
          )
          segmentsRef.current = updated
          if (targetRef.current) {
            const map = getSegmentNodeMap(targetRef.current)
            const nodeIndices = map?.sidToNodeIndices.get(numericSid) ?? []
            nodeIndices.forEach((nodeIndex) => {
              const el = map.nodes[nodeIndex]
              if (!el) return
              const result = updateNodeTranslation(el, updated)
              if (result === 'mismatch') {
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
        }
      }
    }

    const off = ContextPreviewChannel.onMessage(handleMessage)
    return off
  }, [
    onHighlight,
    onTranslationUpdate,
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
