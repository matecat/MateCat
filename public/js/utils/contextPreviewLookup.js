/**
 * DOM lookup strategies for the ContextPreview segment tagging pass.
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
        // XPaths are authored against the context HTML document structure,
        // but `container` holds only the body content — not a full document.
        // Absolute paths must be made relative to `container`:
        //   /html/body/X  →  ./X   (strip the /html/body root prefix)
        //   //X           →  .//X  (prepend . for container-relative search)
        //   other /X      →  .X    (strip leading /)
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
