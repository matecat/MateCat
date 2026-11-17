// What the CodeMirror editors publish to the segment store, computed from the segment string alone: the decoded
// segment, the plain text, the tag map, the missing tags, the Lexiqa text and the character counter. The store, the
// QA warnings and the tag menu rely on these exact values (a tag keeps only the fields they read, see storeTag), and
// __golden__/segmentStoreData.json pins them, edge cases included. The text model below splits the segment into lines
// at its line breaks and places each tag as one unit inside a line: a tag only counts when its placeholder fits inside
// one line, a tag cut by a later range loses all its characters, and a line break inside a tag's own text never splits.
import matchTag from './matchTag'
import {getErrorCheckTag, getSplitBlockTag} from './tagModel'
import {createTagNumberer} from './compressibleTagUtils'
import {
  decodeHtmlEntities,
  decodePlaceholdersToPlainText,
  excludeSomeTagsTransformToText,
  removeTagsFromText,
  removeZeroWidthSpace,
} from './tagUtils'
import {encodeEditorText} from './encodeEditorText'
import prepareTextForLexiqa from './prepareTextForLexiqa'
import checkForMissingTags from './checkForMissingTag'
import {formatText, getCharactersCounter} from './textUtils'

export {formatText}

const ZWSP = '\u200B'
// The line breaks of a segment string; each one splits the editor text into a new block.
const CRLF_PLACEHOLDER = '##$_0D$####$_0A$##'
const LF_PLACEHOLDER = '##$_0A$##'
const CR_PLACEHOLDER = '##$_0D$##'

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

// The editor text is a list of blocks (lines); `ents[i]` is the index in `entities` of the tag covering character
// `i` of the block, or null.
const makeBlock = (text) => ({text, ents: new Array(text.length).fill(null)})

// The maximal runs of characters covered by one same entity, in order: [{start, end, entity}].
const entityRuns = ({ents}) => {
  const runs = []
  let i = 0
  while (i < ents.length) {
    const entity = ents[i]
    let end = i + 1
    while (end < ents.length && ents[end] === entity) end++
    if (entity !== null) runs.push({start: i, end, entity})
    i = end
  }
  return runs
}

// An edit at `offset` that lands inside a tag (the characters on both sides belong to it) strips the whole tag of its
// entity: tags are immutable, they are never cut in two.
const releaseEntityAt = (block, offset) => {
  const before = offset > 0 ? block.ents[offset - 1] : null
  const after = offset < block.ents.length ? block.ents[offset] : null
  if (after === null || after !== before) return
  let range
  entityRuns(block).forEach((run) => {
    if (run.entity === after && run.start <= offset && run.end >= offset)
      range = run
  })
  for (let i = range.start; i < range.end; i++) block.ents[i] = null
}

const releaseEntitiesAtEdges = (block, start, end) => {
  releaseEntityAt(block, start)
  releaseEntityAt(block, end)
}

// Removes [start, end) from the block; a range that starts and ends inside the same tag removes the whole tag.
// Returns the offset the removal collapsed to.
const removeRange = (block, start, end) => {
  const startEntity = block.ents[start] ?? null
  const endEntity = block.ents[end - 1] ?? null
  if (startEntity !== null && startEntity === endEntity) {
    const runs = entityRuns(block).filter(
      (run) =>
        run.entity === startEntity && start <= run.end && start >= run.start,
    )
    if (runs.length !== 1)
      throw new Error(
        'There should only be one entity range within this removal range.',
      )
    start = runs[0].start
    end = runs[0].end
  } else {
    releaseEntitiesAtEdges(block, start, end)
  }
  block.text = block.text.slice(0, start) + block.text.slice(end)
  block.ents.splice(start, end - start)
  return start
}

