// ============================================================================
// Helper functions copied from useContextDocument.js for testing
// These mirror the implementation and are tested in isolation
// ============================================================================

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

// ============================================================================
// Tests
// ============================================================================

describe('resolveUrl', () => {
  const baseUrl = 'https://example.com/path/to/page.html'

  test('returns falsy input unchanged', () => {
    expect(resolveUrl(null, baseUrl)).toBe(null)
    expect(resolveUrl(undefined, baseUrl)).toBe(undefined)
    expect(resolveUrl('', baseUrl)).toBe('')
  })

  test('returns data: URL unchanged', () => {
    const dataUrl = 'data:image/png;base64,iVBORw0KGgo='
    expect(resolveUrl(dataUrl, baseUrl)).toBe(dataUrl)
  })

  test('returns hash URL unchanged', () => {
    expect(resolveUrl('#section', baseUrl)).toBe('#section')
    expect(resolveUrl('#', baseUrl)).toBe('#')
  })

  test('resolves relative URL against base', () => {
    expect(resolveUrl('image.png', baseUrl)).toBe(
      'https://example.com/path/to/image.png',
    )
    expect(resolveUrl('./image.png', baseUrl)).toBe(
      'https://example.com/path/to/image.png',
    )
    expect(resolveUrl('../image.png', baseUrl)).toBe(
      'https://example.com/path/image.png',
    )
  })

  test('returns absolute URL unchanged', () => {
    const absoluteUrl = 'https://cdn.example.com/image.png'
    expect(resolveUrl(absoluteUrl, baseUrl)).toBe(absoluteUrl)
  })

  test('resolves protocol-relative URL', () => {
    expect(resolveUrl('//cdn.example.com/image.png', baseUrl)).toBe(
      'https://cdn.example.com/image.png',
    )
  })

  test('handles invalid URL by treating as relative', () => {
    // Invalid URL that cannot be parsed — URL constructor treats it as relative
    const invalidUrl = 'ht!tp://[invalid'
    const result = resolveUrl(invalidUrl, baseUrl)
    // Should resolve as relative path, not throw
    expect(result).toBeTruthy()
    expect(result).toContain('example.com')
  })
})

describe('resolveRelativeUrls', () => {
  const baseUrl = 'https://example.com/docs/'

  test('resolves src attributes', () => {
    const html = '<img src="image.png" /><script src="script.js"></script>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const img = doc.querySelector('img')
    expect(img.getAttribute('src')).toBe('https://example.com/docs/image.png')

    const script = doc.querySelector('script')
    expect(script.getAttribute('src')).toBe('https://example.com/docs/script.js')
  })

  test('resolves href attributes', () => {
    const html = '<a href="page.html"></a><link href="style.css" />'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const link = doc.querySelector('a')
    expect(link.getAttribute('href')).toBe('https://example.com/docs/page.html')

    const stylesheet = doc.querySelector('link')
    expect(stylesheet.getAttribute('href')).toBe(
      'https://example.com/docs/style.css',
    )
  })

  test('resolves action attributes', () => {
    const html = '<form action="submit.php"></form>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const form = doc.querySelector('form')
    expect(form.getAttribute('action')).toBe(
      'https://example.com/docs/submit.php',
    )
  })

  test('resolves url() in style attributes', () => {
    const html =
      '<div style="background: url(bg.png); border-image: url(\'border.png\')"></div>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const div = doc.querySelector('div')
    const style = div.getAttribute('style')
    expect(style).toContain('url("https://example.com/docs/bg.png")')
    expect(style).toContain('url("https://example.com/docs/border.png")')
  })

  test('handles style with url() without quotes', () => {
    const html = '<div style="background: url(image.png)"></div>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const div = doc.querySelector('div')
    const style = div.getAttribute('style')
    expect(style).toContain('url("https://example.com/docs/image.png")')
  })

  test('resolves srcset attributes', () => {
    const html =
      '<img srcset="small.png 1x, large.png 2x, huge.png 3x" />'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const img = doc.querySelector('img')
    const srcset = img.getAttribute('srcset')
    expect(srcset).toContain('https://example.com/docs/small.png 1x')
    expect(srcset).toContain('https://example.com/docs/large.png 2x')
    expect(srcset).toContain('https://example.com/docs/huge.png 3x')
  })

  test('preserves data: and hash URLs', () => {
    const html =
      '<img src="data:image/png;base64,abc" /><a href="#anchor"></a>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const img = doc.querySelector('img')
    expect(img.getAttribute('src')).toBe('data:image/png;base64,abc')

    const link = doc.querySelector('a')
    expect(link.getAttribute('href')).toBe('#anchor')
  })

  test('handles elements without src/href/action/style', () => {
    const html = '<div class="container"><p>Text</p></div>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    // Should not throw
    expect(() => resolveRelativeUrls(doc, baseUrl)).not.toThrow()
  })

  test('handles style attribute without url()', () => {
    const html = '<div style="color: red; font-size: 14px;"></div>'
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    resolveRelativeUrls(doc, baseUrl)

    const div = doc.querySelector('div')
    expect(div.getAttribute('style')).toBe('color: red; font-size: 14px;')
  })
})

