// What the CodeMirror editors publish to the segment store, computed from the segment string alone: the decoded
// segment, the plain text, the tag map, the missing tags, the Lexiqa text and the character counter. The store, the
// QA warnings and the tag menu rely on these exact values (a tag keeps only the fields they read, see storeTag), and
// __golden__/segmentStoreData.json pins them, edge cases included. The segment is read once, in order, as text runs,
// tags and line breaks (piecesOf); every value is built from that list.
import matchTag from './matchTag'
import findTagWithRegex from './findTagWithRegex'
import {getErrorCheckTag, getSplitBlockTag, tagSignatures} from './tagModel'
import {createTagNumberer} from './compressibleTagUtils'
import {
  decodeHtmlEntities,
  decodePlaceholdersToPlainText,
  encodeHtmlEntities,
  excludeSomeTagsTransformToText,
  removeTagsFromText,
  removeZeroWidthSpace,
} from './tagUtils'
import prepareTextForLexiqa from './prepareTextForLexiqa'
import checkForMissingTags from './checkForMissingTag'
import {formatText, getCharactersCounter} from './textUtils'

export {formatText}

// Numbers ONLY the compressible tags: the open and close of a pair get the same index, an index inherited from the
// source tag map is honoured.
const NUMBERED_TAG_NAMES = ['ph', 'g', 'gCl']

const addIncrementalIndex = (tagRange) => {
  const numberer = createTagNumberer()
  return tagRange.map((cur) => {
    if (!NUMBERED_TAG_NAMES.includes(cur.data.name)) return cur
    const result = numberer(cur.data.encodedText, cur.data.index)
    if (!result) return cur
    return {
      ...cur,
      data: {...cur.data, index: result.index, tagRole: result.role},
    }
  })
}

// A target tag takes the index of the source tag with the same id and name. Tags with an empty id (XLIFF 2 ph) are
// not matched, or all of them would pair with the first source tag; a <g> and its </g> share the id, hence the name.
const inheritSourceIndexes = (tagRange, sourceTagMap) =>
  tagRange.map((tag) => {
    const tagSource = tag.data.id
      ? sourceTagMap.find(
          ({data}) => data.id === tag.data.id && data.name === tag.data.name,
        )
      : undefined
    return {
      ...tag,
      data: {
        ...tag.data,
        ...(tagSource?.data?.index !== undefined && {
          index: tagSource.data.index,
          tagRole: tagSource.data.tagRole,
        }),
      },
    }
  })

// A `&lt;` or `&gt;` outside the tags of the segment string is text the translator typed, never markup: it is laid
// out as one of these instead, so it can neither start nor end a tag. One character each, so the offsets are those of
// the decoded string. A marker is a high surrogate with no low surrogate after it: well-formed text holds these two
// only as the first half of a pair (U+10F800..U+10FFFF), and a marker is never followed by a low surrogate, as it
// takes the place of an entity, after which well-formed text never starts with one.
const TYPED_LT = '\uDBFE'
const TYPED_GT = '\uDBFF'
const TYPED_CHARS = /[\uDBFE\uDBFF](?![\uDC00-\uDFFF])/
const ENTITIES = /&lt;|&gt;|&amp;/g
const typedChar = (entity) =>
  entity === '&lt;' ? TYPED_LT : entity === '&gt;' ? TYPED_GT : '&'
const untype = (text) =>
  text.replace(new RegExp(TYPED_CHARS, 'g'), (char) =>
    char === TYPED_LT ? '<' : '>',
  )

/**
 * The segment string decoded, with the typed `<` and `>` as TYPED_LT and TYPED_GT, and its tags, with their offsets in
 * the decoded string. Tags are found in the string as stored, where the text the translator typed is still escaped:
 * `&lt;g id="1"&gt;` typed in the editor stays text. A tag's own markup is decoded as a whole, as it always was.
 */
