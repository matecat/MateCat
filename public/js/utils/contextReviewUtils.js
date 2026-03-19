const HIGHLIGHT_CLASS = 'context-review-highlight'
const HIGHLIGHT_ACTIVE_CLASS = 'context-review-highlight--active'
const SEGMENT_SID_ATTR = 'data-context-sid'

/**
 * Decodes HTML entities in a string (e.g. `&amp;` → `&`, `&lt;` → `<`).
 * Uses a temporary textarea element so the browser handles all entities.
 *
 * @param {string} text
 * @returns {string}
 */
const decodeHtmlEntities = (text) => {
  if (!text) return text
  const textarea = document.createElement('textarea')
  textarea.innerHTML = text
  return textarea.value
}

/**
 * Replaces visible text inside an element while preserving its DOM structure
 * (child elements like `<a>`, `<span>`, etc. remain intact).
 *
 * Finds the best text node to hold the new text — preferring direct text
 * node children, but falling back to the first nested text node when all
 * text lives inside a child element (e.g. `<li><a>text</a></li>`).
 * This keeps the translation inside the original child element rather than
 * creating a stray text node outside it.
 *
 * @param {HTMLElement} el
 * @param {string} newText
 */
export const replaceTextContent = (el, newText) => {
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null)
  const allTextNodes = []

  while (walker.nextNode()) {
    allTextNodes.push(walker.currentNode)
  }

  if (allTextNodes.length === 0) {
    // No text nodes at all — create one as first child
    el.insertBefore(document.createTextNode(newText), el.firstChild)
    return
  }

  // Find first direct (non-whitespace-only) text node, or fall back to
  // first nested non-whitespace text node, or finally just the very first
  // text node in tree order.
  const firstMeaningfulDirect = allTextNodes.find(
    (n) => n.parentNode === el && n.nodeValue.trim(),
  )
  const firstMeaningfulNested = allTextNodes.find(
    (n) => n.parentNode !== el && n.nodeValue.trim(),
  )
  const target =
    firstMeaningfulDirect || firstMeaningfulNested || allTextNodes[0]

  // Place all new text in the chosen node; empty every other text node
  allTextNodes.forEach((node) => {
    node.nodeValue = node === target ? newText : ''
  })
}

/**
 * Clears all existing highlights from the container by unwrapping
 * <mark> elements back to their original text nodes.
 *
 * @param {HTMLElement} container
 */
export const clearHighlights = (container) => {
  const marks = container.querySelectorAll(`mark.${HIGHLIGHT_CLASS}`)
  marks.forEach((mark) => {
    const parent = mark.parentNode
    const textNode = document.createTextNode(mark.textContent)
    parent.replaceChild(textNode, mark)
    parent.normalize()
  })
}

/**
 * Builds a case-insensitive regex from searchText where whitespace
 * boundaries are flexible — any whitespace in either side matches any
 * whitespace in the other.
 *
 * Uses negative lookahead/lookbehind (`(?<!\w)` / `(?!\w)`) instead of
 * `\b` so that boundaries work correctly even when the pattern starts or
 * ends with a non-word character (e.g. parentheses in "equipment (8:17 AM)").
 * `\b` fails in that case because it requires a word↔non-word transition,
 * which doesn't exist between a non-word char and end-of-string.
 *
 * @param {string} searchText
 * @returns {RegExp}
 */
export const buildFlexibleRegex = (searchText) => {
  const decoded = decodeHtmlEntities(searchText)
  const escaped = decoded.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const tokens = escaped.split(/\s+/)
  return new RegExp('(?<!\\w)' + tokens.join('\\s+') + '(?!\\w)', 'gi')
}

/**
 * Highlights all elements in a container that have a matching
 * `data-context-sid` attribute. Wraps their text content in <mark> elements.
 *
 * Each matched `[data-context-sid]` element is one "occurrence".
 * The `activeIndex` parameter controls which occurrence gets the
 * `--active` class (defaults to 0 — the first one).
 *
 * Preserves the element's DOM structure (child elements like `<a>` stay
 * intact) — only text nodes are wrapped in `<mark>`.
 *
 * @param {HTMLElement} container
 * @param {number|string} sid - The segment ID to highlight
 * @param {number} [activeIndex=0] - Which occurrence to mark as active
 * @returns {{total: number, marks: HTMLElement[][]}} Total occurrences and
 *   grouped mark elements (one array of <mark>s per occurrence)
 */
export const highlightBySid = (container, sid, activeIndex = 0) => {
  const result = {total: 0, marks: []}
  if (!container || sid == null) return result

  const elements = container.querySelectorAll(`[${SEGMENT_SID_ATTR}="${sid}"]`)
  if (!elements.length) return result

  elements.forEach((el, elIndex) => {
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null)
    const textNodes = []
    while (walker.nextNode()) {
      textNodes.push(walker.currentNode)
    }

    const elMarks = []
    textNodes.forEach((textNode) => {
      if (!textNode.nodeValue.trim()) return
      const mark = document.createElement('mark')
      mark.className = HIGHLIGHT_CLASS

      if (elIndex === activeIndex) {
        mark.classList.add(HIGHLIGHT_ACTIVE_CLASS)
      }

      mark.textContent = textNode.nodeValue
      textNode.parentNode.replaceChild(mark, textNode)
      elMarks.push(mark)
    })

    if (elMarks.length > 0) {
      result.marks.push(elMarks)
    }
  })

  result.total = result.marks.length
  return result
}

