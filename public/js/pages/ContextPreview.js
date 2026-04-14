import React, {useEffect, useRef, useState, useCallback, useMemo} from 'react'
import {mountPage} from './mountPage'
import ContextPreviewChannel from '../utils/contextPreviewChannel'
import {findSegmentSidsByClick, tagSegments} from '../utils/contextPreviewUtils'
import {SegmentedControl} from '../components/common/SegmentedControl'
import IconDown from '../components/icons/IconDown'
import useContextDocument from '../hooks/useContextDocument'
import useContextHighlight from '../hooks/useContextHighlight'
import useContextPreviewMessages from '../hooks/useContextPreviewMessages'
import {
  LivePreviewPanel,
  ScreenshotContextPanel,
} from '../components/contextPreview'

const VIEW_MODES = {
  BOTH: 'both',
  SOURCE: 'source',
  TARGET: 'target',
}

const VIEW_OPTIONS = [
  {id: VIEW_MODES.SOURCE, name: 'Source'},
  {id: VIEW_MODES.TARGET, name: 'Translation'},
  {id: VIEW_MODES.BOTH, name: 'Both'},
]

const CONTENT_VIEWS = {
  LIVE_PREVIEW: 'live-preview',
  SCREENSHOT: 'screenshot',
}

const CONTENT_VIEW_OPTIONS = [
  {id: CONTENT_VIEWS.LIVE_PREVIEW, name: 'Live preview'},
  {id: CONTENT_VIEWS.SCREENSHOT, name: 'Screenshot'},
]

const ContextPreview = () => {
  const [viewMode, setViewMode] = useState(VIEW_MODES.BOTH)
  const [contentView, setContentView] = useState(CONTENT_VIEWS.LIVE_PREVIEW)
  const [htmlReady, setHtmlReady] = useState(0)
  const [zoomLevel, setZoomLevel] = useState(100)

  const handleContentViewChange = useCallback((newView) => {
    setContentView(newView)
    if (newView === CONTENT_VIEWS.SCREENSHOT) {
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
  const pendingHighlightRef = useRef(null)

  const showNodeWarning = useCallback(
    (el) => el.classList.add('context-preview-node--mismatch'),
    [],
  )
  const clearNodeWarning = useCallback(
    (el) => el.classList.remove('context-preview-node--mismatch'),
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
      pendingHighlightRef.current = numericSid
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
    // no additional action needed here; useContextPreviewMessages updates segments state
  }, [])

  const {segments, currentContextUrl} = useContextPreviewMessages({
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
    if (contentView !== CONTENT_VIEWS.LIVE_PREVIEW) {
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
    if (
      !segments.length ||
      !htmlReady ||
      contentView !== CONTENT_VIEWS.LIVE_PREVIEW
    )
      return
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

  // Re-apply pending highlight after tagging completes
  useEffect(() => {
    if (
      !segments.length ||
      !htmlReady ||
      contentView !== CONTENT_VIEWS.LIVE_PREVIEW
    )
      return
    const sid = pendingHighlightRef.current
    if (sid == null || highlight) return
    const total = applyHighlightsForSegment(sid, 0, true)
    if (total > 0) {
      setHighlight({mode: 'segment', sid, activeIndex: 0, total})
    }
  }, [
    segments,
    htmlReady,
    contentView,
    highlight,
    applyHighlightsForSegment,
    setHighlight,
  ])

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
          ContextPreviewChannel.sendMessage({
            type: 'loadMoreSegments',
            where: 'before',
          })
        }
      }
      if (scrollBottom < 200 && now - lastRequestRef.after > THROTTLE_MS) {
        if (hasUntaggedNodesInViewport('after')) {
          lastRequestRef.after = now
          ContextPreviewChannel.sendMessage({
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
    if (!htmlContent || contentView !== CONTENT_VIEWS.LIVE_PREVIEW) return

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
      applyHighlightsForNode(nodeIndex, 0, false)
      setHighlight({mode: 'node', nodeIndex, sids, activeSegIdx: 0})
      ContextPreviewChannel.sendMessage({type: 'segmentClicked', sid: sids[0]})
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
      applyHighlightsForNode(nodeIndex, 0, false)
      setHighlight({mode: 'node', nodeIndex, sids, activeSegIdx: 0})
      ContextPreviewChannel.sendMessage({type: 'segmentClicked', sid: sids[0]})
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
      <div className="context-preview-container">
        <div className="context-preview-loading">Loading document...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="context-preview-container">
        <div className="context-preview-error">
          <h2>Error loading document</h2>
          <p>{error}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="context-preview-container">
      <div className="context-preview-toolbar">
        {hasScreenshots && (
          <SegmentedControl
            name="context-preview-content-view"
            options={CONTENT_VIEW_OPTIONS}
            selectedId={contentView}
            onChange={handleContentViewChange}
            compact
          />
        )}
        {contentView === CONTENT_VIEWS.LIVE_PREVIEW && (
          <SegmentedControl
            name="context-preview-view-mode"
            options={VIEW_OPTIONS}
            selectedId={viewMode}
            onChange={setViewMode}
            compact
          />
        )}

        <div className="context-preview-zoom">
          <button
            className="context-preview-zoom__button"
            onClick={handleZoomOut}
            disabled={zoomLevel <= 50}
            aria-label="Zoom out"
          >
            −
          </button>
          <span className="context-preview-zoom__level">{zoomLevel}%</span>
          <button
            className="context-preview-zoom__button"
            onClick={handleZoomIn}
            disabled={zoomLevel >= 200}
            aria-label="Zoom in"
          >
            +
          </button>
          <button
            className="context-preview-zoom__reset"
            onClick={handleZoomReset}
            disabled={zoomLevel === 100}
            aria-label="Reset zoom"
          >
            Reset
          </button>
        </div>

        {highlight &&
          ((highlight.mode === 'segment' && highlight.total > 1) ||
            (highlight.mode === 'node' && highlight.sids.length > 1)) && (
            <div className="context-preview-nav">
              <button
                className="context-preview-nav__button"
                onClick={handlePrev}
                aria-label="Previous"
              >
                <IconDown size={16} />
              </button>
              <span className="context-preview-nav__counter">
                {highlight.mode === 'segment'
                  ? `${highlight.activeIndex + 1} of ${highlight.total}`
                  : `Segment ${highlight.activeSegIdx + 1} of ${highlight.sids.length}`}
              </span>
              <button
                className="context-preview-nav__button"
                onClick={handleNext}
                aria-label="Next"
              >
                <IconDown size={16} />
              </button>
            </div>
          )}
      </div>
      <div className="context-preview-panels">
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.SOURCE) &&
          (contentView === CONTENT_VIEWS.LIVE_PREVIEW ? (
            <LivePreviewPanel
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
          <div className="context-preview-divider" />
        )}
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.TARGET) &&
          (contentView === CONTENT_VIEWS.LIVE_PREVIEW ? (
            <LivePreviewPanel
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

export default ContextPreview

mountPage({
  Component: ContextPreview,
  rootElement: document.getElementsByClassName('context-review__page')[0],
})