const decodeAndMatchTags = (segment) => {
  const tagSpans = matchTag(segment)
    .filter(({data}) => data.name !== 'space')
    .map(({offset, length}) => [offset, offset + length])
    .sort((a, b) => a[0] - b[0])
  let markupText = ''
  let last = 0
  tagSpans.forEach(([start, end]) => {
    if (end <= last) return
    if (start > last)
      markupText += segment.slice(last, start).replace(ENTITIES, typedChar)
    markupText += decodeHtmlEntities(segment.slice(Math.max(start, last), end))
    last = end
  })
  markupText += segment.slice(last).replace(ENTITIES, typedChar)
  const tagRange = matchTag(markupText).filter(
    ({data}) => data.name !== 'space' && !TYPED_CHARS.test(data.encodedText),
  )
  // Blanks are still found in the decoded text: one between a typed `<` and `>` is not a space tag, as before
  if (tagSignatures.space)
    tagRange.push(...findTagWithRegex(untype(markupText), 'space'))
  return {markupText, tagRange}
}

/**
 * The segment string in order, as text runs (`{text}`, decoded, typed `<` and `>` marked), tags (`{tag}`) and line
 * break placeholders (`{lineBreak}`), and the tags the store keeps (`tagRange`, the error-checked ones, with their
 * offsets in the decoded string). A tag starting inside the one before it is left out.
 */
const piecesOf = (segment = '', sourceTagMap) => {
  const {markupText, tagRange} = decodeAndMatchTags(segment)
  const lineBreakTags = getSplitBlockTag()
  tagRange.sort((a, b) => a.offset - b.offset)
  const indexedTagRange = addIncrementalIndex(
    sourceTagMap?.length && tagRange.length
      ? inheritSourceIndexes(tagRange, sourceTagMap)
      : tagRange,
  )
  const pieces = []
  let last = 0
  indexedTagRange.forEach((tag) => {
    const start = tag.offset
    if (start < last) return
    if (start > last) pieces.push({text: markupText.slice(last, start)})
    pieces.push(
      lineBreakTags.includes(tag.data.name) ? {lineBreak: tag} : {tag},
    )
    last = start + tag.data.encodedText.length
  })
  if (last < markupText.length) pieces.push({text: markupText.slice(last)})
  return {
    pieces,
    tagRange: indexedTagRange.filter((tag) =>
      getErrorCheckTag().includes(tag.data.name),
    ),
  }
}

const isCarriageReturn = (piece) =>
  piece?.lineBreak?.data.name === 'carriageReturn'
const isLineFeed = (piece) => piece?.lineBreak?.data.name === 'lineFeed'

/**
 * The plain text: each tag as its placeholder, each line break as one `\n` (a CR LF pair too, written in the text or
 * as placeholders).
 */
const plainTextOf = (pieces) =>
  untype(
    removeZeroWidthSpace(
      pieces
        .map((piece, i) => {
          if (piece.text !== undefined)
            return piece.text.replace(/\r\n|\r/g, '\n')
          if (piece.tag) return piece.tag.data.placeholder
          return isCarriageReturn(piece) && isLineFeed(pieces[i + 1])
            ? ''
            : '\n'
        })
        .join(''),
    ),
  )

// A line break written in the text itself, as its placeholder: the editor reads a `\r` as a carriage return too.
const LINE_BREAK_CHARS = /\r\n|\r|\n/g
const lineBreakPlaceholders = (text) =>
  text.replace(LINE_BREAK_CHARS, (lineBreak) =>
    lineBreak === '\n'
      ? config.lfPlaceholder
      : lineBreak === '\r'
        ? config.crPlaceholder
        : config.crPlaceholder + config.lfPlaceholder,
  )

/**
 * The segment string the store keeps: the tags and line breaks as their markup, the other text HTML-encoded. Text the
 * translator typed stays encoded even where it looks like a tag. A carriage return stays one: the editor shows it as
 * one, and the Lexiqa text built from this string must have as many line breaks as the editor text.
 */
const decodedSegmentOf = (pieces) => {
  // Nothing but zero-width spaces: kept as they are
  if (
    pieces.every(({text}) => text !== undefined && !text.replace(/\u200B/g, ''))
  )
    return untype(pieces.map(({text}) => text).join(''))
  return pieces
    .map((piece) =>
      piece.text !== undefined
        ? encodeText(lineBreakPlaceholders(piece.text))
        : toSegmentForm((piece.tag ?? piece.lineBreak).data.encodedText),
    )
    .join('')
}

// Editor text back to segment string form: line breaks become the line feed placeholder, zero-width spaces go.
const toSegmentForm = (text) =>
  text.replace(/\n/g, config.lfPlaceholder).replace(/\u200B/g, '')

