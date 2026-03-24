import React, {useEffect, useRef, useState, useCallback} from 'react'
import {mountPage} from './mountPage'
import ContextReviewChannel from '../utils/contextReviewChannel'
import {
  clearHighlights,
  highlightBySid,
  setActiveHighlight,
  findSegmentSidByClick,
  tagSegments,
  replaceTextContent,
  stripSegmentTags,
} from '../utils/contextReviewUtils'
import {SegmentedControl} from '../components/common/SegmentedControl'
import IconDown from '../components/icons/IconDown'
const CONTEXT_REVIEW_HTML_URL =
  'https://files.sandbox.translated.com/provetta/content/launches/2025/04/04/launch_copy_of_demo/content/we-retail/language-masters/de/equipment.html'

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

/**
 * Resolves a potentially relative URL against a base URL string.
 *
 * @param {string} url
 * @param {string} baseUrl
 * @returns {string}
 */
const resolveUrl = (url, baseUrl) => {
  if (!url || url.startsWith('data:') || url.startsWith('#')) return url
  try {
    return new URL(url, baseUrl).href
  } catch {
    return url
  }
}

/**
 * Resolves all relative URLs inside a parsed DOM tree so that external
 * resources (stylesheets, scripts, images, etc.) load correctly when the
 * HTML is rendered on a different origin.
 *
 * @param {Document} doc  The parsed document
 * @param {string} baseUrl  The original document URL
 */
