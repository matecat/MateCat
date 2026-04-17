/**
 * DOM lookup strategies for the ContextPreview segment tagging pass.
 *
 * findElementByMetadata selects the strategy based on `restype` and
 * returns the matching DOM element, or null on miss / error / stub.
 */

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

/**
 * Finds the DOM element in `container` that corresponds to a segment,
 * selecting the lookup strategy based on `restype`.
 *
 * Returns null when:
 * - restype is null, unknown, or x-client_nodepath (stub)
 * - the element is not found
 * - any DOM exception is thrown
 *
 * @param {HTMLElement} container
 * @param {string|null} resname
 * @param {string|null} restype
 * @returns {HTMLElement|null}
 */
export const findElementByMetadata = (container, resname, restype) => {
  if (!container || !resname || !restype) return null

  try {
    switch (restype) {
      case 'x-tag-id':
        return container.querySelector('#' + CSS.escape(resname))

      case 'x-css_class':
        return container.querySelector('.' + CSS.escape(resname))

      case 'x-path': {
        // 0-based node-path format (e.g. "html[0]/body[0]/div[2]/h3[0]")
        // — handled separately so it can be removed later without touching
        // the standard XPath logic below.
        if (NODE_PATH_RE.test(resname)) {
          return walkNodePath(container, resname)
        }

        let xpath = resname
        if (resname.startsWith('/html/body/')) {
          xpath = '.' + resname.slice('/html/body'.length)
        } else if (resname.startsWith('/html/body')) {
          // Exact match of /html/body (no trailing path) — map to container itself
          xpath = '.'
        } else if (resname.startsWith('//')) {
          xpath = '.' + resname
        } else if (resname.startsWith('/')) {
          xpath = '.' + resname
        }
        const result = document.evaluate(
          xpath,
          container,
          null,
          XPathResult.FIRST_ORDERED_NODE_TYPE,
          null,
        )
        const node = result.singleNodeValue
        if (!node) return null
        // XPaths may target attribute nodes (e.g. //img/@alt). Attr nodes have
        // no getAttribute/setAttribute, so return the owning element instead.
        return node.nodeType === Node.ATTRIBUTE_NODE ? node.ownerElement : node
      }

      case 'x-attribute_name_value': {
        const eqIdx = resname.indexOf('=')
        if (eqIdx === -1) return null
        const attrName = resname.slice(0, eqIdx)
        const attrValue = resname.slice(eqIdx + 1)
        return container.querySelector(
          `[${CSS.escape(attrName)}="${CSS.escape(attrValue)}"]`,
        )
      }

      case 'x-client_nodepath':
        // Stub — falls through to text-match
        return null

      default:
        return null
    }
  } catch {
    return null
  }
}
