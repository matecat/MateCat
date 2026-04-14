import {useState, useEffect} from 'react'

const CONTEXT_PREVIEW_HTML_URL =
  'https://files.sandbox.translated.com/provetta/content/launches/2025/04/04/launch_copy_of_demo/content/we-retail/language-masters/de/equipment.html'
// const CONTEXT_PREVIEW_HTML_URL =
//   'https://s3.eu-central-1.amazonaws.com/com.matecat.site-staging/matecat_test_context/test-context-mapping.html?response-content-disposition=inline&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Security-Token=IQoJb3JpZ2luX2VjEL7%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaDGV1LWNlbnRyYWwtMSJHMEUCIBvpnySBihSB5LWhxjLEAoRbkLX10WnQeINbEmRFQbrRAiEA7Eizr8ooyRzrey3tj%2BBI%2FSlrpsqE0JRnsav6lKrBl6cq3AMIh%2F%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FARAEGgwyMDgwNjA5OTUyNzYiDAjZdeDuF795hBBkIiqwAwY1qN1Chlzp2qEjz%2FTD%2BB6Twpoxl4TfQ9dFEl1QQJny7miW7oBfZHSGHX5mAQYsgwPbCI1ptQjxxl0kiDK7nzEHIL0FQblGIIiDcACdgPSeqCCOCzqi8s%2BcguTrnfI9xQ8ty%2BNF1f7hZFFnJSQHZNkv%2FxSP%2B%2BlYP3FS5wqWpxxV29NqokWoEG9z%2BqkQk8Uo3fSZJbOl5aO1ZwEBMGkdp4TAv1N07quDrI6hyQZUoNehlIiAoFvIg2%2BvvHrkhZ5RycpyqpMSc6Lx6KQynxj4LBe2AVPSp%2F1nk8Qerb%2FPbeTlDntiT1VZOCHjrtChtVxR%2BSE2cCnr2HnGIrhlERtUgyGK4fT%2F5pfDc6WnNxRYq83BCxqx1CRTCGX7if0DqzKaq18JPei%2B9HSOLc2XYk2XE8ZgM7cchSrFuzh5E7Ks1tmQq0wM%2Bx6GmETPiOGOd2ISa2O6ZQYbjY9Q0U5ULdPVUVwwsJzTV7%2FU%2Fe30WF0oHGXoC914%2BP5P1i3IQNg7XljNZhOKup2%2BSbyd%2Bj7tYRiGsa%2BtYQIWDTURVA4R2S4%2BSgepmOJdAgrZduTkEDTKmKR5dTCXkL%2FOBjq3AuAoRZxKrBvzOf01mWtW4ThkkxQwd5KAKScjEtDiXfNFD9La4h10zYcSHbBPSJ4r0bZTHVfjUKGE0yjgtm%2BMKt3ovyepXvG6DtIwiLUxIyXFQBTK%2Bt6xANipLs9sXyoQthiYvORD%2BXQsk619I6G0msfK2cDd3B6TtOawSe34F%2Bh%2FH9Tt%2B0vX9ZNg9hq6%2FxXu5e9i7F%2FG0dqSV4kaIemLlaf6ZbpKlFkUvw1U7pWMBimxW1tcYwu%2Fwu7oU8OsMqZPzzqrgccv2mcUnRIkRt4h0WW0n9c6plEc2iqsnxqsHqF4hq5Z26O3b%2B7hFVPslCqzyxV5y%2BwATbrrJDZUAZMIjErualNKW65aMx298Kgbpg%2FY%2BCgshLwzSJNAKDtYZ1x5yU40bu3d9Ef7rEV4hHUaj6diug4PA8Ez&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=ASIATA4LG23GOPD3YQMM%2F20260403%2Feu-central-1%2Fs3%2Faws4_request&X-Amz-Date=20260403T140242Z&X-Amz-Expires=43200&X-Amz-SignedHeaders=host&X-Amz-Signature=d2c4b7d98ae16cc65dedfd4367211ba4fb09b2433a2e6e299e2b873a7e8e8acd'

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
  doc.querySelectorAll('[src]').forEach((el) => {
    el.setAttribute('src', resolveUrl(el.getAttribute('src'), baseUrl))
  })
  doc.querySelectorAll('[href]').forEach((el) => {
    el.setAttribute('href', resolveUrl(el.getAttribute('href'), baseUrl))
  })
  doc.querySelectorAll('[action]').forEach((el) => {
    el.setAttribute('action', resolveUrl(el.getAttribute('action'), baseUrl))
  })
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
  doc.querySelectorAll('head script[src]').forEach((el) => {
    headHtml += el.outerHTML
  })
  const bodyHtml = doc.body ? doc.body.innerHTML : rawHtml
  return headHtml + bodyHtml
}

/**
 * Fetches and parses an HTML document for the ContextPreview panel.
 * Re-fetches when `url` changes. Falls back to CONTEXT_PREVIEW_HTML_URL when
 * url is null or undefined.
 *
 * @param {string|null} url  The URL to fetch, or null to use the hardcoded fallback.
 * @returns {{htmlContent: string, loading: boolean, error: string|null}}
 */
const useContextDocument = (url) => {
  const [htmlContent, setHtmlContent] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let cancelled = false
    const resolvedUrl = url || CONTEXT_PREVIEW_HTML_URL

    const fetchHtml = async () => {
      try {
        setLoading(true)
        setError(null)
        const response = await fetch(resolvedUrl)
        if (!response.ok) {
          throw new Error(`Failed to fetch document (${response.status})`)
        }
        const rawHtml = await response.text()
        if (!cancelled) {
          setHtmlContent(parseHtmlContent(rawHtml, resolvedUrl))
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