/**
 * Updates which occurrence is visually active among already-rendered
 * highlights. Removes `--active` from all marks and adds it to the
 * marks of the occurrence at `activeIndex`.
 *
 * @param {HTMLElement} container
 * @param {number} activeIndex
 * @returns {HTMLElement|null} The first <mark> of the newly active occurrence
 */
export const setActiveHighlight = (container, activeIndex) => {
  if (!container) return null

  const allMarks = container.querySelectorAll(`mark.${HIGHLIGHT_CLASS}`)
  allMarks.forEach((m) => m.classList.remove(HIGHLIGHT_ACTIVE_CLASS))

  // Group marks by their closest [data-context-sid] ancestor to determine
  // which occurrence each mark belongs to.
  const occurrences = []
  const seen = new Set()
  allMarks.forEach((m) => {
    const ancestor = m.closest(`[${SEGMENT_SID_ATTR}]`)
    if (!ancestor) return
    if (!seen.has(ancestor)) {
      seen.add(ancestor)
      occurrences.push([])
    }
    occurrences[occurrences.length - 1].push(m)
  })

  const idx = Math.max(0, Math.min(activeIndex, occurrences.length - 1))
  if (occurrences[idx]) {
    occurrences[idx].forEach((m) => m.classList.add(HIGHLIGHT_ACTIVE_CLASS))
    return occurrences[idx][0]
  }

  return null
}

/**
 * Selector for elements that represent meaningful content blocks.
 * Used both for click resolution and for parent-level segment matching.
 */
const MEANINGFUL_SELECTOR = 'p, li, td, th, h1, h2, h3, h4, a, span, label'

/**
 * Tags for elements that represent meaningful content blocks in the document.
 * Used to resolve clicks to segment-level granularity.
 */
const MEANINGFUL_TAGS = [
  'P',
  'LI',
  'TD',
  'TH',
  'H1',
  'H2',
  'H3',
  'H4',
  'DIV',
  'A',
]

/**
 * Finds the closest meaningful parent element for a clicked node.
 *
 * @param {HTMLElement} clickedElement
 * @param {HTMLElement} container
 * @returns {HTMLElement} The meaningful parent, or the clicked element itself
 */
const findMeaningfulParent = (clickedElement, container) => {
  let target = clickedElement

  while (target && target !== container) {
    if (MEANINGFUL_TAGS.includes(target.tagName)) break
    target = target.parentElement
  }

  if (!target || target === container) {
    target = clickedElement
  }

  return target
}

/**
 * Finds the segment SID that matches the text content of a clicked element,
 * plus the occurrence index of the clicked element among all elements with
 * the same SID in the container.
 *
 * First checks for a `data-context-sid` attribute on the element or its
 * ancestors, then falls back to fuzzy-matching the text against the
 * segments list.
 *
 * @param {HTMLElement} clickedElement - The element that was clicked
 * @param {HTMLElement} container - The panel container element
 * @param {Array<{sid: number, source: string, target: string}>} segments - The segments mapping
 * @param {'source'|'target'} field - Which field to match against
 * @returns {{sid: number, occurrenceIndex: number}|null}
 */
export const findSegmentSidByClick = (
  clickedElement,
  container,
  segments,
  field,
) => {
  if (!segments || !segments.length) return null

  // 1. Check for data-context-sid on the clicked element or any ancestor
  const sidEl = clickedElement.closest(`[${SEGMENT_SID_ATTR}]`)
  if (sidEl) {
    const sid = parseInt(sidEl.getAttribute(SEGMENT_SID_ATTR), 10)
    // Determine occurrence index: find all elements with same SID and see
    // which one is (or contains) the clicked element.
    const allWithSid = container.querySelectorAll(
      `[${SEGMENT_SID_ATTR}="${sid}"]`,
    )
    let occurrenceIndex = 0
    for (let i = 0; i < allWithSid.length; i++) {
      if (allWithSid[i] === sidEl || allWithSid[i].contains(sidEl)) {
        occurrenceIndex = i
        break
      }
    }
    return {sid, occurrenceIndex}
  }

  // 2. Fallback: fuzzy-match the clicked text against segments.
  //    When multiple segments match, pick the longest one so that e.g.
  //    "Welcome to our finest equipment" wins over "Equipment" when the
  //    user clicks on the <h2> that contains both.
  const targetEl = findMeaningfulParent(clickedElement, container)
  const clickedText = targetEl.textContent.replace(/\s+/g, ' ').trim()
  if (!clickedText) return null

  let bestMatch = null

  for (const seg of segments) {
    const segText = seg[field]
    if (!segText) continue
    const regex = buildFlexibleRegex(segText)
    regex.lastIndex = 0
    if (regex.test(clickedText)) {
      if (!bestMatch || segText.length > bestMatch.source.length) {
        bestMatch = {sid: seg.sid, source: segText}
      }
    }
  }

  return bestMatch ? {sid: bestMatch.sid, occurrenceIndex: 0} : null
}

