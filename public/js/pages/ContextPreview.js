import React, {useEffect, useRef, useState, useCallback, useMemo} from 'react'
import {mountPage} from './mountPage'
import ContextPreviewChannel from '../utils/contextPreviewChannel'
import {findSegmentSidsByClick, tagSegments, suppressClickTraps, getSidsFromElement, getSegmentNodeMap, checkNodeTranslationStatus} from '../utils/contextPreviewUtils'
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

// RTL primary language subtags supported by Matecat
const RTL_PRIMARY = new Set([
  'ar', 'he', 'fa', 'ur', 'dv', 'ps', 'ckb', 'prs', 'ydd', 'shu', 'kas',
  'rhg', 'sd', 'azb', 'pbt', 'syc', 'tmh', 'ug', 'yi', 'nqo', 'sdh', 'syr',
])

const isRTLLanguage = (code) => {
  if (!code) return false
  try {
    const dir = new Intl.Locale(code).textInfo?.direction
    if (dir) return dir === 'rtl'
  } catch {}
  return RTL_PRIMARY.has(code.split('-')[0].toLowerCase())
}

const ContextPreview = () => {
  const [viewMode, setViewMode] = useState(VIEW_MODES.TARGET)
  const [contentView, setContentView] = useState(CONTENT_VIEWS.LIVE_PREVIEW)
  const [htmlReady, setHtmlReady] = useState(0)
  const [zoomLevel, setZoomLevel] = useState(100)
  const [hasMismatch, setHasMismatch] = useState(false)

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
  const sourceScrollRef = useRef(null)
  const targetScrollRef = useRef(null)
  const pendingHighlightRef = useRef(null)

  const {
    highlight,
    highlightHidden,
    setHighlight,
    highlightRef,
    applyHighlightsForSegment,
    applyHighlightsForNode,
    handlePrev,
    handleNext,
  } = useContextHighlight({sourceRef, targetRef})

  const showNodeWarning = useCallback(
    (el) => {
      setHasMismatch(true)
      const sids = getSidsFromElement(el)
      if (sids.length > 1 && targetRef.current) {
        const map = getSegmentNodeMap(targetRef.current)
        if (map) {
          const nodeIndex = map.nodes.indexOf(el)
          if (nodeIndex !== -1) {
            setHighlight((prev) => ({
              mode: 'node',
              nodeIndex,
              sids,
              activeSegIdx:
                prev?.mode === 'segment'
                  ? Math.max(0, sids.indexOf(prev.sid))
                  : 0,
            }))
          }
        }
      }
    },
    [targetRef, setHighlight],
  )

  const clearNodeWarning = useCallback(
    () => setHasMismatch(false),
    [],
  )

  // Returns {nodeIndex, sids} when the segment's node is shared with other segments.
  const getSharedNodeInfo = useCallback((sid) => {
    const ref = targetRef.current || sourceRef.current
    const map = ref ? getSegmentNodeMap(ref) : null
    if (!map) return null
    const nodeIndices = map.sidToNodeIndices.get(sid) ?? []
    if (!nodeIndices.length) return null
    const allSids = [
      ...new Set(nodeIndices.flatMap((ni) => map.nodeIndexToSids.get(ni) ?? [])),
    ]
    return allSids.length > 1 ? {nodeIndex: nodeIndices[0], sids: allSids} : null
  }, [])

  const onHighlight = useCallback(
    (numericSid) => {
      setHasMismatch(false)
      const current = highlightRef.current
      // Already in node mode for this node — just update which segment is active.
      if (current?.mode === 'node' && current.sids.includes(numericSid)) {
        const newActiveSegIdx = current.sids.indexOf(numericSid)
        if (newActiveSegIdx !== current.activeSegIdx) {
          applyHighlightsForNode(current.sids, newActiveSegIdx, false)
          setHighlight((prev) => ({...prev, activeSegIdx: newActiveSegIdx}))
        }
        return
      }
      pendingHighlightRef.current = numericSid
      const total = applyHighlightsForSegment(numericSid, 0, true)
      if (total > 0) {
        const shared = getSharedNodeInfo(numericSid)
        if (shared) {
          setHighlight({
            mode: 'node',
            nodeIndex: shared.nodeIndex,
            sids: shared.sids,
            activeSegIdx: Math.max(0, shared.sids.indexOf(numericSid)),
          })
          return
        }
      }
      setHighlight(
        total > 0
          ? {mode: 'segment', sid: numericSid, activeIndex: 0, total}
          : null,
      )
    },
    [applyHighlightsForSegment, applyHighlightsForNode, setHighlight, highlightRef, getSharedNodeInfo],
  )

  const onTranslationUpdate = useCallback(() => {
    // no additional action needed here; useContextPreviewMessages updates segments state
  }, [])

  const targetDir = isRTLLanguage(targetCode) ? 'rtl' : 'ltr'

  const {segments, currentContextUrl, currentSid} = useContextPreviewMessages({
    onHighlight,
    onTranslationUpdate,
    targetRef,
    showNodeWarning,
    clearNodeWarning,
    targetDir,
  })

  const {htmlContent, loading, error} = useContextDocument(currentContextUrl)

  // Filter segments to only those matching the currently displayed context URL
  const mappableSegments = useMemo(
    () =>
      currentContextUrl
        ? segments.filter((s) => s.context_url === currentContextUrl)
        : [],
    [segments, currentContextUrl],
  )

  // Build metadataMap for tagSegments strategy pass
  const metadataMap = useMemo(
    () =>
      Object.fromEntries(
        mappableSegments
          .filter((s) => s.resname && s.restype)
          .map((s) => [
            Number(s.sid),
            {resname: s.resname, restype: s.restype, client_name: s.client_name ?? null},
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

  const currentSegmentHasScreenshot = useMemo(
    () => currentSid != null && Boolean(screenshotMap[currentSid]),
    [currentSid, screenshotMap],
  )

  const currentHasScreenshot = useMemo(() => {
    if (highlight?.mode === 'segment' && highlight.sid != null) {
      return Boolean(screenshotMap[highlight.sid])
    }
    if (highlight?.mode === 'node' && highlight.sids?.length) {
      return highlight.sids.some((sid) => Boolean(screenshotMap[sid]))
    }
    if (currentSid != null) return Boolean(screenshotMap[currentSid])
    return hasScreenshots
  }, [highlight, currentSid, screenshotMap, hasScreenshots])

  const screenshotUrl = useMemo(() => {
    if (highlight?.sid) return screenshotMap[highlight.sid] ?? null
    if (currentSid != null) return screenshotMap[currentSid] ?? null
    const firstWithScreenshot = segments.find((s) => s.screenshot)
    return firstWithScreenshot?.screenshot ?? null
  }, [highlight, currentSid, screenshotMap, segments])

  useEffect(() => {
    if (!currentHasScreenshot && contentView === CONTENT_VIEWS.SCREENSHOT) {
      setContentView(CONTENT_VIEWS.LIVE_PREVIEW)
    }
  }, [currentHasScreenshot, contentView])

  // Auto-switch to Screenshot when the current segment has no HTML context but has a screenshot.
  // Gated on currentSid so we wait for the highlight message before deciding.
  useEffect(() => {
    if (currentSid == null) return
    if (
      contentView === CONTENT_VIEWS.LIVE_PREVIEW &&
      !currentContextUrl &&
      currentSegmentHasScreenshot
    ) {
      setContentView(CONTENT_VIEWS.SCREENSHOT)
      setViewMode(VIEW_MODES.SOURCE)
    }
  }, [contentView, currentContextUrl, currentSegmentHasScreenshot, currentSid])

  const segmentsRef = useRef([])
  useEffect(() => {
    segmentsRef.current = segments
  }, [segments])

  // Detect mismatch on the highlighted node whenever segments or highlight changes.
  // This catches the initial case where all translations already exist at load time —
  // useContextPreviewMessages only fires showNodeWarning on live edits.
  // Uses SID-based lookup so the correct panel element is found regardless of which
  // panel's nodeIndex is stored in the highlight state.
  useEffect(() => {
    if (highlight?.mode !== 'node' || hasMismatch) return
    const ref = targetRef.current || sourceRef.current
    const map = ref ? getSegmentNodeMap(ref) : null
    if (!map) return
    const firstSid = highlight.sids?.[highlight.activeSegIdx ?? 0] ?? highlight.sids?.[0]
    if (firstSid == null) return
    const nodeIndices = map.sidToNodeIndices.get(firstSid) ?? []
    const el = map.nodes[nodeIndices[0]]
    if (!el) return
    if (checkNodeTranslationStatus(el, segments) === 'mismatch') {
      setHasMismatch(true)
    }
  }, [highlight, segments, hasMismatch, targetRef, sourceRef])

  // Track segment request deduplication per direction
  const requestedAtRef = useRef({before: -1, after: -1, lastDir: null})

  // Render the fetched HTML into panels once (or when viewMode changes)
  const htmlRenderedRef = useRef({source: '', target: '', url: null})

  useEffect(() => {
    if (!currentContextUrl) htmlRenderedRef.current = {source: '', target: '', url: null}
  }, [currentContextUrl])

  useEffect(() => {
    if (!htmlContent) return

    // When switching to screenshot mode, clear HTML content from refs
    if (contentView !== CONTENT_VIEWS.LIVE_PREVIEW) {
      if (sourceRef.current) sourceRef.current.innerHTML = ''
      if (targetRef.current) targetRef.current.innerHTML = ''
      htmlRenderedRef.current = {source: '', target: '', url: null}
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
    if (injected) htmlRenderedRef.current.url = currentContextUrl
    if (!injected) return

    const links = [
      ...(sourceRef.current?.querySelectorAll('link[rel="stylesheet"]') || []),
      ...(targetRef.current?.querySelectorAll('link[rel="stylesheet"]') || []),
    ]

    if (links.length === 0) {
      suppressClickTraps(sourceRef.current)
      suppressClickTraps(targetRef.current)
      setHtmlReady((prev) => prev + 1)
      return
    }

    let settled = 0
    let cancelled = false
    const onSettle = () => {
      settled++
      if (!cancelled && settled >= links.length) {
        suppressClickTraps(sourceRef.current)
        suppressClickTraps(targetRef.current)
        setHtmlReady((prev) => prev + 1)
      }
    }
    links.forEach((link) => {
      if (link.sheet) {
        onSettle()
      } else {
        link.addEventListener('load', onSettle)
        link.addEventListener('error', onSettle)
      }
    })

    return () => {
      cancelled = true
    }
  }, [htmlContent, viewMode, contentView, currentContextUrl])

  // Tag segments in panels when segments or HTML changes
  useEffect(() => {
    if (
      !mappableSegments.length ||
      !htmlReady ||
      contentView !== CONTENT_VIEWS.LIVE_PREVIEW ||
      htmlRenderedRef.current.url !== currentContextUrl
    )
      return
    if (targetRef.current) {
      targetRef.current.classList.add('context-preview-target')
      tagSegments(targetRef.current, mappableSegments, {
        replaceWithTarget: true,
        metadataMap,
        targetDir,
      })
    }
    if (sourceRef.current) {
      tagSegments(sourceRef.current, mappableSegments, {metadataMap})
    }

    // Re-apply active highlight after re-tagging so marks and counters
    // reflect any eviction (e.g. text-mapped SID removed by point mapping).
    const h = highlightRef.current
    if (h?.mode === 'segment' && h.sid != null) {
      const idx = h.activeIndex ?? 0
      const total = applyHighlightsForSegment(h.sid, idx, false)
      const shared = total > 0 ? getSharedNodeInfo(h.sid) : null
      if (shared) {
        setHighlight({
          mode: 'node',
          nodeIndex: shared.nodeIndex,
          sids: shared.sids,
          activeSegIdx: Math.max(0, shared.sids.indexOf(h.sid)),
        })
      } else if (total !== h.total) {
        if (total === 0) {
          setHighlight(null)
        } else {
          setHighlight({
            ...h,
            total,
            activeIndex: Math.min(idx, total - 1),
          })
        }
      }
    } else if (h?.mode === 'node' && h.sids?.length) {
      applyHighlightsForNode(h.sids, h.activeSegIdx ?? 0, false)
    }
  }, [mappableSegments, htmlReady, viewMode, metadataMap, contentView, applyHighlightsForSegment, applyHighlightsForNode, setHighlight, getSharedNodeInfo])

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
      const shared = getSharedNodeInfo(sid)
      if (shared) {
        setHighlight({
          mode: 'node',
          nodeIndex: shared.nodeIndex,
          sids: shared.sids,
          activeSegIdx: Math.max(0, shared.sids.indexOf(sid)),
        })
      } else {
        setHighlight({mode: 'segment', sid, activeIndex: 0, total})
      }
    }
  }, [
    segments,
    htmlReady,
    contentView,
    highlight,
    applyHighlightsForSegment,
    setHighlight,
    getSharedNodeInfo,
  ])

  // Detect untagged nodes and proactively request adjacent segments
  useEffect(() => {
    const container = sourceRef.current || targetRef.current
    if (!container || !mappableSegments.length || !htmlReady) return
    if (contentView !== CONTENT_VIEWS.LIVE_PREVIEW) return

    const THROTTLE_MS = 2000
    let lastCheckTime = 0
    const segCount = mappableSegments.length

    // An element counts as "tagged" only if it (or a small, leaf-like
    // ancestor) carries data-context-sids. A high-level wrapper <div> that
    // maps a single segment but contains many block descendants must NOT
    // suppress lazy-loading for all those descendants.
    const SIDS_ATTR = 'data-context-sids'
    const BLOCK_CHECK = 'p, li, td, th, h1, h2, h3, h4'
    const isEffectivelyTagged = (el) => {
      if (el.hasAttribute(SIDS_ATTR)) return true
      const tagged = el.closest(`[${SIDS_ATTR}]`)
      if (!tagged) return false
      return tagged.querySelectorAll(BLOCK_CHECK).length <= 1
    }

    const checkForUntaggedNodes = () => {
      const now = Date.now()
      if (now - lastCheckTime < THROTTLE_MS) return
      lastCheckTime = now

      const meaningfulEls = container.querySelectorAll(BLOCK_CHECK)
      if (!meaningfulEls.length) return

      let firstTaggedIdx = -1
      let lastTaggedIdx = -1
      for (let i = 0; i < meaningfulEls.length; i++) {
        if (isEffectivelyTagged(meaningfulEls[i])) {
          if (firstTaggedIdx === -1) firstTaggedIdx = i
          lastTaggedIdx = i
        }
      }

      let hasUntaggedBefore = false
      let hasUntaggedAfter = false

      if (firstTaggedIdx === -1) {
        hasUntaggedBefore = true
        hasUntaggedAfter = true
      } else {
        for (let i = 0; i < firstTaggedIdx; i++) {
          if (!isEffectivelyTagged(meaningfulEls[i])) {
            hasUntaggedBefore = true
            break
          }
        }
        for (let i = lastTaggedIdx + 1; i < meaningfulEls.length; i++) {
          if (!isEffectivelyTagged(meaningfulEls[i])) {
            hasUntaggedAfter = true
            break
          }
        }
      }

      const wantBefore =
        hasUntaggedBefore && requestedAtRef.current.before !== segCount
      const wantAfter =
        hasUntaggedAfter && requestedAtRef.current.after !== segCount

      // CatTool uses a single {segmentId, where} state for the segment
      // loader, so sending both directions in the same tick causes the
      // second to overwrite the first. Send only one per cycle.
      if (wantBefore && wantAfter) {
        const pick = requestedAtRef.current.lastDir === 'before'
          ? 'after'
          : 'before'
        requestedAtRef.current[pick] = segCount
        requestedAtRef.current.lastDir = pick
        ContextPreviewChannel.sendMessage({
          type: 'loadMoreSegments',
          where: pick,
        })
      } else if (wantBefore) {
        requestedAtRef.current.before = segCount
        requestedAtRef.current.lastDir = 'before'
        ContextPreviewChannel.sendMessage({
          type: 'loadMoreSegments',
          where: 'before',
        })
      } else if (wantAfter) {
        requestedAtRef.current.after = segCount
        requestedAtRef.current.lastDir = 'after'
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
      applyHighlightsForNode(sids, 0, true)
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
      applyHighlightsForNode(sids, 0, true)
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

  // Sync scroll position between source and target panels in split view
  useEffect(() => {
    if (viewMode !== VIEW_MODES.BOTH) return
    if (contentView !== CONTENT_VIEWS.LIVE_PREVIEW) return
    const sourceEl = sourceScrollRef.current
    const targetEl = targetScrollRef.current
    if (!sourceEl || !targetEl) return

    let syncing = false

    const syncScroll = (origin, target) => {
      if (syncing) return
      syncing = true
      const maxScroll = origin.scrollHeight - origin.clientHeight
      const ratio = maxScroll > 0 ? origin.scrollTop / maxScroll : 0
      const targetMax = target.scrollHeight - target.clientHeight
      target.scrollTop = ratio * targetMax
      requestAnimationFrame(() => {
        syncing = false
      })
    }

    const onSourceScroll = () => syncScroll(sourceEl, targetEl)
    const onTargetScroll = () => syncScroll(targetEl, sourceEl)

    sourceEl.addEventListener('scroll', onSourceScroll, {passive: true})
    targetEl.addEventListener('scroll', onTargetScroll, {passive: true})

    return () => {
      sourceEl.removeEventListener('scroll', onSourceScroll)
      targetEl.removeEventListener('scroll', onTargetScroll)
    }
  }, [viewMode, contentView, htmlReady])

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

  if (!currentContextUrl && !htmlContent && !hasScreenshots) {
    return (
      <div className="context-preview-container">
        <div className="context-preview-empty">
          No context available for this segment
        </div>
      </div>
    )
  }

  return (
    <div className="context-preview-container">
      <div className="context-preview-toolbar">
        <div className="context-preview-toolbar__left">
          {currentHasScreenshot && currentContextUrl && (
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

        {contentView === CONTENT_VIEWS.LIVE_PREVIEW &&
          htmlReady > 0 &&
          mappableSegments.length > 0 &&
          highlightHidden && (
            <span className="context-preview-hidden-warning">
              Segment not found in preview
            </span>
          )}
        {contentView === CONTENT_VIEWS.LIVE_PREVIEW &&
          highlight?.mode === 'node' &&
          highlight.sids.length > 1 &&
          !hasMismatch && (
            <span className="context-preview-hidden-warning context-preview-hidden-warning--info">
              {highlight.sids.length} segments share this element
            </span>
          )}
        {contentView === CONTENT_VIEWS.LIVE_PREVIEW && hasMismatch && (
          <span className="context-preview-hidden-warning">
            Duplicate segments with conflicting translations
          </span>
        )}
        <div className="context-preview-toolbar__right">
          {contentView === CONTENT_VIEWS.LIVE_PREVIEW &&
            highlight &&
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
              scrollRef={sourceScrollRef}
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
              scrollRef={targetScrollRef}
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
