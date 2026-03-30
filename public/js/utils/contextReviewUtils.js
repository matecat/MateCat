import {
  excludeSomeTagsTransformToText,
  removeTagsFromText,
} from '../components/segments/utils/DraftMatecatUtils/tagUtils'

const containerMaps = new WeakMap()

const HIGHLIGHT_CLASS = 'context-review-highlight'
const HIGHLIGHT_ACTIVE_CLASS = 'context-review-highlight--active'
const SEGMENT_SID_ATTR = 'data-context-sid'
const SEGMENT_SIDS_ATTR = 'data-context-sids'

/**
 * Returns the list of SIDs associated with an element, or [] if none.
 * @param {HTMLElement} el
 * @returns {number[]}
 */
export const getSidsFromElement = (el) => {
  const raw = el.getAttribute(SEGMENT_SIDS_ATTR)
  if (!raw) return []
  return raw.split(',').map(Number).filter(Boolean)
}

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
 * Selector for block-level elements that correspond to XLIFF trans-units.
 * Okapi treats these as segment boundaries; inline elements (a, span,
 * strong, etc.) become <g> tags *inside* a trans-unit and must NOT appear
 * here.
 *
 * `div` is included because Okapi extracts text from <div> elements that
 * contain only inline content (e.g. `<div><a>Surfing</a></div>`).
 * Outer wrapper <div> elements are not a problem because we skip elements
 * that already contain tagged descendants (longest-first processing).
 */
const BLOCK_SELECTOR = 'p, h1, h2, h3, h4, h5, h6, li, td, th, div, title'

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
 * Finds all segment SIDs that match the text content of a clicked element,
 * plus the index of the clicked node in the container's node map.
 *
 * Strategy 1: Check for a `data-context-sids` attribute on the element or
 * an ancestor, then read all SIDs from that attribute.
 *
 * Strategy 2 (fallback): Fuzzy-match the clicked text against all segments
 * and collect every SID whose source/target matches.
 *
 * @param {HTMLElement} clickedElement - The element that was clicked
 * @param {HTMLElement} container - The panel container element
 * @param {Array<{sid: number, source: string, target: string}>} segments - The segments mapping
 * @param {'source'|'target'} field - Which field to match against
 * @returns {{sids: number[], nodeIndex: number}|null}
 */
export const findSegmentSidsByClick = (
  clickedElement,
  container,
  segments,
  field,
) => {
  if (!segments || !segments.length) return null

  // Strategy 1: data-context-sids attribute on clicked element or ancestor
  const sidEl = clickedElement.closest(`[${SEGMENT_SIDS_ATTR}]`)
  if (sidEl) {
    const sids = getSidsFromElement(sidEl)
    const map = getSegmentNodeMap(container)
    let nodeIndex = map ? map.nodes.indexOf(sidEl) : 0
    if (nodeIndex === -1) nodeIndex = 0
    return {sids, nodeIndex}
  }

  // Strategy 2: fuzzy text match (fallback for untagged elements)
  const targetEl = findMeaningfulParent(clickedElement, container)
  const clickedText = targetEl.textContent.replace(/\s+/g, ' ').trim()
  if (!clickedText) return null

  const matchingSids = []
  for (const seg of segments) {
    const segText = seg[field]
    if (!segText) continue
    const regex = buildFlexibleRegex(segText)
    regex.lastIndex = 0
    if (regex.test(clickedText)) matchingSids.push(seg.sid)
  }

  return matchingSids.length > 0 ? {sids: matchingSids, nodeIndex: 0} : null
}

