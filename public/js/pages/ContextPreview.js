import React, {useEffect, useRef, useState, useCallback, useMemo} from 'react'
import {mountPage} from './mountPage'
import ContextPreviewChannel from '../utils/contextPreviewChannel'
import {findSegmentSidsByClick, tagSegments} from '../utils/contextPreviewUtils'
import {SegmentedControl} from '../components/common/SegmentedControl'
import IconChevronLeft from '../components/icons/IconChevronLeft'
import IconChevronRight from '../components/icons/IconChevronRight'
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
  {id: VIEW_MODES.TARGET, name: 'Target'},
  {id: VIEW_MODES.BOTH, name: 'Split view'},
]

const CONTENT_VIEWS = {
  LIVE_PREVIEW: 'live-preview',
  SCREENSHOT: 'screenshot',
}

const CONTENT_VIEW_OPTIONS = [
  {id: CONTENT_VIEWS.LIVE_PREVIEW, name: 'HTML'},
  {id: CONTENT_VIEWS.SCREENSHOT, name: 'Screenshot'},
]

const ContextPreview = () => {
  const [viewMode, setViewMode] = useState(VIEW_MODES.BOTH)
  const [contentView, setContentView] = useState(CONTENT_VIEWS.LIVE_PREVIEW)
  const [htmlReady, setHtmlReady] = useState(0)
  const [zoomLevel, setZoomLevel] = useState(100)

  const urlParams = useMemo(() => new URLSearchParams(window.location.search), [])
  const sourceCode = urlParams.get('source_code') || config.source_code || ''
  const targetCode = urlParams.get('target_code') || config.target_code || ''

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
    targetRef,
    showNodeWarning,
    clearNodeWarning,
  })

  const {htmlContent, loading, error} = useContextDocument(currentContextUrl)

  // Filter segments to only those with context_url
  const mappableSegments = useMemo(
    () => segments.filter((s) => s.context_url),
    [segments],
  )

  // Build metadataMap for tagSegments strategy pass
  const metadataMap = useMemo(
    () =>
      Object.fromEntries(
        mappableSegments
          .filter((s) => s.resname && s.restype)
          .map((s) => [
            Number(s.sid),
            {resname: s.resname, restype: s.restype},
          ]),
      ),
    [mappableSegments],
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

  // Track segment request deduplication per direction
  const requestedAtRef = useRef({before: -1, after: -1})

  // Render the fetched HTML into panels once (or when viewMode changes)
  const htmlRenderedRef = useRef({source: '', target: ''})

  useEffect(() => {
    if (!currentContextUrl) htmlRenderedRef.current = {source: '', target: ''}
  }, [currentContextUrl])

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
      !mappableSegments.length ||
      !htmlReady ||
      contentView !== CONTENT_VIEWS.LIVE_PREVIEW
    )
      return
    if (targetRef.current) {
      tagSegments(targetRef.current, mappableSegments, {
        replaceWithTarget: true,
        metadataMap,
      })
    }
    if (sourceRef.current) {
      tagSegments(sourceRef.current, mappableSegments, {metadataMap})
    }
  }, [mappableSegments, htmlReady, viewMode, metadataMap, contentView])

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

  // Detect untagged nodes and proactively request adjacent segments
  useEffect(() => {
    const container = sourceRef.current || targetRef.current
    if (!container || !mappableSegments.length || !htmlReady) return
    if (contentView !== CONTENT_VIEWS.LIVE_PREVIEW) return

    const THROTTLE_MS = 2000
    let lastCheckTime = 0
    const segCount = mappableSegments.length

    const checkForUntaggedNodes = () => {
      const now = Date.now()
      if (now - lastCheckTime < THROTTLE_MS) return
      lastCheckTime = now

      const meaningfulEls = container.querySelectorAll(
        'p, li, td, th, h1, h2, h3, h4',
      )
      if (!meaningfulEls.length) return

      let firstTaggedIdx = -1
      let lastTaggedIdx = -1
      for (let i = 0; i < meaningfulEls.length; i++) {
        if (meaningfulEls[i].closest('[data-context-sids]')) {
          if (firstTaggedIdx === -1) firstTaggedIdx = i
          lastTaggedIdx = i
        }
      }

      if (firstTaggedIdx === -1) {
        if (requestedAtRef.current.before !== segCount) {
          requestedAtRef.current.before = segCount
          ContextPreviewChannel.sendMessage({
            type: 'loadMoreSegments',
            where: 'before',
          })
        }
        if (requestedAtRef.current.after !== segCount) {
          requestedAtRef.current.after = segCount
          ContextPreviewChannel.sendMessage({
            type: 'loadMoreSegments',
            where: 'after',
          })
        }
        return
      }

      let hasUntaggedBefore = false
      for (let i = 0; i < firstTaggedIdx; i++) {
        if (!meaningfulEls[i].closest('[data-context-sids]')) {
          hasUntaggedBefore = true
          break
        }
      }

      let hasUntaggedAfter = false
      for (let i = lastTaggedIdx + 1; i < meaningfulEls.length; i++) {
        if (!meaningfulEls[i].closest('[data-context-sids]')) {
          hasUntaggedAfter = true
          break
        }
      }

      if (hasUntaggedBefore && requestedAtRef.current.before !== segCount) {
        requestedAtRef.current.before = segCount
        ContextPreviewChannel.sendMessage({
          type: 'loadMoreSegments',
          where: 'before',
        })
      }
      if (hasUntaggedAfter && requestedAtRef.current.after !== segCount) {
        requestedAtRef.current.after = segCount
        ContextPreviewChannel.sendMessage({
          type: 'loadMoreSegments',
          where: 'after',
        })
      }
    }

    checkForUntaggedNodes()

    const scrollContainer =
      container.getRootNode()?.host?.closest('.context-preview-content') ??
      container.closest('.context-preview-content')

    if (scrollContainer) {
      const handleScroll = () => checkForUntaggedNodes()
      scrollContainer.addEventListener('scroll', handleScroll, {passive: true})
      return () => scrollContainer.removeEventListener('scroll', handleScroll)
    }
  }, [mappableSegments, htmlReady, contentView, viewMode])

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
        <div className="context-preview-toolbar__left">
          {hasScreenshots && (
            <SegmentedControl
              name="context-preview-content-view"
              className="context-preview-content-view"
              options={CONTENT_VIEW_OPTIONS}
              selectedId={contentView}
              onChange={handleContentViewChange}
              compact
              autoWidth
            />
          )}
          {contentView === CONTENT_VIEWS.LIVE_PREVIEW && (
            <SegmentedControl
              name="context-preview-view-mode"
              className="context-preview-view-mode"
              options={VIEW_OPTIONS}
              selectedId={viewMode}
              onChange={setViewMode}
              compact
              autoWidth
            />
          )}
        </div>

        <div className="context-preview-toolbar__right">
          {highlight &&
            ((highlight.mode === 'segment' && highlight.total > 1) ||
              (highlight.mode === 'node' && highlight.sids.length > 1)) && (
              <div className="context-preview-nav">
                <button
                  className="context-preview-nav__button"
                  onClick={handlePrev}
                  aria-label="Previous"
                >
                  <IconChevronLeft size={16} />
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
                  <IconChevronRight size={16} />
                </button>
              </div>
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
        </div>
      </div>
      <div className="context-preview-panels">
        {(viewMode === VIEW_MODES.BOTH || viewMode === VIEW_MODES.SOURCE) &&
          (contentView === CONTENT_VIEWS.LIVE_PREVIEW ? (
            <LivePreviewPanel
              key={`source-${contentView}`}
              panelRef={sourceRef}
              title="Source"
              languageLabel={viewMode === VIEW_MODES.BOTH ? `Source - ${sourceCode}` : undefined}
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
              languageLabel={viewMode === VIEW_MODES.BOTH ? `Target - ${targetCode}` : undefined}
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
  rootElement: document.getElementsByClassName('context-preview__page')[0],
})