// The line break placeholders left in the block become block breaks: a CR LF pair is one break.
const splitOnLineBreaks = (block) => {
  const splitPoints = []
  const text = block.text.replace(
    /##\$_0D\$####\$_0A\$##/g,
    (match, offset) => {
      splitPoints.push({offset, length: CRLF_PLACEHOLDER.length})
      // keeps the LF and CR searches below off the pair
      return '##$_CR$####$_LF$##'
    },
  )
  let match
  const lineFeed = /##\$(_0A)\$##/gi
  while ((match = lineFeed.exec(text)) !== null)
    splitPoints.push({offset: match.index, length: LF_PLACEHOLDER.length})
  const carriageReturn = /##\$(_0D)\$##/gi
  while ((match = carriageReturn.exec(text)) !== null)
    splitPoints.push({offset: match.index, length: CR_PLACEHOLDER.length})
  splitPoints.sort((a, b) => b.offset - a.offset)
  // A line break in a tag's placeholder is part of the tag, not a break of the segment
  const segmentBreaks = splitPoints.filter(
    ({offset}) => block.ents[offset] === null,
  )

  const breaks = []
  while (segmentBreaks.length > 0) {
    const point = segmentBreaks.pop()
    breaks.push(removeRange(block, point.offset, point.offset + point.length))
    segmentBreaks.forEach((other) => (other.offset -= point.length))
  }
  breaks.sort((a, b) => a - b)

  const blocks = []
  let tail = block
  let consumed = 0
  breaks.forEach((offset) => {
    const at = offset - consumed
    releaseEntityAt(tail, at)
    blocks.push({text: tail.text.slice(0, at), ents: tail.ents.slice(0, at)})
    tail = {text: tail.text.slice(at), ents: tail.ents.slice(at)}
    consumed = offset
  })
  blocks.push(tail)
  return blocks
}

/**
 * The segment string as the editor text: blocks, the tag entities covering them, and the tags the store keeps
 * (`tagRange`, the error-checked ones, with their offsets in the decoded string).
 */
const toEditorText = (segment = '', sourceTagMap) => {
  const text = decodeHtmlEntities(segment)
  const lineBreakTags = getSplitBlockTag()
  const tagRange = matchTag(text)
  tagRange.sort((a, b) => a.offset - b.offset)
  const indexedTagRange = addIncrementalIndex(
    sourceTagMap?.length && tagRange.length
      ? inheritSourceIndexes(tagRange, sourceTagMap)
      : tagRange,
  )

  // Every tag but the line breaks is replaced by its placeholder, between two zero-width spaces
  let plainText = text
  let slicedLength = 0
  const placed = []
  indexedTagRange.forEach((tag) => {
    const {name, placeholder, encodedText} = tag.data
    if (lineBreakTags.includes(name)) return
    const start = tag.offset - slicedLength
    const end = start + encodedText.length
    placed.push({start, tag})
    plainText =
      plainText.slice(0, start) +
      ZWSP +
      placeholder +
      ZWSP +
      plainText.slice(end)
    slicedLength += end - start - (placeholder.length + 2)
  })

  // Real line breaks split blocks too. Offsets keep counting one character per break, also for a CR LF. A line break
  // in a tag's placeholder is kept as is, as the editor's tag chip shows it (its label is the placeholder text): it
  // belongs to the tag, which stays whole on one line.
  const inPlaceholder = (offset) =>
    placed.some(
      ({start, tag}) =>
        offset > start && offset <= start + tag.data.placeholder.length,
    )
  const lines = ['']
  for (let i = 0; i < plainText.length; i++) {
    const char = plainText[i]
    if ((char !== '\r' && char !== '\n') || inPlaceholder(i)) {
      lines[lines.length - 1] += char
    } else {
      if (char === '\r' && plainText[i + 1] === '\n') i++
      lines.push('')
    }
  }
  const blocks = lines.map(makeBlock)
  const entities = []
  let maxCharsInBlocks = 0
  blocks.forEach((block, blockIndex) => {
    const blockLength = block.text.length
    maxCharsInBlocks += blockLength + (blockIndex !== 0 ? 1 : 0)
    const blockStart = maxCharsInBlocks - blockLength
    placed.forEach(({start, tag}) => {
      const from = start + 1
      const to = from + tag.data.placeholder.length
      if (
        from < maxCharsInBlocks &&
        to <= maxCharsInBlocks &&
        from >= blockStart &&
        !lineBreakTags.includes(tag.data.name)
      ) {
        const entity = entities.push({data: tag.data}) - 1
        releaseEntitiesAtEdges(block, from - blockStart, to - blockStart)
        for (let i = from - blockStart; i < to - blockStart; i++)
          block.ents[i] = entity
      }
    })
  })

  return {
    blocks: blocks.flatMap(splitOnLineBreaks),
    entities,
    tagRange: indexedTagRange.filter((tag) =>
      getErrorCheckTag().includes(tag.data.name),
    ),
  }
}