// An XLIFF tag the segment string carries as markup without the editor showing it as a tag (a <ph> with no
// equiv-text, an <mrk>): kept as is. Its name ends at a word boundary and its attributes are quoted.
const XLIFF_MARKUP =
  /<\/?(?:g|x|bx|ex|bpt|ept|ph|it|mrk)\b(?:\s+[\w:.-]+\s*=\s*(?:"[^"<]*"|'[^'<]*'))*\s*\/?>/gi

// Text of the editor in segment string form, HTML-encoded but for the XLIFF markup it carries. Typed characters are
// never markup: TYPED_LT and TYPED_GT are encoded.
const encodeText = (text) => {
  const segmentForm = toSegmentForm(text)
  let encoded = ''
  let last = 0
  for (const {0: markup, index} of segmentForm.matchAll(XLIFF_MARKUP)) {
    encoded +=
      encodeHtmlEntities(untype(segmentForm.slice(last, index))) +
      untype(markup)
    last = index + markup.length
  }
  return encoded + encodeHtmlEntities(untype(segmentForm.slice(last)))
}

// A tag as the store keeps it: its offset in the decoded segment (the order of the tag maps) and what the tag menu,
// the missing tag check and the source index inheritance read. `index` and `tagRole` only for a numbered tag.
const storeTag = ({
  offset,
  data: {id, name, encodedText, decodedText, placeholder, index, tagRole},
}) => ({
  offset,
  data: {
    id,
    name,
    encodedText,
    decodedText,
    placeholder,
    ...(index !== undefined && {index}),
    ...(tagRole !== undefined && {tagRole}),
  },
})

/**
 * The tags of the translation, in order (nbsp and tab included, line breaks not). A target tag carries no index: only
 * the source tags are numbered for the store.
 */
const tagMapOf = (pieces) =>
  pieces
    .filter(({tag}) => tag)
    .map(({tag}) => {
      const {originalOffset, id, name, encodedText, decodedText, placeholder} =
        tag.data
      return storeTag({
        offset: originalOffset,
        data: {id, name, encodedText, decodedText, placeholder},
      })
    })

/**
 * What SegmentActions.updateSource takes for `source` (a segment string): the decoded segment, the plain text, the
 * tag range and the Lexiqa text.
 */
export const sourceStoreData = (source) => {
  const {pieces, tagRange} = piecesOf(source)
  const decodedSegment = decodedSegmentOf(pieces)
  return {
    decodedSegment,
    plainText: plainTextOf(pieces),
    tagRange: tagRange.map(storeTag),
    lxqText: prepareTextForLexiqa(decodedSegment),
  }
}

/**
 * What SegmentActions.updateTranslation takes for `translation` (a segment string): the plain text, the source tags
 * missing from it and the Lexiqa text. `tagMap`, the tags of the translation, is what the missing tags are computed
 * from.
 */
export const translationUpdate = (translation, sourceTagMap) => {
  const {pieces} = piecesOf(translation, sourceTagMap)
  const tagMap = tagMapOf(pieces)
  const {missingTags} = checkForMissingTags(sourceTagMap, tagMap)
  return {
    plainText: plainTextOf(pieces),
    tagMap,
    missingTags: missingTags.map(storeTag),
    lxqDecodedTranslation: prepareTextForLexiqa(decodedSegmentOf(pieces)),
  }
}
/** The character counter of `translation`; `countTags`: the project counts g, bx, ex and x tags as characters. */
export const charactersCounter = (translation, countTags) =>
  getCharactersCounter(
    countTags
      ? excludeSomeTagsTransformToText(translation, ['g', 'bx', 'ex', 'x'])
      : decodePlaceholdersToPlainText(removeTagsFromText(translation)),
  )

/**
 * The id and placeholder of every error-checked tag of `segment`, in document order, with its markup:
 * `[{raw, id, placeholder}]`. `sourceTagMap` (for a translation) gives the tags their source index.
 */
export const tagIdentities = (segment, sourceTagMap) =>
  piecesOf(segment, sourceTagMap)
    .tagRange.slice()
    .sort((a, b) => a.offset - b.offset)
    .map(({data}) => ({
      raw: data.encodedText,
      id: data.id,
      placeholder: data.placeholder,
    }))