describe('parseHtmlContent', () => {
  const sourceUrl = 'https://example.com/document.html'

  test('extracts head styles and returns combined output', () => {
    const html = `
      <html>
        <head>
          <style>body { color: red; }</style>
        </head>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('<style>body { color: red; }</style>')
    expect(result).toContain('<p>Content</p>')
  })

  test('extracts head link[rel="stylesheet"]', () => {
    const html = `
      <html>
        <head>
          <link rel="stylesheet" href="style.css" />
        </head>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('link')
    expect(result).toContain('rel="stylesheet"')
    expect(result).toContain('<p>Content</p>')
  })

  test('extracts head script[src]', () => {
    const html = `
      <html>
        <head>
          <script src="app.js"></script>
        </head>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('<script')
    expect(result).toContain('src=')
    expect(result).toContain('<p>Content</p>')
  })

  test('resolves relative URLs in extracted head resources', () => {
    const html = `
      <html>
        <head>
          <link rel="stylesheet" href="style.css" />
          <script src="app.js"></script>
        </head>
        <body>
          <img src="image.png" />
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('https://example.com/style.css')
    expect(result).toContain('https://example.com/app.js')
    expect(result).toContain('https://example.com/image.png')
  })

  test('handles document with no head', () => {
    const html = `
      <html>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('<p>Content</p>')
  })

  test('handles document with no body', () => {
    const html = `
      <html>
        <head>
          <style>body { color: red; }</style>
        </head>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('<style>body { color: red; }</style>')
  })

  test('ignores script tags without src attribute', () => {
    const html = `
      <html>
        <head>
          <script>console.log('inline');</script>
          <script src="app.js"></script>
        </head>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    // Only script[src] should be extracted
    expect(result).toContain('https://example.com/app.js')
    expect(result).not.toContain("console.log('inline');")
  })

  test('ignores link tags without rel="stylesheet"', () => {
    const html = `
      <html>
        <head>
          <link rel="icon" href="favicon.ico" />
          <link rel="stylesheet" href="style.css" />
        </head>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    // Only link[rel="stylesheet"] should be extracted
    expect(result).toContain('https://example.com/style.css')
    expect(result).not.toContain('favicon.ico')
  })

  test('preserves body content exactly', () => {
    const html = `
      <html>
        <head>
          <style>body { color: red; }</style>
        </head>
        <body>
          <div class="container">
            <h1>Title</h1>
            <p>Paragraph with <strong>bold</strong> text</p>
            <img src="image.png" />
          </div>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    expect(result).toContain('<h1>Title</h1>')
    expect(result).toContain('<strong>bold</strong>')
    expect(result).toContain('https://example.com/image.png')
  })

  test('handles complex nested HTML with multiple head resources', () => {
    const html = `
      <html>
        <head>
          <style>body { margin: 0; }</style>
          <style>p { color: blue; }</style>
          <link rel="stylesheet" href="reset.css" />
          <link rel="stylesheet" href="theme.css" />
          <script src="vendor.js"></script>
          <script src="app.js"></script>
        </head>
        <body>
          <header><h1>Page</h1></header>
          <main><p>Content</p></main>
          <footer><p>Footer</p></footer>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    // All head resources should be present
    expect(result).toContain('<style>body { margin: 0; }</style>')
    expect(result).toContain('<style>p { color: blue; }</style>')
    expect(result).toContain('https://example.com/reset.css')
    expect(result).toContain('https://example.com/theme.css')
    expect(result).toContain('https://example.com/vendor.js')
    expect(result).toContain('https://example.com/app.js')

    // Body content should be present
    expect(result).toContain('<h1>Page</h1>')
    expect(result).toContain('<p>Content</p>')
    expect(result).toContain('<footer>')
  })

  test('resolves relative URLs in inline styles within body', () => {
    const html = `
      <html>
        <head></head>
        <body>
          <div style="background: url(bg.png)">Content</div>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    // jsdom escapes quotes in attributes as &quot;
    expect(result).toContain('https://example.com/bg.png')
  })

  test('handles malformed HTML gracefully', () => {
    const html = `
      <html>
        <head>
          <style>body { color: red; }
        </head>
        <body>
          <p>Content
        </body>
      </html>
    `

    // Should not throw
    expect(() => parseHtmlContent(html, sourceUrl)).not.toThrow()

    const result = parseHtmlContent(html, sourceUrl)
    expect(result).toBeTruthy()
  })

  test('head resources appear before body content in output', () => {
    const html = `
      <html>
        <head>
          <style>body { color: red; }</style>
        </head>
        <body>
          <p>Content</p>
        </body>
      </html>
    `

    const result = parseHtmlContent(html, sourceUrl)

    const styleIndex = result.indexOf('<style>')
    const contentIndex = result.indexOf('<p>Content</p>')

    expect(styleIndex).toBeLessThan(contentIndex)
  })
})
