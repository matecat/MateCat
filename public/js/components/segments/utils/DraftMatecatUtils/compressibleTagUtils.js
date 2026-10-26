import {Base64} from 'js-base64'

/**
 * Helpers for the "compress/expand" feature, scoped to the only tags that are
 * compactable. Each is identified by a `kind`:
 *
 *  - `pc`: a `ph` that carries an XLIFF 2 `pc` tag, identified by its `ctype`.
 *    - WITHOUT dataRef (`x-original_pc_open` / `x-original_pc_close`): the open/close
 *      ids (`mtc_N`) cannot be linked, so pairing is positional (a stack).
 *    - WITH dataRef (`x-pc_open_data_ref` / `x-pc_close_data_ref`): open `id="x_1"` and
 *      close `id="x_2"` share the base id (`x`), so pairing is by base id.
 *  - `g`: an XLIFF 1.2 `<g>` opening tag with an `equiv-text` attribute. Its `</g>`
 *    carries no attribute at all, so it is paired by the numberer (see below).
 *
 * Any other tag (semantic placeholders, line breaks, x-html, an `x-original_x` ph,
 * a `<g>` without equiv-text, …) is NOT compactable and is ignored by every helper here.
 */

// ctype -> {role, hasDataRef}. Anything not listed is a non-pc ph tag.
export const PC_CTYPES = {
  'x-original_pc_open': {role: 'open', hasDataRef: false},
  'x-original_pc_close': {role: 'close', hasDataRef: false},
  'x-pc_open_data_ref': {role: 'open', hasDataRef: true},
  'x-pc_close_data_ref': {role: 'close', hasDataRef: true},
}

const CTYPE_RE = /\bctype="([^"]+)"/
const ID_RE = /\bid="([^"]*)"/
const EQUIV_TEXT_RE = /\bequiv-text="base64:([^"]+)"/
// An opening `<g …>`, not the self-closing `<g …/>`.
const G_OPEN_RE = /^<g\b[^>]*[^/]>$/
const G_CLOSE = '</g>'

/**
 * The decoded `equiv-text` of an opening `<g>` tag, or null when it has none.
 * The value is base64 of the already HTML-escaped text, as for `ph` tags.
 * @param {string} encodedText e.g. `<g id="1" equiv-text="base64:WyMkZHA0XQ==">`
 * @returns {string|null}
 */
export const getGEquivText = (encodedText) => {
  if (!encodedText || !G_OPEN_RE.test(encodedText)) return null
  const equivText = encodedText.match(EQUIV_TEXT_RE)?.[1]
  return equivText ? Base64.decode(equivText) : null
}

/**
 * Classify a tag from its raw encoded text.
 * @param {string} encodedText e.g. `<ph id="source1_1" ctype="x-pc_open_data_ref" .../>`
 * @returns {{kind:'pc'|'g', role:'open'|'close', hasDataRef:boolean, ctype:string, id:string, baseId:string}|null}
 *          null when the tag is not compressible. A `</g>` is null too: whether it
 *          closes a compressible `<g>` depends on its opener, which only
 *          `createTagNumberer` tracks.
 */
export const classifyCompressibleTag = (encodedText) => {
  if (!encodedText) return null
  const id = encodedText.match(ID_RE)?.[1] ?? ''
  if (getGEquivText(encodedText) !== null) {
    return {
      kind: 'g',
      role: 'open',
      hasDataRef: false,
      ctype: '',
      id,
      baseId: id,
    }
  }
  if (!encodedText.startsWith('<ph')) return null
  const ctype = encodedText.match(CTYPE_RE)?.[1]
  const info = ctype && PC_CTYPES[ctype]
  if (!info) return null
  const baseId = id.replace(/_\d+$/, '') // source1_1 -> source1
  return {kind: 'pc', ...info, ctype, id, baseId}
}

const COMPRESSIBLE_TEST_RE = new RegExp(
  [
    `<ph\\b[^>]*\\bctype="(${Object.keys(PC_CTYPES).join('|')})"[^>]*\\/>`,
    `<g\\b[^>]*\\bequiv-text="base64:[^"]+"(?![^>]*\\/>)[^>]*>`,
  ].join('|'),
)

/**
 * Cheap predicate: does the encoded segment string contain at least one
 * compressible tag? Used to decide whether to show the compress/expand button.
 */
export const hasCompressibleTags = (segmentString) =>
  !!segmentString && COMPRESSIBLE_TEST_RE.test(segmentString)

/**
 * Stateful numberer. Feed each tag's encoded text in document order; returns
 * `{index, role}` for compressible tags (open and close of a pair share the same
 * 0-based `index`, rendered as `index + 1`) or `null` for any other tag.
 *
 * Every `<g>` and `</g>` must be fed, compressible or not: a `</g>` carries no
 * attribute, so it closes whichever `<g>` is innermost. A `<g>` without
 * equiv-text holds its slot on the stack, so its `</g>` gets null.
 *
 * @param {number} [inheritedIndex] when a tag already has an index (e.g. a target
 *        tag that inherited it from the matching source tag), it is honoured and
 *        the internal counter is kept monotonic.
 */
export const createTagNumberer = () => {
  let next = 0
  const byBaseId = new Map() // dataRef: baseId -> index
  const stack = [] // non-dataRef pc: open indexes awaiting a close
  const gStack = [] // g: open indexes awaiting a `</g>`, null for a plain <g>
  const bump = (n) => {
    if (n + 1 > next) next = n + 1
  }
  const hasInherited = (n) => n !== undefined && n !== null

  return (encodedText, inheritedIndex) => {
    if (encodedText === G_CLOSE) {
      const openIndex = gStack.length ? gStack.pop() : null
      const index = hasInherited(inheritedIndex) ? inheritedIndex : openIndex
      if (index === null) return null
      bump(index)
      return {index, role: 'close'}
    }
    const c = classifyCompressibleTag(encodedText)
    if (!c) {
      if (G_OPEN_RE.test(encodedText ?? '')) gStack.push(null)
      return null
    }
    let index
    if (hasInherited(inheritedIndex)) {
      index = inheritedIndex
      // keep the pairing state in sync so non-inherited partners still match
      if (c.kind === 'g') gStack.push(index)
      else if (c.hasDataRef) byBaseId.set(c.baseId, index)
      else if (c.role === 'open') stack.push(index)
      else if (c.role === 'close' && stack.length) stack.pop()
      bump(index)
    } else if (c.kind === 'g') {
      index = next++
      gStack.push(index)
    } else if (c.hasDataRef && byBaseId.has(c.baseId)) {
      index = byBaseId.get(c.baseId)
    } else if (c.hasDataRef) {
      index = next++
      byBaseId.set(c.baseId, index)
    } else if (c.role === 'open') {
      index = next++
      stack.push(index)
    } else {
      // non-dataRef close: pair with the nearest unclosed open
      index = stack.length ? stack.pop() : next++
    }
    return {index, role: c.role}
  }
}
