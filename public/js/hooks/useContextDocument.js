import {useState, useEffect} from 'react'

const htmlCache = new Map()

/**
 * Resolves a potentially relative URL against a base URL string.
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
 * Resolves all relative URLs inside a parsed DOM tree.
 * @param {Document} doc
 * @param {string} baseUrl
 */
const resolveRelativeUrls = (doc, baseUrl) => {
  doc.querySelectorAll('[src],[href],[action],[style],[srcset]').forEach((el) => {
    if (el.hasAttribute('src')) {
      el.setAttribute('src', resolveUrl(el.getAttribute('src'), baseUrl))
    }
    if (el.hasAttribute('href')) {
      el.setAttribute('href', resolveUrl(el.getAttribute('href'), baseUrl))
    }
    if (el.hasAttribute('action')) {
      el.setAttribute('action', resolveUrl(el.getAttribute('action'), baseUrl))
    }
    if (el.hasAttribute('style')) {
      const style = el.getAttribute('style')
      if (style.includes('url(')) {
        el.setAttribute(
          'style',
          style.replace(/url\(["']?(.*?)["']?\)/g, (_match, p1) => {
            return `url("${resolveUrl(p1, baseUrl)}")`
          }),
        )
      }
    }
    if (el.hasAttribute('srcset')) {
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
    }
  })
}

/**
 * Parses the fetched HTML string and extracts head resources plus body content.
 * @param {string} rawHtml
 * @param {string} sourceUrl
 * @returns {string}
 */
const parseHtmlContent = (rawHtml, sourceUrl) => {
  const parser = new DOMParser()
  const doc = parser.parseFromString(rawHtml, 'text/html')
  resolveRelativeUrls(doc, sourceUrl)
  let headHtml = ''
  doc.querySelectorAll('head style').forEach((el) => {
    headHtml += el.outerHTML
  })
  doc.querySelectorAll('head link[rel="stylesheet"]').forEach((el) => {
    headHtml += el.outerHTML
  })
  doc.querySelectorAll('script').forEach((el) => el.remove())

  // Phantom overlay suppression is handled at render time by CSS rules in
  // LivePreviewPanel.js (SHADOW_STYLES): role=dialog/alertdialog with no content
  // get pointer-events:none + visibility:hidden, and inline position:fixed elements
  // with no content are also suppressed. Class-based overlays are caught by
  // suppressClickTraps() after stylesheets load. No synchronous DOM scan needed here.

  const bodyHtml = doc.body ? doc.body.innerHTML : rawHtml
  return headHtml + bodyHtml
}

/**
 * Fetches and parses an HTML document for the ContextPreview panel.
 * Re-fetches when `url` changes. Does nothing when `url` is null.
 *
 * @param {string|null} url  The URL to fetch, or null to skip fetching.
 * @returns {{htmlContent: string, loading: boolean, error: string|null}}
 */
const useContextDocument = (url) => {
  const [htmlContent, setHtmlContent] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!url) {
      setHtmlContent('')
      setLoading(false)
      setError(null)
      return
    }
    let cancelled = false

    if (htmlCache.has(url)) {
      setHtmlContent(htmlCache.get(url))
      setLoading(false)
      setError(null)
      return
    }

    const fetchHtml = async () => {
      try {
        setLoading(true)
        setError(null)
        const response = await fetch(url)
        if (!response.ok) {
          throw new Error(`Failed to fetch document (${response.status})`)
        }
        const rawHtml = await response.text()
        if (!cancelled) {
          const parsed = parseHtmlContent(rawHtml, url)
          htmlCache.set(url, parsed)
          setHtmlContent(parsed)
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
  }, [url])

  return {htmlContent, loading, error}
}

export default useContextDocument
