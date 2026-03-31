/**
 * DOM lookup strategies for the ContextReview segment tagging pass.
 *
 * findElementByMetadata selects the strategy based on `restype` and
 * returns the matching DOM element, or null on miss / error / stub.
 */

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
        // Absolute XPath (starts with '/') must be evaluated against document,
        // then verified to be a descendant of container.
        const isAbsolute = resname.startsWith('/')
        const contextNode = isAbsolute ? document : container
        const result = document.evaluate(
          resname,
          contextNode,
          null,
          XPathResult.FIRST_ORDERED_NODE_TYPE,
          null,
        )
        const el = result.singleNodeValue
        if (!el) return null
        if (isAbsolute && !container.contains(el)) return null
        return el
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
