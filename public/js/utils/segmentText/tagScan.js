import {tagSignatures} from './tagModel'
import decodeTagInfo from './decodeTagInfo'
import {createTagNumberer} from './compressibleTagUtils'

// The one place that finds tags in a segment string. Every caller reads the signatures through `tagSignatures` at call
// time: plugins and the show-whitespace switch change them through its middleware.
//
// The callers do not all look at the same string. The store and the Lexiqa text scan the segment as stored, each
// signature on its own, so the matches of two signatures may overlap. The views replace one signature at a time over
// the text an earlier signature already turned into chips, so they call findTagMatches/replaceTagMatches once per
// signature on that text; a later signature then matches inside the content an earlier one inserted, which is how
// tags come to be nested in a chip.

// The signatures whose tags can be compressible (see compressibleTagUtils), the only ones that are numbered.
const NUMBERED_TAG_NAMES = ['ph', 'g', 'gCl']

const globalRegex = (regex) =>
  regex.flags.includes('g')
    ? regex
    : new RegExp(regex.source, regex.flags + 'g')

/**
 * The matches of the signature `name` in `text`, in order and without overlaps, as `[{0: markup, 1: capture, index}]`.
 * `display` is the rule of the views, which match a signature by its placeholder regex when it has one: that regex
 * reads the content a chip shows, and for `x` it is case-sensitive where the tag regex is not.
 */
export const findTagMatches = (text, name, {display = false} = {}) => {
  const signature = tagSignatures[name]
  if (!signature) return []
  const {regex, placeholderRegex, find} = signature
  if (display && placeholderRegex)
    return [...text.matchAll(globalRegex(placeholderRegex))]
  // `find` scans in linear time where the regex of the signature would not (blanks)
  if (find) return find(text)
  return [...text.matchAll(globalRegex(regex))]
}

/**
 * `text` with each match of the signature `name` replaced by what `replace(match)` returns, built in one pass.
 */
export const replaceTagMatches = (text, name, replace, options) => {
  let replaced = ''
  let last = 0
  findTagMatches(text, name, options).forEach((match) => {
    replaced += text.slice(last, match.index) + replace(match)
    last = match.index + match[0].length
  })
  return replaced + text.slice(last)
}

/**
 * Every tag of `text`, signature by signature in the order of `tagSignatures`, each with its offset, its length, its
 * signature name, its markup (`encodedText`), its `id` and the text it shows (`placeholder` and `decodedText`). Tags of
 * two signatures may overlap. `accept(name)` limits the scan to some signatures.
 */
export const scanTags = (text, accept = () => true) => {
  const tags = []
  for (const name in tagSignatures) {
    if (!accept(name)) continue
    // Collected first and added after, so a signature that throws midway adds none of its tags. Added one by one: a
    // spread into push() has an argument limit, which a segment of single-character tags can reach.
    const found = []
    try {
      findTagMatches(text, name).forEach((match) => {
        const encodedText = match[0]
        const {id, content} = decodeTagInfo({data: {name, encodedText}})
        found.push({
          offset: match.index,
          length: encodedText.length,
          name,
          encodedText,
          id,
          placeholder: content,
          decodedText: content,
        })
      })
      found.forEach((tag) => tags.push(tag))
    } catch (e) {
      console.error('Error finding tags in scanTags', e)
    }
  }
  return tags
}

/**
 * The closing tags of `tags` (in document order) paired with their opening tag: each takes the nearest opening tag
 * before it that no other closing tag took. Returns a Map closing tag → opening tag, or null for an orphan.
 */
export const pairClosingTags = (tags, isOpening, isClosing) => {
  const open = []
  const pairs = new Map()
  tags.forEach((tag) => {
    if (isOpening(tag)) open.push(tag)
    else if (isClosing(tag)) pairs.set(tag, open.pop() ?? null)
  })
  return pairs
}

/**
 * `scanTags` with every closing tag paired: a closing tag (no id of its own) takes the id and the placeholder of its
 * opening tag, so a click highlights both halves; an orphan shows `?`. Returned as the opening tags from the last to
 * the first, then the closing tags and the self-closing ones in document order.
 */
export const scanPairedTags = (text, accept) => {
  const openings = []
  const closings = []
  const selfClosing = []
  scanTags(text, accept).forEach((tag) => {
    const {isClosure, selfClosing: isSelfClosing} = tagSignatures[tag.name]
    if (isClosure) closings.push(tag)
    else if (isSelfClosing) selfClosing.push(tag)
    else openings.push(tag)
  })
  openings.sort((a, b) => b.offset - a.offset)
  closings.sort((a, b) => a.offset - b.offset)
  const openingSet = new Set(openings)
  const pairs = pairClosingTags(
    [...openings.slice().reverse(), ...closings].sort(
      (a, b) => a.offset - b.offset,
    ),
    (tag) => openingSet.has(tag),
    (tag) => !openingSet.has(tag),
  )
  pairs.forEach((opening, closing) => {
    if (opening) {
      closing.id = opening.id
      closing.placeholder = opening.placeholder
    } else {
      closing.placeholder = '?'
    }
  })
  return [...openings, ...closings, ...selfClosing]
}

/**
 * The index and role (`{index, role}`, or null) of each of `tags`, in document order: only the compressible tags are
 * numbered, the open and close of a pair get the same index, and a tag's own `index` (inherited from the source) is
 * kept.
 */
export const numberTags = (tags) => {
  const numberer = createTagNumberer()
  return tags.map(({name, encodedText, index}) =>
    NUMBERED_TAG_NAMES.includes(name) ? numberer(encodedText, index) : null,
  )
}

/**
 * For the views, which replace one signature at a time: the numbers of the numbered tags of `text`, queued by
 * signature in document order, for each signature's replace to take in turn.
 */
export const numberTagsInDocumentOrder = (text) => {
  const tags = scanTags(text, (name) => NUMBERED_TAG_NAMES.includes(name)).sort(
    (a, b) => a.offset - b.offset,
  )
  const queues = Object.fromEntries(
    NUMBERED_TAG_NAMES.map((name) => [name, []]),
  )
  numberTags(tags).forEach((numbered, i) => queues[tags[i].name].push(numbered))
  return queues
}
