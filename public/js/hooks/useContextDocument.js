import {useState, useEffect} from 'react'

// const CONTEXT_REVIEW_HTML_URL =
//   'https://files.sandbox.translated.com/provetta/content/launches/2025/04/04/launch_copy_of_demo/content/we-retail/language-masters/de/equipment.html'
const CONTEXT_REVIEW_HTML_URL =
  'https://s3.eu-central-1.amazonaws.com/com.matecat.site-staging/matecat_test_context/test-context-mapping.html?response-content-disposition=inline&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Security-Token=IQoJb3JpZ2luX2VjELr%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaDGV1LWNlbnRyYWwtMSJIMEYCIQCrwyOEiCmgFEnNkPPkwlDkRlXpBiTPzKvb1gbdwe32qQIhAOhk6KbzYGB8lqRxKDbvOebedJSQSDG%2BjFfZTfN7k%2BePKtwDCIP%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEQBBoMMjA4MDYwOTk1Mjc2IgxgFo2tC7JqSxZGEF8qsAPPqkKiyMrTj%2FVoiVa7266Yvfo%2F6MnN1NADBsAYSPUS%2BzO9DHIL4Mh%2FSks4TmL7tAIEZcdDCrQyIEJVkq4jTnXS1S%2FLzpu3Q3usZ9g0zX7Q%2Fr%2B%2BS6JVMQlU69Ay8K0KcrkuCrEa6YSC7nbVs72nguvVM9eu2xwb4rO%2BRBQZKr9crnkw7jSiGxUBBFGKOq4ojazy5tdtM7Vqkn%2FgHtdqbMqxo%2BvvOq%2FGrlnzEhoTyOBnOIMfVs%2FeQCTxSAxsyMWFN5ty6YnifVRcL9%2BpinjICAIdRUFaRD29xGgOxlkxgovIB5MaD%2BfPHK%2B8ZMoO5dFy1tQjXQWInM%2BkGaEU0QjlWJxOjAv4YCDnJQUHOkUONpN9eAKjy%2Bc07EDGmJowGTiATUT7%2B0G0DyCnpHTtrlZ83yX45rtVQdl9PxdBhl7X6ABdGzvdGPOFjD0mU7UQqOWVtPV%2BoQ12Ye1KbDJlf6UCyG8%2FLGnJNjvFio7UWdWTh93bhESO8guTQDM2IkvO13K%2FSELOFoUCq3pURjgYswPWMC5fHdY8lRl5IMTrSOu5wzqy5QFH%2FMVnL5dZdI1nkcqspd4w9Y2%2BzgY6tgIsgsvQk3Z0ToRv%2FCeqxvNfppBa3L0BLCyMr14hI%2FeyOee2qfLeHtDS69OaChw2J%2BFsWfxBIPvD5o5BGQMbUInpz5cRi3ydtaUhBmzk%2F2ieicB6i9RX7wp3oppVBsdTcY%2FffR7tKQxeuHOvR2%2BlicmN%2FqzLQazBxuUfMZ8h6e%2BquUHjMZzKXXbtfU3c4IaNvi14Vaxc34YjTBCF7dYdBgUDXpodXj7uBGTLDBoLtuAXVRDS5JFtiH%2FhR%2FGNebjtRXCm0pkCv9zo2rPYYM6eSeMtLCv2WmtnToaxHBhPOWdHZOCZArN5KTXzv2DuVyaQIPLaNVmDZGItzg%2BOl%2FIC9UM9YazNZZR%2FN38ytTka6YB%2Bel2ND2Ce35tAXDDBYSt4lkO2%2F8l76jn1ULF0BHSb%2B4apxiLJYgUK&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=ASIATA4LG23GNOQ42CFG%2F20260403%2Feu-central-1%2Fs3%2Faws4_request&X-Amz-Date=20260403T092648Z&X-Amz-Expires=43200&X-Amz-SignedHeaders=host&X-Amz-Signature=f2f173dcd6ed60de09146ce8917627d5641cf9e2043e3a1b122cf86e392a165b'

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
 * Fetches and parses an HTML document for the ContextReview panel.
 * Re-fetches when `url` changes. Falls back to CONTEXT_REVIEW_HTML_URL when
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
    const resolvedUrl = url || CONTEXT_REVIEW_HTML_URL

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
