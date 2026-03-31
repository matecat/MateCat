import React, {useEffect, useRef, useState, useCallback, useMemo} from 'react'
import {mountPage} from './mountPage'
import ContextReviewChannel from '../utils/contextReviewChannel'
import {findSegmentSidsByClick, tagSegments} from '../utils/contextReviewUtils'
import {SegmentedControl} from '../components/common/SegmentedControl'
import IconDown from '../components/icons/IconDown'
import useContextDocument from '../hooks/useContextDocument'
import useContextHighlight from '../hooks/useContextHighlight'
import useContextReviewMessages from '../hooks/useContextReviewMessages'

const VIEW_MODES = {
  BOTH: 'both',
  SOURCE: 'source',
  TARGET: 'target',
}

const VIEW_OPTIONS = [
  {id: VIEW_MODES.SOURCE, name: 'Source'},
  {id: VIEW_MODES.TARGET, name: 'Translation'},
  {id: VIEW_MODES.BOTH, name: 'Source&Target'},
]

const ContextReview = () => {
  const [viewMode, setViewMode] = useState(VIEW_MODES.BOTH)
  const [htmlReady, setHtmlReady] = useState(0)

  const sourceRef = useRef(null)
  const targetRef = useRef(null)

  const showNodeWarning = useCallback(
    (el) => el.classList.add('context-review-node--mismatch'),
    [],
  )
  const clearNodeWarning = useCallback(
    (el) => el.classList.remove('context-review-node--mismatch'),
    [],
  )

  const {
    highlight,
    setHighlight,
    highlightRef,
    applyHighlightsForSegment,
    applyHighlightsForNode,
    handlePrev,
    handleNext,
  } = useContextHighlight({sourceRef, targetRef})

  const onHighlight = useCallback(
    (numericSid) => {
      const total = applyHighlightsForSegment(numericSid, 0, true)
      setHighlight(
        total > 0
          ? {mode: 'segment', sid: numericSid, activeIndex: 0, total}
          : null,
      )
    },
    [applyHighlightsForSegment, setHighlight],
  )

  const onTranslationUpdate = useCallback(() => {
    // no additional action needed here; useContextReviewMessages updates segments state
  }, [])

  const {segments, currentContextUrl} = useContextReviewMessages({
    onHighlight,
    onTranslationUpdate,
    highlightRef,
    targetRef,
    showNodeWarning,
    clearNodeWarning,
  })

  // Derive the URL to pass to useContextDocument:
  // - If highlight messages have arrived and carried a context_url → use it.
  // - Otherwise → use the first segment's context_url (if any).
  // - Passing null → hook falls back to the hardcoded constant.
  const firstSegmentContextUrl = useMemo(
    () => segments.find((s) => s.context_url)?.context_url ?? null,
    [segments],
  )
  const documentUrl = currentContextUrl ?? firstSegmentContextUrl

  const {htmlContent, loading, error} = useContextDocument(documentUrl)

  // Build metadataMap for tagSegments strategy pass
  const metadataMap = useMemo(
    () =>
      Object.fromEntries(
        segments
          .filter((s) => s.resname && s.restype)
          .map((s) => [
            Number(s.sid),
            {resname: s.resname, restype: s.restype},
          ]),
      ),
    [segments],
  )

  const segmentsRef = useRef([])
  useEffect(() => {
    segmentsRef.current = segments
  }, [segments])

  // Render the fetched HTML into panels once (or when viewMode changes)
  const htmlRenderedRef = useRef({source: '', target: ''})

  useEffect(() => {
    if (!htmlContent) return
    let injected = false
    if (
      sourceRef.current &&
      htmlRenderedRef.current.source !== htmlContent + viewMode
    ) {
      sourceRef.current.innerHTML = htmlContent
      htmlRenderedRef.current.source = htmlContent + viewMode
      injected = true
    }
    if (
      targetRef.current &&
      htmlRenderedRef.current.target !== htmlContent + viewMode
    ) {
      targetRef.current.innerHTML = htmlContent
      htmlRenderedRef.current.target = htmlContent + viewMode
      injected = true
    }
    if (injected) setHtmlReady((prev) => prev + 1)
  }, [htmlContent, viewMode])

  // Tag segments in panels when segments or HTML changes
  useEffect(() => {
    if (!segments.length || !htmlReady) return
    if (targetRef.current) {
      tagSegments(targetRef.current, segments, {
        replaceWithTarget: true,
        metadataMap,
      })
    }
    if (sourceRef.current) {
      tagSegments(sourceRef.current, segments, {metadataMap})
    }
  }, [segments, htmlReady, viewMode, metadataMap])

  // Scroll listener — detect untagged nodes and request more segments
  useEffect(() => {
    const panel = sourceRef.current || targetRef.current
    if (!panel || !segments.length) return

    const lastRequestRef = {before: 0, after: 0}
    const THROTTLE_MS = 1000

    const hasUntaggedNodesInViewport = (region) => {
      const container = sourceRef.current || targetRef.current
      if (!container) return false
      const meaningfulEls = container.querySelectorAll(
        'p, li, td, th, h1, h2, h3, h4',
      )
      const viewportMidY = window.innerHeight / 2
      for (const el of meaningfulEls) {
        const elRect = el.getBoundingClientRect()
        if (elRect.bottom < 0 || elRect.top > window.innerHeight) continue
        const midY = (elRect.top + elRect.bottom) / 2
        if (region === 'before' && midY > viewportMidY) continue
        if (region === 'after' && midY < viewportMidY) continue
        if (
          !el.hasAttribute('data-context-sids') &&
          !el.querySelector('[data-context-sids]')
        ) {
          return true
        }
      }
      return false
    }

    const handleScroll = () => {
      const now = Date.now()
      const scrollTop = window.scrollY
      const scrollBottom =
        document.documentElement.scrollHeight - scrollTop - window.innerHeight
      if (scrollTop < 200 && now - lastRequestRef.before > THROTTLE_MS) {
        if (hasUntaggedNodesInViewport('before')) {
          lastRequestRef.before = now
          ContextReviewChannel.sendMessage({
            type: 'loadMoreSegments',
            where: 'before',
          })
        }
      }
      if (scrollBottom < 200 && now - lastRequestRef.after > THROTTLE_MS) {
        if (hasUntaggedNodesInViewport('after')) {
          lastRequestRef.after = now
          ContextReviewChannel.sendMessage({
            type: 'loadMoreSegments',
            where: 'after',
          })
        }
      }
    }

    window.addEventListener('scroll', handleScroll)
    return () => window.removeEventListener('scroll', handleScroll)
  }, [segments, viewMode])

  // Click listeners on both panels
  useEffect(() => {
    const sourceContainer = sourceRef.current
    const targetContainer = targetRef.current
    if (!htmlContent) return

    const handleSourceClick = (event) => {
      event.preventDefault()
      const result = findSegmentSidsByClick(
        event.target,
        sourceContainer,
        segmentsRef.current,
        'source',
      )
      if (!result) return
      const {sids, nodeIndex} = result
      ContextReviewChannel.sendMessage({type: 'segmentClicked', sid: sids[0]})
      applyHighlightsForNode(nodeIndex, 0, false)
      setHighlight({mode: 'node', nodeIndex, sids, activeSegIdx: 0})
    }

    const handleTargetClick = (event) => {
      event.preventDefault()
      const result = findSegmentSidsByClick(
        event.target,
        targetContainer,
        segmentsRef.current,
        'target',
      )
      if (!result) return
      const {sids, nodeIndex} = result
      ContextReviewChannel.sendMessage({type: 'segmentClicked', sid: sids[0]})
      applyHighlightsForNode(nodeIndex, 0, false)
      setHighlight({mode: 'node', nodeIndex, sids, activeSegIdx: 0})
    }

    if (sourceContainer)
      sourceContainer.addEventListener('click', handleSourceClick)
    if (targetContainer)
      targetContainer.addEventListener('click', handleTargetClick)

    return () => {
      if (sourceContainer)
        sourceContainer.removeEventListener('click', handleSourceClick)
      if (targetContainer)
        targetContainer.removeEventListener('click', handleTargetClick)
    }
  }, [htmlContent, viewMode, applyHighlightsForNode, setHighlight])

  if (loading) {
    return (
      <div className="context-review-container">
        <div className="context-review-loading">Loading document...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="context-review-container">
        <div className="context-review-error">
          <h2>Error loading document</h2>
          <p>{error}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="context-review-container">
      <div className="context-review-toolbar">
        <SegmentedControl
          name="context-review-view-mode"
          options={VIEW_OPTIONS}
          selectedId={viewMode}
          onChange={setViewMode}
          compact
        />
        {highlight &&
          ((highlight.mode === 'segment' && highlight.total > 1) ||
            (highlight.mode === 'node' && highlight.sids.length > 1)) && (
            <div className="context-review-nav">
              <button
                className="context-review-nav__button"
                onClick={handlePrev}
                aria-label="Previous"
              >
                <IconDown size={16} />
              </button>
              <span className="context-review-nav__counter">
                {highlight.mode === 'segment'
                  ? `${highlight.activeIndex + 1} of ${highlight.total}`
                  : `Segment ${highlight.activeSegIdx + 1} of ${highlight.sids.length}`}
              </span>
              <button
                className="context-review-nav__button"
                onClick={handleNext}
                aria-label="Next"
              >
                <IconDown size={16} />
              </button>
            </div>
          )}
      </div>
      <div className="context-review-panels">
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.SOURCE) && (
          <div className="context-review-panel">
            <div className="context-review-panel-header">Source</div>
            <div ref={sourceRef} className="context-review-content" />
          </div>
        )}
        {viewMode === VIEW_MODES.BOTH && (
          <div className="context-review-divider" />
        )}
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.TARGET) && (
          <div className="context-review-panel">
            <div className="context-review-panel-header">Translation</div>
            <div ref={targetRef} className="context-review-content" />
          </div>
        )}
      </div>
    </div>
  )
}

export default ContextReview

mountPage({
  Component: ContextReview,
  rootElement: document.getElementsByClassName('context-review__page')[0],
})
