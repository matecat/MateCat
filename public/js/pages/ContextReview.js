import React, {useEffect, useRef, useState, useCallback, useMemo} from 'react'
import {mountPage} from './mountPage'
import ContextReviewChannel from '../utils/contextReviewChannel'
import {findSegmentSidsByClick, tagSegments} from '../utils/contextReviewUtils'
import {SegmentedControl} from '../components/common/SegmentedControl'
import IconDown from '../components/icons/IconDown'
import useContextDocument from '../hooks/useContextDocument'
import useContextHighlight from '../hooks/useContextHighlight'
import useContextReviewMessages from '../hooks/useContextReviewMessages'
import {
  HtmlContextPanel,
  ScreenshotContextPanel,
} from '../components/contextReview'

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

const CONTENT_VIEW_OPTIONS = [
  {id: 'html', name: 'HTML'},
  {id: 'screenshot', name: 'Screenshot'},
]

const ContextReview = () => {
  const [viewMode, setViewMode] = useState(VIEW_MODES.BOTH)
  const [contentView, setContentView] = useState('html')
  const [htmlReady, setHtmlReady] = useState(0)
  const [zoomLevel, setZoomLevel] = useState(100)

  const handleContentViewChange = useCallback((newView) => {
    setContentView(newView)
    if (newView === 'screenshot') {
      setViewMode(VIEW_MODES.SOURCE)
    }
  }, [])

  const handleZoomIn = useCallback(() => {
    setZoomLevel((prev) => Math.min(prev + 25, 200))
  }, [])

  const handleZoomOut = useCallback(() => {
    setZoomLevel((prev) => Math.max(prev - 25, 50))
  }, [])

  const handleZoomReset = useCallback(() => {
    setZoomLevel(100)
  }, [])

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

  const screenshotMap = useMemo(
    () =>
      Object.fromEntries(
        segments
          .filter((s) => s.screenshot)
          .map((s) => [Number(s.sid), s.screenshot]),
      ),
    [segments],
  )

  const hasScreenshots = Object.keys(screenshotMap).length > 0

  const screenshotUrl = useMemo(() => {
    if (highlight?.sid) return screenshotMap[highlight.sid] ?? null
    const firstWithScreenshot = segments.find((s) => s.screenshot)
    return firstWithScreenshot?.screenshot ?? null
  }, [highlight, screenshotMap, segments])

  const segmentsRef = useRef([])
  useEffect(() => {
    segmentsRef.current = segments
  }, [segments])

  // Render the fetched HTML into panels once (or when viewMode changes)
  const htmlRenderedRef = useRef({source: '', target: ''})

  useEffect(() => {
    if (!htmlContent) return

    // When switching to screenshot mode, clear HTML content from refs
    if (contentView !== 'html') {
      if (sourceRef.current) sourceRef.current.innerHTML = ''
      if (targetRef.current) targetRef.current.innerHTML = ''
      htmlRenderedRef.current = {source: '', target: ''}
      return
    }

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
  }, [htmlContent, viewMode, contentView])

  // Tag segments in panels when segments or HTML changes
  useEffect(() => {
    if (!segments.length || !htmlReady || contentView !== 'html') return
    if (targetRef.current) {
      tagSegments(targetRef.current, segments, {
        replaceWithTarget: true,
        metadataMap,
      })
    }
    if (sourceRef.current) {
      tagSegments(sourceRef.current, segments, {metadataMap})
    }
  }, [segments, htmlReady, viewMode, metadataMap, contentView])

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
    if (!htmlContent || contentView !== 'html') return

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
  }, [htmlContent, viewMode, applyHighlightsForNode, setHighlight, contentView])

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
        {hasScreenshots && (
          <SegmentedControl
            name="context-review-content-view"
            options={CONTENT_VIEW_OPTIONS}
            selectedId={contentView}
            onChange={handleContentViewChange}
            compact
          />
        )}
        {contentView === 'html' && (
          <SegmentedControl
            name="context-review-view-mode"
            options={VIEW_OPTIONS}
            selectedId={viewMode}
            onChange={setViewMode}
            compact
          />
        )}
        {hasScreenshots && (
          <div className="context-review-zoom">
            <button
              className="context-review-zoom__button"
              onClick={handleZoomOut}
              disabled={zoomLevel <= 50}
              aria-label="Zoom out"
            >
              −
            </button>
            <span className="context-review-zoom__level">{zoomLevel}%</span>
            <button
              className="context-review-zoom__button"
              onClick={handleZoomIn}
              disabled={zoomLevel >= 200}
              aria-label="Zoom in"
            >
              +
            </button>
            <button
              className="context-review-zoom__reset"
              onClick={handleZoomReset}
              disabled={zoomLevel === 100}
              aria-label="Reset zoom"
            >
              Reset
            </button>
          </div>
        )}
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
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.SOURCE) &&
          (contentView === 'html' ? (
            <HtmlContextPanel
              key={`source-${contentView}`}
              panelRef={sourceRef}
              title="Source"
              zoomLevel={zoomLevel}
            />
          ) : (
            <ScreenshotContextPanel
              key={`source-${contentView}`}
              screenshotUrl={screenshotUrl}
              zoomLevel={zoomLevel}
              title="Source"
            />
          ))}
        {viewMode === VIEW_MODES.BOTH && (
          <div className="context-review-divider" />
        )}
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.TARGET) &&
          (contentView === 'html' ? (
            <HtmlContextPanel
              key={`target-${contentView}`}
              panelRef={targetRef}
              title="Translation"
              zoomLevel={zoomLevel}
            />
          ) : (
            <ScreenshotContextPanel
              key={`target-${contentView}`}
              screenshotUrl={screenshotUrl}
              zoomLevel={zoomLevel}
              title="Translation"
            />
          ))}
      </div>
    </div>
  )
}

export default ContextReview

mountPage({
  Component: ContextReview,
  rootElement: document.getElementsByClassName('context-review__page')[0],
})