const resolveRelativeUrls = (doc, baseUrl) => {
  // src attributes (script, img, iframe, source, video, audio, …)
  doc.querySelectorAll('[src]').forEach((el) => {
    el.setAttribute('src', resolveUrl(el.getAttribute('src'), baseUrl))
  })
  // href attributes (link, a, area, …)
  doc.querySelectorAll('[href]').forEach((el) => {
    el.setAttribute('href', resolveUrl(el.getAttribute('href'), baseUrl))
  })
  // form actions
  doc.querySelectorAll('[action]').forEach((el) => {
    el.setAttribute('action', resolveUrl(el.getAttribute('action'), baseUrl))
  })
  // inline style background-image / url() references
  doc.querySelectorAll('[style]').forEach((el) => {
    const style = el.getAttribute('style')
    if (style && style.includes('url(')) {
      el.setAttribute(
        'style',
        style.replace(/url\(["']?(.*?)["']?\)/g, (_match, p1) => {
          return `url("${resolveUrl(p1, baseUrl)}")`
        }),
      )
    }
  })
  // srcset attributes (img, source)
  doc.querySelectorAll('[srcset]').forEach((el) => {
    const srcset = el.getAttribute('srcset')
    const resolved = srcset
      .split(',')
      .map((entry) => {
        const parts = entry.trim().split(/\s+/)
        parts[0] = resolveUrl(parts[0], baseUrl)
        return parts.join(' ')
      })
      .join(', ')
    el.setAttribute('srcset', resolved)
  })
}

/**
 * Parses the fetched HTML string and extracts head resources (stylesheets,
 * scripts, inline styles) plus body content with all relative URLs resolved.
 *
 * @param {string} rawHtml
 * @param {string} sourceUrl  The URL the HTML was fetched from
 * @returns {string} Combined head resources and body innerHTML
 */
const parseHtmlContent = (rawHtml, sourceUrl) => {
  const parser = new DOMParser()
  const doc = parser.parseFromString(rawHtml, 'text/html')

  // Resolve all relative URLs in the document
  resolveRelativeUrls(doc, sourceUrl)

  // Collect <style> tags from head
  let headHtml = ''
  doc.querySelectorAll('head style').forEach((el) => {
    headHtml += el.outerHTML
  })

  // Collect <link rel="stylesheet"> tags from head
  doc.querySelectorAll('head link[rel="stylesheet"]').forEach((el) => {
    headHtml += el.outerHTML
  })

  // Collect <script> tags from head (skip inline ContextHub / CMS scripts)
  doc.querySelectorAll('head script[src]').forEach((el) => {
    headHtml += el.outerHTML
  })

  const bodyHtml = doc.body ? doc.body.innerHTML : rawHtml
  return headHtml + bodyHtml
}

const ContextReview = () => {
  const [htmlContent, setHtmlContent] = useState('')
  const [segments, setSegments] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [viewMode, setViewMode] = useState(VIEW_MODES.BOTH)
  // {sid, activeIndex, total} or null — tracks current highlight navigation
  const [highlight, setHighlight] = useState(null)
  // Bumped after innerHTML injection so the tagging effect knows the DOM is ready
  const [htmlReady, setHtmlReady] = useState(0)

  const sourceRef = useRef(null)
  const targetRef = useRef(null)
  const segmentsRef = useRef([])

  // Keep segmentsRef in sync so callbacks always see the latest value
  useEffect(() => {
    segmentsRef.current = segments
  }, [segments])

  /**
   * Applies highlights on both panels for the given SID.
   * Returns the total number of occurrences found (from the source panel,
   * or the target panel when source is not mounted).
   *
   * @param {number|string} sid
   * @param {number} activeIndex  Which occurrence to mark active
   * @param {boolean} scroll      Whether to scroll to the active occurrence
   * @returns {number} total occurrences
   */
  const applyHighlights = useCallback((sid, activeIndex, scroll) => {
    let total = 0

    if (sourceRef.current) {
      clearHighlights(sourceRef.current)
      const res = highlightBySid(sourceRef.current, sid, activeIndex)
      total = res.total
      if (scroll && res.marks[activeIndex]) {
        res.marks[activeIndex][0].scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        })
      }
    }

    if (targetRef.current) {
      clearHighlights(targetRef.current)
      const res = highlightBySid(targetRef.current, sid, activeIndex)
      if (!total) total = res.total
      if (scroll && res.marks[activeIndex]) {
        res.marks[activeIndex][0].scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        })
      }
    }

    return total
  }, [])

  const handleMessage = useCallback(
    (message) => {
      if (message.type === 'segments') {
        const incoming = message.segments ?? []
        setSegments((prev) => {
          const existingSids = new Set(prev.map((s) => s.sid))
          const newSegments = incoming.filter((s) => !existingSids.has(s.sid))
          return newSegments.length > 0 ? [...prev, ...newSegments] : prev
        })
      }

      if (message.type === 'highlight') {
        // Highlight on both panels — scroll to the first occurrence
        const total = applyHighlights(message.sid, 0, true)
        setHighlight(
          total > 0 ? {sid: message.sid, activeIndex: 0, total} : null,
        )
      }

      if (message.type === 'updateTranslation') {
        const {sid, target} = message
        setSegments((prev) =>
          prev.map((seg) => (seg.sid === sid ? {...seg, target} : seg)),
        )
        // Directly update the already-tagged element in the target panel so we
        // don't need to nuke innerHTML and re-tag everything.
        // Uses replaceTextContent to preserve child elements (e.g. <a> tags).
        if (targetRef.current && target) {
          const cleanTarget = stripSegmentTags(target)
          const spans = targetRef.current.querySelectorAll(
            `[data-context-sid="${sid}"]`,
          )
          spans.forEach((span) => {
            replaceTextContent(span, cleanTarget)
          })
        }
      }
    },
    [applyHighlights],
  )

  // Subscribe to ContextReviewChannel messages
  useEffect(() => {
    return ContextReviewChannel.onMessage(handleMessage)
  }, [handleMessage])

  // Request segments from CatTool on mount (covers case where ContextReview loads after CatTool)
  useEffect(() => {
    ContextReviewChannel.sendMessage({type: 'requestSegments'})
  }, [])

  // Fetch and parse the HTML document
  useEffect(() => {
    let cancelled = false

    const fetchHtml = async () => {
      try {
        setLoading(true)
        setError(null)
        const response = await fetch(CONTEXT_REVIEW_HTML_URL)
        if (!response.ok) {
          throw new Error(`Failed to fetch document (${response.status})`)
        }
        const rawHtml = await response.text()
        if (!cancelled) {
          setHtmlContent(parseHtmlContent(rawHtml, CONTEXT_REVIEW_HTML_URL))
        }
      } catch (e) {
        if (!cancelled) {
          setError(e.message)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    fetchHtml()

    return () => {
      cancelled = true
    }
  }, [])

  // Render the fetched HTML into panels once (or when viewMode changes,
  // since panels are conditionally mounted).  A ref tracks whether the
  // current HTML has already been injected so we never nuke innerHTML
  // just because `segments` changed.
  const htmlRenderedRef = useRef({source: '', target: ''})

  useEffect(() => {
    if (!htmlContent) return

    let injected = false

    // Source panel — inject HTML only when the content or panel changed
    if (
      sourceRef.current &&
      htmlRenderedRef.current.source !== htmlContent + viewMode
    ) {
      sourceRef.current.innerHTML = htmlContent
      htmlRenderedRef.current.source = htmlContent + viewMode
      injected = true
    }

    // Target panel — same logic
    if (
      targetRef.current &&
      htmlRenderedRef.current.target !== htmlContent + viewMode
    ) {
      targetRef.current.innerHTML = htmlContent
      htmlRenderedRef.current.target = htmlContent + viewMode
      injected = true
    }

    // Signal the tagging effect that fresh HTML is in the DOM
    if (injected) {
      setHtmlReady((prev) => prev + 1)
    }
  }, [htmlContent, viewMode])

  // When segments change, apply text replacements / SID tagging
  // incrementally — without resetting innerHTML.
  // Depends on `htmlReady` (not `htmlContent`) so it only runs after the
  // HTML-injection effect has finished writing to the DOM.
  useEffect(() => {
    if (!segments.length || !htmlReady) return

    if (targetRef.current) {
      tagSegments(targetRef.current, segments, {replaceWithTarget: true})
    }

    if (sourceRef.current) {
      tagSegments(sourceRef.current, segments)
    }
  }, [segments, htmlReady, viewMode])

  // Detect untagged nodes while scrolling and request more segments
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
        // Check if element is visible in the window viewport
        if (elRect.bottom < 0 || elRect.top > window.innerHeight) continue

        // Element is visible — check if it's in the requested region
        const midY = (elRect.top + elRect.bottom) / 2
        if (region === 'before' && midY > viewportMidY) continue
        if (region === 'after' && midY < viewportMidY) continue

        // Check if this element has tagged content (attribute on self or descendants)
        if (
          !el.hasAttribute('data-context-sid') &&
          !el.querySelector('[data-context-sid]')
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

      // Near top → check for untagged nodes in upper half
      if (scrollTop < 200 && now - lastRequestRef.before > THROTTLE_MS) {
        if (hasUntaggedNodesInViewport('before')) {
          lastRequestRef.before = now
          ContextReviewChannel.sendMessage({
            type: 'loadMoreSegments',
            where: 'before',
          })
        }
      }

      // Near bottom → check for untagged nodes in lower half
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

    return () => {
      window.removeEventListener('scroll', handleScroll)
    }
  }, [segments, viewMode])

  // Attach click listeners to both panels
  useEffect(() => {
    const sourceContainer = sourceRef.current
    const targetContainer = targetRef.current
    if (!htmlContent) return

    const handleSourceClick = (event) => {
      event.preventDefault()
      const result = findSegmentSidByClick(
        event.target,
        sourceContainer,
        segmentsRef.current,
        'source',
      )
      if (result) {
        ContextReviewChannel.sendMessage({
          type: 'segmentClicked',
          sid: result.sid,
        })
        // Highlight at the clicked occurrence — do NOT scroll
        const total = applyHighlights(result.sid, result.occurrenceIndex, false)
        setHighlight(
          total > 0
            ? {sid: result.sid, activeIndex: result.occurrenceIndex, total}
            : null,
        )
      }
    }

    const handleTargetClick = (event) => {
      event.preventDefault()
      const result = findSegmentSidByClick(
        event.target,
        targetContainer,
        segmentsRef.current,
        'target',
      )
      if (result) {
        ContextReviewChannel.sendMessage({
          type: 'segmentClicked',
          sid: result.sid,
        })
        // Highlight at the clicked occurrence — do NOT scroll
        const total = applyHighlights(result.sid, result.occurrenceIndex, false)
        setHighlight(
          total > 0
            ? {sid: result.sid, activeIndex: result.occurrenceIndex, total}
            : null,
        )
      }
    }

    if (sourceContainer) {
      sourceContainer.addEventListener('click', handleSourceClick)
    }
    if (targetContainer) {
      targetContainer.addEventListener('click', handleTargetClick)
    }

    return () => {
      if (sourceContainer) {
        sourceContainer.removeEventListener('click', handleSourceClick)
      }
      if (targetContainer) {
        targetContainer.removeEventListener('click', handleTargetClick)
      }
    }
  }, [htmlContent, viewMode])

  // --- Occurrence navigation handlers ---

  const navigateHighlight = useCallback(
    (direction) => {
      if (!highlight || highlight.total <= 1) return

      const nextIndex =
        direction === 'next'
          ? (highlight.activeIndex + 1) % highlight.total
          : (highlight.activeIndex - 1 + highlight.total) % highlight.total

      // Update the active mark in both panels and scroll to it
      ;[sourceRef, targetRef].forEach((ref) => {
        if (!ref.current) return
        const mark = setActiveHighlight(ref.current, nextIndex)
        if (mark) {
          mark.scrollIntoView({behavior: 'smooth', block: 'center'})
        }
      })

      setHighlight((prev) => ({...prev, activeIndex: nextIndex}))
    },
    [highlight],
  )

  const handlePrev = useCallback(
    () => navigateHighlight('prev'),
    [navigateHighlight],
  )
  const handleNext = useCallback(
    () => navigateHighlight('next'),
    [navigateHighlight],
  )

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
        {highlight && highlight.total > 1 && (
          <div className="context-review-nav">
            <button
              className="context-review-nav__button"
              onClick={handlePrev}
              aria-label="Previous occurrence"
            >
              <IconDown size={16} />
            </button>
            <span className="context-review-nav__counter">
              {highlight.activeIndex + 1} of {highlight.total}
            </span>
            <button
              className="context-review-nav__button"
              onClick={handleNext}
              aria-label="Next occurrence"
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
