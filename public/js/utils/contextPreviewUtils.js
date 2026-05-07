import {
  excludeSomeTagsTransformToText,
  removeTagsFromText,
} from '../components/segments/utils/DraftMatecatUtils/tagUtils'
import {findElementByMetadata} from './contextPreviewLookup'

const containerMaps = new WeakMap()

const HIGHLIGHT_CLASS = 'context-preview-highlight'
const HIGHLIGHT_ACTIVE_CLASS = 'context-preview-highlight--active'
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
 * Attempts to replace the visible text of a node with its translated content.
 *
 * Only performs the replacement when every segment linked to the node has a
 * non-empty target AND all targets are identical (after stripping XLIFF tags).
 *
 * @param {HTMLElement} el
 * @param {Array<{sid: number, source: string, target: string}>} segments
 * @returns {'ok'|'mismatch'|'no-target'}
 */
export const updateNodeTranslation = (el, segments) => {
  const sids = getSidsFromElement(el)
  if (!sids.length) return 'no-target'

  const sidSet = new Set(sids)
  const relevant = segments.filter((s) => sidSet.has(Number(s.sid)))
  if (!relevant.length || relevant.length < sids.length) return 'no-target'

  // Group segments by internal_id. Segments sharing the same internal_id
  // originate from a single trans-unit that was split into N parts — their
  // translations must be concatenated. Segments with different internal_id
  // are textual duplicates mapped to the same node — they must produce
  // identical translations (mismatch otherwise).
  const groups = new Map()
  for (const seg of relevant) {
    const key = seg.internal_id ?? `__sid_${seg.sid}`
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key).push(seg)
  }

  const groupTargets = []
  for (const [, group] of groups) {
    group.sort((a, b) => Number(a.sid) - Number(b.sid))
    const targets = group.map((s) => {
      if (!s.target) return null
      const stripped = stripSegmentTags(s.target).replace(/[\s\u200b]+/g, ' ').trim()
      return stripped || null
    })
    if (targets.some((t) => t === null)) return 'no-target'
    groupTargets.push(targets.join(' '))
  }

  if (!groupTargets.length) return 'no-target'

  const allSame = groupTargets.every((t) => t === groupTargets[0])
  if (!allSame) return 'mismatch'

  replaceTextContent(el, groupTargets[0])
  return 'ok'
}

/**
 * Clears all existing highlights from the container by unwrapping
 * <mark> elements back to their original text nodes.
 *
 * @param {HTMLElement} container
 */
