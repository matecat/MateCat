// Lexiqa warnings on the source and the target (activateLexiqa). Lexiqa checks the "Lexiqa text" of the segment (prepareTextForLexiqa:
// text decoded, each tag written as `<label>`, whitespace tags as their `lexiqaText`), so its offsets are aligned back
// to the document here: a document character is itself, or what its whitespace tag is written as, and every other tag
// spans whatever Lexiqa text lies between the text before it and the text after it.
import {cloneDeep} from 'lodash'
import LexiqaUtils from '../../../../utils/lxq.main'
import {
  getNoLexiqaTag,
  tagSignatures,
} from '../../../../utils/segmentText/tagModel'
import {CR} from '../../core/docModel'
import {chipTokens} from '../../core/tags'

export const LEXIQA_CLASS = 'lexiqahighlight'

// The whitespace tags the document holds as characters. A space is one only while visible spaces are on.
const TAG_FOR_CHAR = {' ': 'nbsp', '\t': 'tab', '\n': 'lineFeed', ' ': 'space'}

/** How the Lexiqa text writes a document character (transformTagsToLexiqaText). */
export const lexiqaChar = (ch) => {
  const signature = tagSignatures[TAG_FOR_CHAR[ch]]
  if (!signature?.replaceForLexiqa) return ch
  return signature.convertToLexiqaIgnoreAnglesBrackets
    ? signature.lexiqaText
    : `<${signature.lexiqaText}>`
}

// Where `run` (document text) ends when written from `at` in `text`, with each character's start; null if it differs.
const matchRun = (text, at, run) => {
  const starts = []
  let j = at
  for (const ch of run) {
    const written = lexiqaChar(ch)
    if (text.substr(j, written.length) !== written) return null
    starts.push(j)
    j += written.length
  }
  return {end: j, starts}
}

/**
 * Document position of every character of `lexiqaText` (a tag's characters all point at the tag), or null when the
 * two texts cannot be aligned (the Lexiqa text is stale).
 * @returns {number[]|null} length `lexiqaText.length`
 */
export const alignLexiqaText = (state, lexiqaText) => {
  const doc = state.doc.toString()
  const tags = chipTokens(state)
  const map = []
  let j = 0
  let at = 0
  const takeText = (end) => {
    const run = doc.slice(at, end)
    const match = matchRun(lexiqaText, j, run)
    if (!match) return false
    // A character outside the BMP is two positions in the document: `pos` steps by character, not by index.
    let pos = at
    ;[...run].forEach((ch, i) => {
      const next = i + 1 < match.starts.length ? match.starts[i + 1] : match.end
      for (let k = match.starts[i]; k < next; k++) map.push(pos)
      pos += ch.length
    })
    j = match.end
    at = end
    return true
  }
  for (let t = 0; t < tags.length; t++) {
    const {from, to, token} = tags[t]
    if (!takeText(from)) return null
    let end = -1
    if (token.raw === CR) {
      end = j + lexiqaChar('\n').length // written as a line break
    } else {
      // The tag ends at the first `>` followed by the text that follows it in the document.
      const nextFrom = t + 1 < tags.length ? tags[t + 1].from : doc.length
      const after = doc.slice(to, nextFrom)
      for (let k = j + 1; k <= lexiqaText.length && end < 0; k++) {
        if (lexiqaText[k - 1] !== '>') continue
        const fits =
          after === ''
            ? k === lexiqaText.length || t + 1 < tags.length
            : matchRun(lexiqaText, k, after) !== null
        if (fits) end = k
      }
    }
    if (end < 0 || end > lexiqaText.length) return null
    for (; j < end; j++) map.push(from)
    at = to
  }
  if (!takeText(doc.length) || j !== lexiqaText.length) return null
  return map
}

// Draft's canDecorateRange: a warning covering a tag Lexiqa must not check is not shown at all.
const coversNoLexiqaTag = (state, from, to) => {
  const excluded = getNoLexiqaTag()
  return chipTokens(state).some(
    (tag) =>
      tag.from < to && tag.to > from && excluded.includes(tag.token.name),
  )
}

/**
 * Lexiqa warnings (ranges from `LexiqaUtils.getRanges`, offsets in `lexiqaText`) as document ranges. Warnings that
 * cannot be placed are dropped.
 */
export const lexiqaDocRanges = (state, warnings, lexiqaText) => {
  if (!warnings?.length || !lexiqaText) return []
  const map = alignLexiqaText(state, lexiqaText)
  if (!map) return []
  const docEnd = (e) => (e < map.length ? map[e] : state.doc.length)
  return warnings
    .filter(({start, end}) => end > start && start < map.length)
    .map((warning) => ({
      warning,
      from: map[warning.start],
      to: docEnd(warning.end),
    }))
    .filter(({from, to}) => to > from && !coversNoLexiqaTag(state, from, to))
}

/**
 * The Lexiqa layer of the source (`lexiqa.source` over `lxqDecodedSource`) or, with `isSource` false, of the target
 * (`lexiqa.target` over `lxqDecodedTranslation`) of `segment`. Target warnings also carry Lexiqa's suggestions.
 */
export const lexiqaRanges = (state, segment, isSource = true) => {
  const {lexiqa, sid} = segment
  const results = isSource ? lexiqa?.source : lexiqa?.target
  const lexiqaText = isSource
    ? segment.lxqDecodedSource
    : segment.lxqDecodedTranslation
  if (!results || !Object.keys(results).length) return []
  const ranges = LexiqaUtils.getRanges(cloneDeep(results), lexiqaText, isSource)
  return lexiqaDocRanges(state, ranges, lexiqaText).map(
    ({from, to, warning}) => {
      const messages =
        warning.myClass && warning.errorid
          ? LexiqaUtils.buildTooltipMessages(warning, sid, isSource)
          : undefined
      return {
        from,
        to,
        className: LEXIQA_CLASS,
        ...(messages && warning.color
          ? {attributes: {style: `background-color: ${warning.color}`}}
          : {}),
        data: {type: 'lexiqa', messages},
      }
    },
  )
}
