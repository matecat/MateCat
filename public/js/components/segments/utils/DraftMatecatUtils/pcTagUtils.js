/**
 * Helpers for the "compress/expand" feature, scoped to the only `ph` tags that
 * are compactable: those that carry an XLIFF `pc` tag.
 *
 * Two variants, identified by the `ctype` attribute:
 *  - WITHOUT dataRef (`x-original_pc_open` / `x-original_pc_close`): the open/close
 *    ids (`mtc_N`) cannot be linked, so pairing is positional (a stack).
 *  - WITH dataRef (`x-pc_open_data_ref` / `x-pc_close_data_ref`): open `id="x_1"` and
 *    close `id="x_2"` share the base id (`x`), so pairing is by base id.
 *
 * Any other `ph` (semantic placeholders, line breaks, x-html, …) is NOT compactable
 * and is ignored by every helper here.
 */

// ctype -> {role, hasDataRef}. Anything not listed is a non-pc ph tag.
export const PC_CTYPES = {
  'x-original_pc_open': {role: 'open', hasDataRef: false},
  'x-original_pc_close': {role: 'close', hasDataRef: false},
  'x-pc_open_data_ref': {role: 'open', hasDataRef: true},
  'x-pc_close_data_ref': {role: 'close', hasDataRef: true},
}

const CTYPE_RE = /\bctype="([^"]+)"/
const ID_RE = /\bid="(-?\w+)"/

/**
 * Classify a `ph` tag from its raw encoded text.
 * @param {string} encodedText e.g. `<ph id="source1_1" ctype="x-pc_open_data_ref" .../>`
 * @returns {{role:'open'|'close', hasDataRef:boolean, ctype:string, id:string, baseId:string}|null}
 *          null when the tag is not a pc-carrying ph tag.
 */
export const classifyPcPhTag = (encodedText) => {
  if (!encodedText) return null
  const ctype = encodedText.match(CTYPE_RE)?.[1]
  const info = ctype && PC_CTYPES[ctype]
  if (!info) return null
  const id = encodedText.match(ID_RE)?.[1] ?? ''
  const baseId = id.replace(/_\d+$/, '') // source1_1 -> source1
  return {...info, ctype, id, baseId}
}

const PC_CTYPE_TEST_RE =
  /<ph\b[^>]*\bctype="(x-original_pc_open|x-original_pc_close|x-pc_open_data_ref|x-pc_close_data_ref)"[^>]*\/>/

/**
 * Cheap predicate: does the encoded segment string contain at least one
 * pc-carrying ph tag? Used to decide whether to show the compress/expand button.
 */
export const hasCompressiblePhTags = (segmentString) =>
  !!segmentString && PC_CTYPE_TEST_RE.test(segmentString)

/**
 * Stateful numberer. Feed each tag's encoded text in document order; returns
 * `{index, role}` for pc tags (open and close of a pair share the same 0-based
 * `index`, rendered as `index + 1`) or `null` for non-pc tags.
 *
 * @param {number} [inheritedIndex] when a tag already has an index (e.g. a target
 *        tag that inherited it from the matching source tag), it is honoured and
 *        the internal counter is kept monotonic.
 */
export const createPcNumberer = () => {
  let next = 0
  const byBaseId = new Map() // dataRef: baseId -> index
  const stack = [] // non-dataRef: open indexes awaiting a close
  const bump = (n) => {
    if (n + 1 > next) next = n + 1
  }
  return (encodedText, inheritedIndex) => {
    const c = classifyPcPhTag(encodedText)
    if (!c) return null
    let index
    if (inheritedIndex !== undefined && inheritedIndex !== null) {
      index = inheritedIndex
      // keep the pairing state in sync so non-inherited partners still match
      if (c.hasDataRef) byBaseId.set(c.baseId, index)
      else if (c.role === 'open') stack.push(index)
      else if (stack.length) stack.pop()
      bump(index)
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
