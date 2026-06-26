/**
 * DOM lookup strategies for the ContextPreview segment tagging pass.
 *
 * findElementByMetadata selects the strategy based on `restype` and
 * returns the matching DOM element, or null on miss / error / stub.
 */
import {findElementByTextMatch} from './contextPreviewUtils'

/**
 * Regex that matches the 0-based node-path format produced by some CMS
 * extractors (e.g. "html[0]/body[0]/div[2]/ul[0]/we-product-item[0]/h3[0]").
 *
 * Characteristics that distinguish it from standard XPath:
 *   - Does NOT start with "/" or "//"
 *   - Every step is `tagName[index]`
 *   - Indices are 0-based
 *
 * @type {RegExp}
 */
const NODE_PATH_RE = /^[a-z][\w-]*\[\d+\](?:\/[a-z][\w-]*\[\d+\])*$/i

/**
 * Walks a 0-based node-path against `container`, returning the matched
 * element or null.
 *
 * Algorithm per step:
 *   1. Among `current`'s direct children, collect those whose tagName
 *      matches (case-insensitive).  Pick the one at `index` (0-based).
 *   2. If no direct child matches, search descendants (breadth-first via
 *      querySelectorAll) — this handles CMS paths that skip structural
 *      wrappers like `<li>`.
 *
 * The `html[N]/body[N]` prefix is stripped automatically because
 * `container` already represents the body content.
 *
 * @param {HTMLElement} container
 * @param {string} path  e.g. "html[0]/body[0]/div[0]/ul[0]/h3[0]"
 * @returns {HTMLElement|null}
 */
export const walkNodePath = (container, path) => {
  if (!container || !path) return null

  const steps = path.split('/')

  // Strip leading html[N] / body[N] prefix — container is already the body.
  let start = 0
  if (steps[start]?.match(/^html\[\d+\]$/i)) start++
  if (steps[start]?.match(/^body\[\d+\]$/i)) start++

  let current = container

  for (let i = start; i < steps.length; i++) {
    const match = steps[i].match(/^([\w-]+)\[(\d+)\]$/)
    if (!match) return null

    const tagName = match[1].toUpperCase()
    const index = parseInt(match[2], 10)

    // 1. Try direct children first (strict match)
    const directChildren = []
    for (const child of current.children) {
      if (child.tagName === tagName) directChildren.push(child)
    }

    if (index < directChildren.length) {
      current = directChildren[index]
      continue
    }

    // 2. Descendant fallback — handles paths that skip wrapper elements
    const descendants = current.getElementsByTagName(match[1])
    const matching = [...descendants]
    if (index < matching.length) {
      current = matching[index]
      continue
    }

    return null
  }

  return current === container ? null : current
}

export const findContainerByXpath = (rootContainer, xpath) => {
  if (!rootContainer || !xpath) return null
  try {
    let expr = xpath
    if (xpath.startsWith('/html/body/')) {
      expr = '.' + xpath.slice('/html/body'.length)
    } else if (xpath.startsWith('/html/body')) {
      expr = '.'
    } else if (xpath.startsWith('//')) {
      expr = '.' + xpath
    } else if (xpath.startsWith('/')) {
      expr = '.' + xpath
    }
    const result = document.evaluate(
      expr,
      rootContainer,
      null,
      XPathResult.FIRST_ORDERED_NODE_TYPE,
      null,
    )
    const node = result.singleNodeValue
    if (!node) return null
    return node.nodeType === Node.ATTRIBUTE_NODE ? node.ownerElement : node
  } catch {
    return null
  }
}

export class ClientNodepathRegistry {
  constructor() {
    this._strategies = new Map()
  }

  register(clientName, strategy) {
    this._strategies.set(clientName, strategy)
  }

  resolve(clientName) {
    return this._strategies.get(clientName) ?? null
  }
}

// Selector for block-level elements that can independently host a segment.
// Mirrors the set used in contextPreviewUtils.js BLOCK_SELECTOR.
const BLOCK_SELECTOR =
  'p, h1, h2, h3, h4, h5, h6, li, td, th, div, title'