/**
 * Backward-compatible wrapper around findSegmentSidsByClick.
 * Returns the first SID and the node index as occurrenceIndex.
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
  const result = findSegmentSidsByClick(
    clickedElement,
    container,
    segments,
    field,
  )
  if (!result) return null
  return {sid: result.sids[0], occurrenceIndex: result.nodeIndex}
}

/**
 * Strips XLIFF inline tags (`<g>`, `<x/>`, `<ph>`, etc.) and MateCat
 * whitespace placeholders (`##$_0A$##`, `##$_A0$##`, …) from a segment
 * string, then collapses whitespace.
 *
 * The result is the "visible text" that should appear in the rendered HTML.
 *
 * @param {string} text  Raw segment source or target from MateCat
 * @returns {string}
 */
export const stripSegmentTags = (text) => {
  if (!text) return ''
  return decodeHtmlEntities(
    removeTagsFromText(
      excludeSomeTagsTransformToText(text, ['g', 'gCl', 'gSc', 'bx', 'ex', 'x'])
        .replace(/##\$_[^$]+\$##/g, ' ')
        .replace(/\s+/g, ' ')
        .trim(),
    ),
  )
}

/**
 * Builds and caches the two-way segment↔node lookup maps for a container.
 * Must be called after tagSegments has finished tagging the container.
 *
 * @param {HTMLElement} container
 * @returns {{ sidToNodeIndices: Map<number, number[]>, nodeIndexToSids: Map<number, number[]>, nodes: HTMLElement[] }}
 */
export const buildSegmentNodeMap = (container) => {
  const nodes = Array.from(container.querySelectorAll(`[${SEGMENT_SIDS_ATTR}]`))
  const sidToNodeIndices = new Map()
  const nodeIndexToSids = new Map()

  nodes.forEach((el, nodeIndex) => {
    const sids = getSidsFromElement(el)
    nodeIndexToSids.set(nodeIndex, sids)
    sids.forEach((sid) => {
      if (!sidToNodeIndices.has(sid)) sidToNodeIndices.set(sid, [])
      sidToNodeIndices.get(sid).push(nodeIndex)
    })
  })

  const map = {sidToNodeIndices, nodeIndexToSids, nodes}
  containerMaps.set(container, map)
  return map
}

/**
 * Returns the cached map for a container, or null if not yet built.
 * @param {HTMLElement} container
 * @returns {{ sidToNodeIndices: Map<number, number[]>, nodeIndexToSids: Map<number, number[]>, nodes: HTMLElement[] }|null}
 */
export const getSegmentNodeMap = (container) =>
  containerMaps.get(container) ?? null

/**
 * Walks all block-level elements in a container and tags those whose
 * `textContent` matches a segment source.
 *
 * Matching logic follows Okapi's HTML segmentation rules:
 * - Block-level elements (`<p>`, `<h1>`–`<h6>`, `<li>`, `<td>`, `<th>`,
 *   `<title>`) correspond to XLIFF trans-units — each one IS a segment.
 * - Inline elements (`<a>`, `<strong>`, `<span>`, etc.) are encoded as
 *   `<g>` tags *inside* a trans-unit and do NOT create new segments.
 *
 * Before comparison the segment source is normalised: XLIFF inline tags
 * and MateCat whitespace placeholders are stripped, then whitespace is
 * collapsed.  The block element's `textContent` is normalised the same
 * way.  Matching is case-insensitive.
 *
 * Both the DOM elements (returned by `querySelectorAll` in document order)
 * and the segments (sorted by SID) follow the same top-to-bottom order
 * that Okapi uses when extracting trans-units from HTML.  The algorithm
 * exploits this: it walks elements in document order and, for each one,
 * consumes the first not-yet-used segment whose normalised source matches.
 * This correctly handles duplicate source text (e.g. "Equipment" appearing
 * in both `<title>` and `<strong>`) by assigning each occurrence to the
 * right segment based on position.
 *
 * When `replaceWithTarget` is true the matched element's visible text is
 * replaced with the normalised `seg.target`.
 *
 * Already-tagged elements are skipped to prevent double-tagging on
 * incremental updates.
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

  // Pre-compute normalised source for each segment, sorted by SID so
  // their order matches the document order of the HTML elements.
  const prepared = segments
    .map((seg) => ({
      ...seg,
      normSource: stripSegmentTags(seg.source),
      normTarget: stripSegmentTags(seg.target),
    }))
    .filter((seg) => seg.normSource)
    .sort((a, b) => a.sid - b.sid)
  if (!prepared.length) return

  // Collect SIDs that are already tagged in the DOM from previous calls
  // (segments arrive incrementally as the user scrolls).  These segments
  // must be marked as consumed so they are not re-assigned to a different
  // DOM element with the same text.
  const alreadyTagged = new Set()
  container.querySelectorAll(`[${SEGMENT_SIDS_ATTR}]`).forEach((el) => {
    getSidsFromElement(el).forEach((sid) => alreadyTagged.add(sid))
  })

  // Track which segments have been consumed (by index in `prepared`).
  // Pre-mark segments whose SID is already in the DOM.
  const used = new Set()
  for (let i = 0; i < prepared.length; i++) {
    if (alreadyTagged.has(prepared[i].sid)) {
      used.add(i)
    }
  }

  const candidates = container.querySelectorAll(BLOCK_SELECTOR)

  // Cache parsed SIDs per element to avoid repeated getAttribute + split
  // calls inside the inner loops.
  const elSidsCache = new Map()
  const getCachedSids = (el) => {
    if (!elSidsCache.has(el)) elSidsCache.set(el, getSidsFromElement(el))
    return elSidsCache.get(el)
  }
  const appendSid = (el, sid) => {
    const existing = getCachedSids(el)
    const updated = [...existing, sid]
    el.setAttribute(SEGMENT_SIDS_ATTR, updated.join(','))
    el.setAttribute(SEGMENT_SID_ATTR, String(updated[0]))
    elSidsCache.set(el, updated)
  }

  // Pass 1 — positional: each untagged element consumes the first unused
  // segment whose normalised source is an exact match.  This preserves
  // positional pairing when duplicate source texts appear in multiple
  // elements (e.g. two <p>Equipment</p> nodes).
  for (const el of candidates) {
    // Skip elements that contain an already-tagged descendant — the
    // descendant is the more specific match.
    if (el.querySelector(`[${SEGMENT_SIDS_ATTR}]`)) continue

    const elText = el.textContent.replace(/\s+/g, ' ').trim()
    if (!elText) continue

    const elTextLower = elText.toLowerCase()

    for (let i = 0; i < prepared.length; i++) {
      if (used.has(i)) continue
      if (getCachedSids(el).includes(prepared[i].sid)) continue
      if (elTextLower === prepared[i].normSource.toLowerCase()) {
        appendSid(el, prepared[i].sid)
        if (replaceWithTarget && prepared[i].target) {
          replaceTextContent(el, stripSegmentTags(prepared[i].target))
        }
        used.add(i)
        break // one new segment per element per pass (positional pairing)
      }
    }
  }

  // Pass 2 — N:N append: remaining unused segments are checked against
  // already-tagged elements.  If the segment's normalised source matches
  // the element's text and that SID is not yet on the element, append it.
  // Note: replaceWithTarget is NOT applied here — the element's text was
  // already replaced by the primary segment in Pass 1. Pass 2 only adds
  // additional SID associations for N:N tracking.
  for (const el of candidates) {
    if (!el.hasAttribute(SEGMENT_SIDS_ATTR)) continue

    const elText = el.textContent.replace(/\s+/g, ' ').trim()
    if (!elText) continue

    const elTextLower = elText.toLowerCase()

    for (let i = 0; i < prepared.length; i++) {
      if (used.has(i)) continue
      if (getCachedSids(el).includes(prepared[i].sid)) continue
      if (elTextLower === prepared[i].normSource.toLowerCase()) {
        appendSid(el, prepared[i].sid)
        used.add(i)
      }
    }
  }

  buildSegmentNodeMap(container)
}