export const clearHighlights = (container) => {
  const marks = Array.from(
    container.querySelectorAll(`mark.${HIGHLIGHT_CLASS}`),
  )
  for (let i = marks.length - 1; i >= 0; i--) {
    const mark = marks[i]
    const parent = mark.parentNode
    if (!parent) continue
    const textNode = document.createTextNode(mark.textContent)
    parent.replaceChild(textNode, mark)
    parent.normalize()
  }
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
 * Highlights all elements in a container whose `data-context-sids`
 * attribute includes the given SID. Wraps their text content in
 * <mark> elements.
 *
 * Each matched element is one "occurrence".
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

  const numSid = Number(sid)
  const all = container.querySelectorAll(`[${SEGMENT_SIDS_ATTR}]`)
  const elements = Array.from(all).filter((el) =>
    getSidsFromElement(el).includes(numSid),
  )
  if (!elements.length) return result

  elements.forEach((el, elIndex) => {
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null)
    const textNodes = []
    while (walker.nextNode()) {
      if (walker.currentNode.parentNode.closest(`mark.${HIGHLIGHT_CLASS}`)) {
        continue
      }
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

  // Group marks by their closest [data-context-sids] ancestor to determine
  // which occurrence each mark belongs to.
  const occurrences = []
  const seen = new Set()
  allMarks.forEach((m) => {
    const ancestor = m.closest(`[${SEGMENT_SIDS_ATTR}]`)
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
 * @returns {{sids: number[], nodeIndex: number}|null} nodeIndex is the element's
 *   position in the container's segment node map (`nodes` array). Falls back to 0
 *   when the node map is unavailable or the element is not found in it.
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
  const seenSids = new Set()
  for (const seg of segments) {
    const segText = seg[field]
    if (!segText) continue
    const numSid = Number(seg.sid)
    if (seenSids.has(numSid)) continue
    const regex = buildFlexibleRegex(segText)
    regex.lastIndex = 0
    if (regex.test(clickedText)) {
      matchingSids.push(numSid)
      seenSids.add(numSid)
    }
  }

  return matchingSids.length > 0 ? {sids: matchingSids, nodeIndex: 0} : null
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
  {replaceWithTarget = false, metadataMap = {}} = {},
) => {
  if (!container || !segments || !segments.length) return

  // Pre-compute normalised source for each segment, sorted by SID so
  // their order matches the document order of the HTML elements.
  // Coerce `sid` to Number so comparisons against getSidsFromElement()
  // (which returns number[]) use strict equality consistently.
  const prepared = segments
    .map((seg) => ({
      ...seg,
      sid: Number(seg.sid),
      normSource: stripSegmentTags(seg.source),
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

  // Cache parsed SIDs per element to avoid repeated getAttribute + split
  // calls inside the inner loops.
  const elSidsCache = new Map()
  const getCachedSids = (el) => {
    if (!elSidsCache.has(el)) elSidsCache.set(el, getSidsFromElement(el))
    return elSidsCache.get(el)
  }
  const appendSid = (el, sid) => {
    const existing = getCachedSids(el)
    // Defense-in-depth: never append a SID that is already on the element.
    if (existing.includes(sid)) return
    const updated = [...existing, sid].sort((a, b) => a - b)
    el.setAttribute(SEGMENT_SIDS_ATTR, updated.join(','))
    elSidsCache.set(el, updated)
  }

  // Strategy pass — runs first, highest priority.
  // Segments with resname + restype are resolved via DOM-attribute lookups
  // before any text-match runs. Misses (element not found) silently fall
  // through to text-match exactly as segments with no metadata.
  const strategyResolved = new Set()
  const tier1Nodes = new Set()

  for (const [sidStr, {resname, restype}] of Object.entries(metadataMap)) {
    const sid = Number(sidStr)
    if (!resname || !restype) continue
    if (alreadyTagged.has(sid)) {
      strategyResolved.add(sid)
      const idx = prepared.findIndex((p) => p.sid === sid)
      if (idx !== -1) used.add(idx)
      // Re-find the element so Pass 2 still excludes it on incremental calls
      const existingEl = findElementByMetadata(container, resname, restype)
      if (existingEl) tier1Nodes.add(existingEl)
      continue
    }
    const el = findElementByMetadata(container, resname, restype)
    if (el) {
      appendSid(el, sid)
      strategyResolved.add(sid)
      tier1Nodes.add(el)
      // Mark the corresponding prepared entry as used so Pass 1 skips it
      const idx = prepared.findIndex((p) => p.sid === sid)
      if (idx !== -1) used.add(idx)
    }
  }

  const candidates = container.querySelectorAll(BLOCK_SELECTOR)

  // Pass 1 — positional: each untagged element consumes the first unused
  // segment whose normalised source is an exact match.  This preserves
  // positional pairing when duplicate source texts appear in multiple
  // elements (e.g. two <p>Equipment</p> nodes).
  for (const el of candidates) {
    // Skip elements that were successfully tagged by the Strategy Pass
    if (tier1Nodes.has(el)) continue

    // Skip elements already tagged (from a previous incremental call) —
    // Pass 1 is strictly for fresh, untagged elements.
    if (el.hasAttribute(SEGMENT_SIDS_ATTR)) continue

    // Skip elements that contain an already-tagged descendant — the
    // descendant is the more specific match.
    if (el.querySelector(`[${SEGMENT_SIDS_ATTR}]`)) continue

    const elText = el.textContent.replace(/\s+/g, ' ').trim()
    if (!elText) continue

    const elTextLower = elText.toLowerCase()

    for (let i = 0; i < prepared.length; i++) {
      if (used.has(i)) continue
      if (strategyResolved.has(prepared[i].sid)) continue
      if (getCachedSids(el).includes(prepared[i].sid)) continue
      if (elTextLower === prepared[i].normSource.toLowerCase()) {
        appendSid(el, prepared[i].sid)
        used.add(i)
        break // one new segment per element per pass (positional pairing)
      }
    }
  }

  // Pass 2 — N:N broadcast: every segment is checked against every
  // candidate element regardless of the `used` set.  If the segment's
  // normalised source matches the element's text and that SID is not yet
  // on the element, append it.  This ensures that one segment can map to
  // many nodes AND one node can accumulate many segments.
  // Text replacement (when replaceWithTarget is true) happens after both
  // passes, via updateNodeTranslation.
  for (const el of candidates) {
    // Skip elements that were successfully tagged by the Strategy Pass,
    // or that contain / are contained by a tier1 node.
    if (tier1Nodes.has(el)) continue
    if ([...tier1Nodes].some((n) => el.contains(n) || n.contains(el))) continue

    const elText = el.textContent.replace(/\s+/g, ' ').trim()
    if (!elText) continue

    const elTextLower = elText.toLowerCase()

    for (let i = 0; i < prepared.length; i++) {
      if (getCachedSids(el).includes(prepared[i].sid)) continue
      if (strategyResolved.has(prepared[i].sid)) continue
      if (elTextLower === prepared[i].normSource.toLowerCase()) {
        appendSid(el, prepared[i].sid)
      }
    }
  }

  buildSegmentNodeMap(container)

  if (replaceWithTarget) {
    const map = getSegmentNodeMap(container)
    if (map) {
      map.nodes.forEach((el) => {
        updateNodeTranslation(el, segments)
        // mismatch silently ignored here — shown via the updateTranslation message handler
      })
    }
  }
}

/**
 * Checks whether a DOM element is hidden by CSS (display:none, visibility:hidden,
 * opacity:0, zero dimensions, or content-visibility).
 *
 * @param {HTMLElement} el
 * @returns {boolean}
 */
export const isNodeHidden = (el) => {
  if (!el) return true
  if (typeof el.checkVisibility === 'function') {
    return !el.checkVisibility({checkOpacity: true, checkVisibilityCSS: true})
  }
  const style = getComputedStyle(el)
  if (style.display === 'none' || style.visibility === 'hidden') return true
  if (parseFloat(style.opacity) === 0) return true
  return false
}

/**
 * Extracts the context preview payload fields from a raw segment object.
 *
 * Works with both plain JS objects (dot notation) and any shape where
 * `metadata` is an Array<{meta_key: string, meta_value: string}>.
 *
 * @param {{metadata?: Array<{meta_key: string, meta_value: string}>, context_url?: string|null}} segment
 * @returns {{context_url: string|null, resname: string|null, restype: string|null, screenshot: string|null}}
 */
export const extractSegmentContextFields = (segment) => {
  const meta = segment.metadata ?? []
  const find = (key) => meta.find((m) => m.meta_key === key)?.meta_value ?? null
  return {
    context_url: segment.context_url ?? null,
    resname: find('resname'),
    restype: find('restype'),
    screenshot: find('screenshot'),
  }
}
