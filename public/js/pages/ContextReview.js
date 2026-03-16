import React, {useEffect, useRef, useState, useCallback} from 'react'
import {mountPage} from './mountPage'
import useContextReviewChannel from '../hooks/useContextReviewChannel'
import {
  clearHighlights,
  highlightBySid,
  findSegmentSidByClick,
  tagSegments,
} from '../utils/contextReviewUtils'
import sampleHtml from '../../../sample-context-review.html'

/**
 * Parses the sample HTML string and extracts style + body content.
 *
 * @param {string} rawHtml
 * @returns {string} Combined style tags and body innerHTML
 */
const parseHtmlContent = (rawHtml) => {
  const parser = new DOMParser()
  const doc = parser.parseFromString(rawHtml, 'text/html')

  const styles = doc.querySelectorAll('head style')
  let styleHtml = ''
  styles.forEach((style) => {
    styleHtml += style.outerHTML
  })

  const bodyHtml = doc.body ? doc.body.innerHTML : rawHtml
  return styleHtml + bodyHtml
}

const ContextReview = () => {
  const [htmlContent, setHtmlContent] = useState('')
  const [segments, setSegments] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const sourceRef = useRef(null)
  const targetRef = useRef(null)
  const segmentsRef = useRef([])

  // Keep segmentsRef in sync so callbacks always see the latest value
  useEffect(() => {
    segmentsRef.current = segments
  }, [segments])

  const handleMessage = useCallback((message) => {
    if (message.type === 'segments') {
      const incoming = message.segments ?? []
      setSegments((prev) => {
        const existingSids = new Set(prev.map((s) => s.sid))
        const newSegments = incoming.filter((s) => !existingSids.has(s.sid))
        return newSegments.length > 0 ? [...prev, ...newSegments] : prev
      })
    }

    if (message.type === 'highlight') {
      // Highlight on both panels using data-context-sid attribute
      if (sourceRef.current) {
        clearHighlights(sourceRef.current)
        const firstMatch = highlightBySid(sourceRef.current, message.sid)
        if (firstMatch) {
          firstMatch.scrollIntoView({behavior: 'smooth', block: 'center'})
        }
      }
      if (targetRef.current) {
        clearHighlights(targetRef.current)
        const firstMatch = highlightBySid(targetRef.current, message.sid)
        if (firstMatch) {
          firstMatch.scrollIntoView({behavior: 'smooth', block: 'center'})
        }
      }
    }

    if (message.type === 'updateTranslation') {
      setSegments((prev) =>
        prev.map((seg) =>
          seg.sid === message.sid ? {...seg, target: message.target} : seg,
        ),
      )
    }
  }, [])

  const {sendMessage} = useContextReviewChannel({onMessage: handleMessage})

  // Parse the imported HTML string
  useEffect(() => {
    try {
      setLoading(true)
      setError(null)
      setHtmlContent(parseHtmlContent(sampleHtml))
    } catch (e) {
      setError(e.message)
    } finally {
      setLoading(false)
    }
  }, [])

  // When segments change, apply text replacements to the target panel
  // and tag source panel nodes with SID attributes
  useEffect(() => {
    if (!segments.length || !htmlContent) return

    // Re-render fresh HTML in target panel then apply replacements
    if (targetRef.current) {
      targetRef.current.innerHTML = htmlContent
      tagSegments(targetRef.current, segments, {replaceWithTarget: true})
    }

    // Tag source panel nodes with SID attributes for click resolution
    if (sourceRef.current) {
      sourceRef.current.innerHTML = htmlContent
      tagSegments(sourceRef.current, segments)
    }
  }, [segments, htmlContent])

  // Attach click listeners to both panels
  useEffect(() => {
    const sourceContainer = sourceRef.current
    const targetContainer = targetRef.current
    if (!htmlContent) return

    const handleSourceClick = (event) => {
      const sid = findSegmentSidByClick(
        event.target,
        sourceContainer,
        segmentsRef.current,
        'source',
      )
      if (sid != null) {
        sendMessage({type: 'segmentClicked', sid})
      }
    }

    const handleTargetClick = (event) => {
      const sid = findSegmentSidByClick(
        event.target,
        targetContainer,
        segmentsRef.current,
        'target',
      )
      if (sid != null) {
        sendMessage({type: 'segmentClicked', sid})
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
  }, [htmlContent, sendMessage])

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
      <div className="context-review-panels">
        <div className="context-review-panel">
          <div className="context-review-panel-header">Source</div>
          <div
            ref={sourceRef}
            className="context-review-content"
            dangerouslySetInnerHTML={{__html: htmlContent}}
          />
        </div>
        <div className="context-review-divider" />
        <div className="context-review-panel">
          <div className="context-review-panel-header">Translation</div>
          <div
            ref={targetRef}
            className="context-review-content"
            dangerouslySetInnerHTML={{__html: htmlContent}}
          />
        </div>
      </div>
    </div>
  )
}

export default ContextReview

mountPage({
  Component: ContextReview,
  rootElement: document.getElementsByClassName('context-review__page')[0],
})