/**
 * Walks all meaningful elements in a container and tags those whose full
 * `textContent` matches a segment source.
 *
 * The key difference from the previous approach: instead of matching
 * individual text nodes in isolation, we match against the **parent element's
 * combined text**.  This ensures only elements whose full text contains
 * the entire segment get tagged.
 *
 * When the segment text is found inside a single text node within the
 * matched parent, that text node is split and the match is wrapped in a
 * `<span data-context-sid>`.  When the segment spans the parent's entire
 * text (possibly across multiple child nodes), the `data-context-sid`
 * attribute is set directly on the parent element.
 *
 * When `replaceWithTarget` is true the matched text is swapped with
 * `seg.target` (falling back to the original text when target is empty).
 *
 * Segments are processed longest-first to avoid partial replacements.
 * Already-tagged nodes are skipped to prevent double-wrapping.
 *
 * @param {HTMLElement} container - The DOM container to process (modified in place)
 * @param {Array<{sid: number, source: string, target: string}>} segments
 * @param {Object}  [options]
 * @param {boolean} [options.replaceWithTarget=false] - Substitute matched text with target
 */
export const tagSegments = (
  container,
  segments,
  {replaceWithTarget = false} = {},
) => {
  if (!container || !segments || !segments.length) return

  const sorted = [...segments]
    .filter((s) => s.source)
    .sort((a, b) => b.source.length - a.source.length)

  sorted.forEach((seg) => {
    const flexRegex = buildFlexibleRegex(seg.source)
    const decodedSource = decodeHtmlEntities(seg.source).trim()

    // Collect candidate parent elements whose full textContent matches
    const candidates = container.querySelectorAll(MEANINGFUL_SELECTOR)

    for (const el of candidates) {
      // Skip elements already tagged (or inside a tagged ancestor)
      if (el.closest(`[${SEGMENT_SID_ATTR}]`)) continue

      const fullText = el.textContent
      flexRegex.lastIndex = 0
      if (!flexRegex.test(fullText)) continue

      // Check whether the full textContent of this element is (approximately)
      // equal to the segment source — i.e. the element IS the segment.
      const normalizedFull = fullText.replace(/\s+/g, ' ').trim()
      const normalizedSource = decodedSource.replace(/\s+/g, ' ').trim()
      const isExactParent =
        normalizedFull.toLowerCase() === normalizedSource.toLowerCase()

      if (isExactParent) {
        // The element's entire text matches the segment — tag the element
        // itself instead of wrapping individual text nodes.
        el.setAttribute(SEGMENT_SID_ATTR, seg.sid)
        if (replaceWithTarget && seg.target) {
          replaceTextContent(el, decodeHtmlEntities(seg.target))
        }
        continue
      }

      // The segment is a substring of this element's text.  Walk its text
      // nodes and wrap the matching portion.  We must verify each text node
      // individually contains the full segment to avoid partial matches.
      const treeWalker = document.createTreeWalker(
        el,
        NodeFilter.SHOW_TEXT,
        null,
      )

      const matchingNodes = []
      while (treeWalker.nextNode()) {
        const textNode = treeWalker.currentNode
        if (textNode.parentNode.closest(`[${SEGMENT_SID_ATTR}]`)) continue
        const nodeRegex = buildFlexibleRegex(seg.source)
        nodeRegex.lastIndex = 0
        if (nodeRegex.test(textNode.nodeValue)) {
          matchingNodes.push(textNode)
        }
      }

      matchingNodes.forEach((textNode) => {
        const text = textNode.nodeValue
        const parent = textNode.parentNode
        const fragment = document.createDocumentFragment()

        const regex = buildFlexibleRegex(seg.source)
        let lastIndex = 0
        let match

        while ((match = regex.exec(text)) !== null) {
          const matchStart = match.index
          const matchEnd = matchStart + match[0].length

          if (matchStart > lastIndex) {
            fragment.appendChild(
              document.createTextNode(text.slice(lastIndex, matchStart)),
            )
          }

          const originalText = text.slice(matchStart, matchEnd)
          const span = document.createElement('span')
          span.setAttribute(SEGMENT_SID_ATTR, seg.sid)
          span.textContent =
            replaceWithTarget && seg.target
              ? decodeHtmlEntities(seg.target)
              : originalText
          fragment.appendChild(span)

          lastIndex = matchEnd
        }

        if (lastIndex < text.length) {
          fragment.appendChild(document.createTextNode(text.slice(lastIndex)))
        }

        if (lastIndex > 0) {
          parent.replaceChild(fragment, textNode)
        }
      })
    }
  })
}
