import {useState, useRef, useCallback} from 'react'
import {
  clearHighlights,
  highlightBySid,
  setActiveHighlight,
  getSegmentNodeMap,
} from '../utils/contextPreviewUtils'
import ContextPreviewChannel from '../utils/contextPreviewChannel'

// Manual scroll — scrollIntoView propagates through shadow DOM to outer page
function scrollToCenter(el, behavior = 'smooth') {
  const root = el.getRootNode()
  const host = root instanceof ShadowRoot ? root.host : null
  const scrollContainer = host
    ? host.closest('.context-preview-content')
    : el.closest('.context-preview-content')
  if (!scrollContainer) return

  const elRect = el.getBoundingClientRect()
  const containerRect = scrollContainer.getBoundingClientRect()
  const targetTop =
    scrollContainer.scrollTop +
    (elRect.top - containerRect.top) -
    containerRect.height / 2 +
    elRect.height / 2

  scrollContainer.scrollTo({top: targetTop, behavior})
}

/**
 * Manages highlight state and occurrence navigation for the ContextPreview panels.
 *
 * @param {{sourceRef: React.RefObject, targetRef: React.RefObject}} params
 * @returns {{
 *   highlight: object|null,
 *   setHighlight: Function,
 *   highlightRef: React.RefObject,
 *   applyHighlightsForSegment: Function,
 *   applyHighlightsForNode: Function,
 *   navigateHighlight: Function,
 *   handlePrev: Function,
 *   handleNext: Function,
 * }}
 */
const useContextHighlight = ({sourceRef, targetRef}) => {
  const [highlight, setHighlightState] = useState(null)
  const highlightRef = useRef(null)

  const setHighlight = useCallback((valueOrUpdater) => {
    if (typeof valueOrUpdater !== 'function') {
      highlightRef.current = valueOrUpdater
    }
    setHighlightState((prev) => {
      const next =
        typeof valueOrUpdater === 'function'
          ? valueOrUpdater(prev)
          : valueOrUpdater
      highlightRef.current = next
      return next
    })
  }, [])

  const applyHighlightsForSegment = useCallback(
    (sid, activeIndex, scroll) => {
      let total = 0
      if (sourceRef.current) {
        clearHighlights(sourceRef.current)
        const res = highlightBySid(sourceRef.current, sid, activeIndex)
        total = res.total
        if (scroll && res.marks[activeIndex]) {
          scrollToCenter(res.marks[activeIndex][0])
        }
      }
      if (targetRef.current) {
        clearHighlights(targetRef.current)
        const res = highlightBySid(targetRef.current, sid, activeIndex)
        if (!total) total = res.total
        if (scroll && res.marks[activeIndex]) {
          scrollToCenter(res.marks[activeIndex][0])
        }
      }
      return total
    },
    [sourceRef, targetRef],
  )

  const applyHighlightsForNode = useCallback(
    (nodeIndex, activeSegIdx, scroll) => {
      ;[sourceRef, targetRef].forEach((ref) => {
        if (!ref.current) return
        const map = getSegmentNodeMap(ref.current)
        if (!map) return
        const sids = map.nodeIndexToSids.get(nodeIndex) ?? []
        const activeSid = sids[activeSegIdx] ?? sids[0]
        if (activeSid == null) return
        clearHighlights(ref.current)
        const res = highlightBySid(ref.current, activeSid, 0)
        if (scroll && res.marks[0]?.[0]) {
          scrollToCenter(res.marks[0][0])
        }
      })
    },
    [sourceRef, targetRef],
  )

  const navigateHighlight = useCallback(
    (direction) => {
      if (!highlight) return

      if (highlight.mode === 'segment') {
        const nextIndex =
          direction === 'next'
            ? (highlight.activeIndex + 1) % highlight.total
            : (highlight.activeIndex - 1 + highlight.total) % highlight.total
        ;[sourceRef, targetRef].forEach((ref) => {
          if (!ref.current) return
          const mark = setActiveHighlight(ref.current, nextIndex)
          if (mark) scrollToCenter(mark)
        })
        setHighlight((prev) => ({...prev, activeIndex: nextIndex}))
        return
      }

      if (highlight.mode === 'node') {
        const {sids, activeSegIdx, nodeIndex} = highlight
        const nextSegIdx =
          direction === 'next'
            ? (activeSegIdx + 1) % sids.length
            : (activeSegIdx - 1 + sids.length) % sids.length
        ContextPreviewChannel.sendMessage({
          type: 'segmentClicked',
          sid: sids[nextSegIdx],
        })
        applyHighlightsForNode(nodeIndex, nextSegIdx, false)
        setHighlight((prev) => ({...prev, activeSegIdx: nextSegIdx}))
      }
    },
    [highlight, sourceRef, targetRef, applyHighlightsForNode, setHighlight],
  )

  const handlePrev = useCallback(
    () => navigateHighlight('prev'),
    [navigateHighlight],
  )
  const handleNext = useCallback(
    () => navigateHighlight('next'),
    [navigateHighlight],
  )

  return {
    highlight,
    setHighlight,
    highlightRef,
    applyHighlightsForSegment,
    applyHighlightsForNode,
    navigateHighlight,
    handlePrev,
    handleNext,
  }
}

export default useContextHighlight