/**
 * Normalized Levenshtein distance in [0, 1].
 * 0 = identical, 1 = completely different.
 * Uses an O(min(m,n)) space implementation.
 */
const normalizedLevenshtein = (a, b) => {
  if (a === b) return 0
  if (!a.length) return 1
  if (!b.length) return 1
  // Keep `a` as the shorter string to minimise memory usage
  if (a.length > b.length) [a, b] = [b, a]
  const m = a.length, n = b.length
  let row = Array.from({length: m + 1}, (_, i) => i)
  for (let j = 1; j <= n; j++) {
    let prev = row[0]
    row[0] = j
    for (let i = 1; i <= m; i++) {
      const tmp = row[i]
      row[i] = a[i - 1] === b[j - 1] ? prev : 1 + Math.min(prev, row[i], row[i - 1])
      prev = tmp
    }
  }
  return row[m] / n  // n = max length (longer string)
}

export class AemContainerTextMatchStrategy {
  execute(rootContainer, path, normSource) {
    if (!path || !normSource) return null
    const aemContainer = findElementByMetadata(
      rootContainer,
      path,
      'x-attribute_name_value',
    )
    if (!aemContainer) return null
    // allowTagged: multiple segments can share the same AEM container path and
    // text — they must all land on the same element rather than being distributed.
    const match = findElementByTextMatch(aemContainer, normSource, {allowTagged: true})
    if (match) return match
    // Fallback: use the container itself only when it is a leaf-like node
    // (≤1 block descendant) AND its text is similar enough to the source.
    // Using normalized Levenshtein (threshold 0.25) handles minor differences
    // from HTML entities, typographic quotes, trailing punctuation, etc., while
    // soundly rejecting unrelated text (e.g. "requires login" vs "GET STARTED").
    if (aemContainer.querySelectorAll(BLOCK_SELECTOR).length > 1) return null
    const containerText = aemContainer.textContent.replace(/\s+/g, ' ').trim().toLowerCase()
    const needle = normSource.replace(/\s+/g, ' ').trim().toLowerCase()
    return normalizedLevenshtein(containerText, needle) <= 0.25 ? aemContainer : null
  }
}

export const clientNodepathRegistry = new ClientNodepathRegistry()
clientNodepathRegistry.register('aem', new AemContainerTextMatchStrategy())

/**
 * Finds the DOM element in `container` that corresponds to a segment,
 * selecting the lookup strategy based on `restype`.
 *
 * Returns null when:
 * - restype is null or unknown
 * - restype is x-client_nodepath and clientName/normSource are absent or unregistered
 * - the element is not found
 * - any DOM exception is thrown
 *
 * @param {HTMLElement} container
 * @param {string|null} resname
 * @param {string|null} restype
 * @param {string|null} [clientName]
 * @param {string|null} [normSource]
 * @returns {HTMLElement|null}
 */
export const findElementByMetadata = (
  container,
  resname,
  restype,
  clientName = null,
  normSource = null,
) => {
  if (!container || !resname || !restype) return null

  try {
    switch (restype) {
      case 'x-tag-id':
        return container.querySelector('#' + CSS.escape(resname))

      case 'x-css_class':
        return container.querySelector('.' + CSS.escape(resname))

      case 'x-path': {
        if (NODE_PATH_RE.test(resname)) {
          return walkNodePath(container, resname)
        }
        return findContainerByXpath(container, resname)
      }

      case 'x-attribute_name_value': {
        const eqIdx = resname.indexOf('=')
        if (eqIdx === -1) return null
        const attrName = resname.slice(0, eqIdx)
        const attrValue = resname.slice(eqIdx + 1)
        const escapedValue = attrValue.replace(/\\/g, '\\\\').replace(/"/g, '\\"')
        return container.querySelector(
          `[${CSS.escape(attrName)}="${escapedValue}"]`,
        )
      }

      case 'x-client_nodepath': {
        if (!clientName || !normSource) return null
        const strategy = clientNodepathRegistry.resolve(clientName.toLowerCase())
        if (!strategy) return null
        return strategy.execute(container, resname, normSource) ?? null
      }

      default:
        return null
    }
  } catch {
    return null
  }
}