// An editor with a single block of zero-width spaces only counts as empty.
const hasText = (blocks) =>
  blocks.length > 1 || blocks[0].text.replace(/\u200B/g, '').length > 0

const editorPlainText = (blocks) => blocks.map(({text}) => text).join('\n')

/** The segment string the editor text stands for, each tag placeholder turned back into its markup. */
const decodedSegmentOf = ({blocks, entities}) => {
  if (!hasText(blocks)) return editorPlainText(blocks)
  const lines = blocks.map((block) => {
    let line = ''
    let last = 0
    entityRuns(block).forEach(({start, end, entity}) => {
      line += block.text.slice(last, start) + entities[entity].data.encodedText
      last = end
    })
    return line + block.text.slice(last)
  })
  return encodeEditorText(lines.join('\n'))
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
 * The tags in the editor text, in order (nbsp and tab included, line breaks not). A target tag carries no index: only
 * the source tags are numbered for the store.
 */
const tagMapOf = ({blocks, entities}) => {
  if (!hasText(blocks)) return []
  return blocks.flatMap((block) =>
    entityRuns(block).map(({entity}) => {
      const {originalOffset, id, name, encodedText, decodedText, placeholder} =
        entities[entity].data
      return storeTag({
        offset: originalOffset,
        data: {id, name, encodedText, decodedText, placeholder},
      })
    }),
  )
}

const plainTextOf = ({blocks}) => removeZeroWidthSpace(editorPlainText(blocks))

/**
 * What SegmentActions.updateSource takes for `source` (a segment string): the decoded segment, the plain text, the
 * tag range and the Lexiqa text.
 */
export const sourceStoreData = (source) => {
  const editorText = toEditorText(source)
  const decodedSegment = decodedSegmentOf(editorText)
  return {
    decodedSegment,
    plainText: plainTextOf(editorText),
    tagRange: editorText.tagRange.map(storeTag),
    lxqText: prepareTextForLexiqa(decodedSegment),
  }
}

/**
 * What SegmentActions.updateTranslation takes for `translation` (a segment string): the plain text, the source tags
 * missing from it and the Lexiqa text. `tagMap`, the tags of the translation, is what the missing tags are computed
 * from.
 */
export const translationUpdate = (translation, sourceTagMap) => {
  const editorText = toEditorText(translation, sourceTagMap)
  const tagMap = tagMapOf(editorText)
  const {missingTags} = checkForMissingTags(sourceTagMap, tagMap)
  return {
    plainText: plainTextOf(editorText),
    tagMap,
    missingTags: missingTags.map(storeTag),
    lxqDecodedTranslation: prepareTextForLexiqa(decodedSegmentOf(editorText)),
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
  toEditorText(segment, sourceTagMap)
    .tagRange.slice()
    .sort((a, b) => a.offset - b.offset)
    .map(({data}) => ({
      raw: data.encodedText,
      id: data.id,
      placeholder: data.placeholder,
    }))
