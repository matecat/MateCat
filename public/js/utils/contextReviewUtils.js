const HIGHLIGHT_CLASS = 'context-review-highlight'
const HIGHLIGHT_ACTIVE_CLASS = 'context-review-highlight--active'
const SEGMENT_SID_ATTR = 'data-context-sid'

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
 * boundaries are flexible — any whitespace in either side (or none at all)
 * matches any whitespace in the other.  This handles mismatches like:
 *   search "Hello World"  vs  node "Hello\nWorld"
 *   search "HelloWorld"   vs  node "Hello\nWorld"
 *
 * @param {string} searchText
 * @returns {RegExp}
 */
export const buildFlexibleRegex = (searchText) => {
  const escaped = searchText.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const tokens = escaped.split(/\s+/)
  return new RegExp(tokens.join('\\s*'), 'gi')
}

/**
 * Highlights all elements in a container that have a matching
 * `data-context-sid` attribute. Wraps their text content in <mark> elements
 * and returns the first one for scrolling.
 *
 * @param {HTMLElement} container
 * @param {number|string} sid - The segment ID to highlight
 * @returns {HTMLElement|null} The first <mark> element, or null if no match found
 */
export const highlightBySid = (container, sid) => {
  if (!container || sid == null) return null

  const elements = container.querySelectorAll(`[${SEGMENT_SID_ATTR}="${sid}"]`)
  if (!elements.length) return null

  let firstMark = null

  elements.forEach((el) => {
    const mark = document.createElement('mark')
    mark.className = HIGHLIGHT_CLASS
    mark.textContent = el.textContent

    if (!firstMark) {
      firstMark = mark
      mark.classList.add(HIGHLIGHT_ACTIVE_CLASS)
    }

    el.textContent = ''
    el.appendChild(mark)
  })

  return firstMark
}

/**
 * Tags for elements that represent meaningful content blocks in the document.
 * Used to resolve clicks to segment-level granularity.
 */
const MEANINGFUL_TAGS = ['P', 'LI', 'TD', 'TH', 'H1', 'H2', 'H3', 'H4', 'DIV']

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
 * Finds the segment SID that matches the text content of a clicked element.
 * First checks for a `data-context-sid` attribute on the element or its ancestors,
 * then falls back to fuzzy-matching the text against the segments list.
 *
 * @param {HTMLElement} clickedElement - The element that was clicked
 * @param {HTMLElement} container - The panel container element
 * @param {Array<{sid: number, source: string, target: string}>} segments - The segments mapping
 * @param {'source'|'target'} field - Which field to match against
 * @returns {number|null} The matching segment SID, or null if not found
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
    return parseInt(sidEl.getAttribute(SEGMENT_SID_ATTR), 10)
  }

  // 2. Fallback: fuzzy-match the clicked text against segments
  const targetEl = findMeaningfulParent(clickedElement, container)
  const clickedText = targetEl.textContent.replace(/\s+/g, ' ').trim()
  if (!clickedText) return null

  for (const seg of segments) {
    const segText = seg[field]
    if (!segText) continue
    const regex = buildFlexibleRegex(segText)
    regex.lastIndex = 0
    if (regex.test(clickedText)) {
      return seg.sid
    }
  }

  return null
}

/**
 * Walks all text nodes in a container, matches them against segment source texts,
 * and wraps matches in `<span data-context-sid="...">` elements.
 *
 * When `replaceWithTarget` is true the matched text is swapped with
 * `seg.target` (falling back to the original text when target is empty).
 * When false the original text is always kept.
 *
 * Segments are processed longest-first to avoid partial replacements.
 * Already-tagged nodes are skipped to prevent double-wrapping.
 *
 * @param {HTMLElement} container - The DOM container to process (modified in place)
 * @param {Array<{sid: number, source: string, target: string}>} segments
 * @param {Object}  [options]
 * @param {boolean} [options.replaceWithTarget=false] - Substitute matched text with target
 */
export const tagSegments = (container, segments, {replaceWithTarget = false} = {}) => {
  if (!container || !segments || !segments.length) return

  const sorted = [...segments]
    .filter((s) => s.source)
    .sort((a, b) => b.source.length - a.source.length)

  sorted.forEach((seg) => {
    const flexRegex = buildFlexibleRegex(seg.source)
    const treeWalker = document.createTreeWalker(
      container,
      NodeFilter.SHOW_TEXT,
      null,
    )

    const matchingNodes = []
    while (treeWalker.nextNode()) {
      const textNode = treeWalker.currentNode
      // Skip nodes already tagged
      if (textNode.parentNode.closest(`[${SEGMENT_SID_ATTR}]`)) continue
      flexRegex.lastIndex = 0
      if (flexRegex.test(textNode.nodeValue)) {
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
          replaceWithTarget && seg.target ? seg.target : originalText
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
  })
}
