// The document model of the segment editor.
//
// The CodeMirror document is the text the translator sees: entities decoded (`&amp;` is one `&`), a line feed a real
// "\n", an nbsp a real U+00A0, a tab a real "\t", a word joiner a real U+2060. Every other tag keeps its raw markup
// in the document, and the `tagRanges` field records which stretches of the document are tags. Only those stretches
// are tags: markup that is typed or pasted as plain text stays text, and is stored encoded (`&lt;x id="9"/&gt;`).
//
// Tag rules come from segmentView/segmentModel only.
import {StateEffect, StateField} from '@codemirror/state'
import {Decoration} from '@codemirror/view'
import {
  decodeEntities,
  encodeText,
  findTagMarkup,
  markableText,
  OPAQUE_TAG,
  tokenize,
} from '../../segmentView/segmentModel'
import {CHARACTER_TAGS} from '../../../utils/segmentText/specialChars'

export const LF = '##$_0A$##'
export const NBSP = '##$_A0$##'
export const TAB = '##$_09$##'
export const CR = '##$_0D$##'

// Placeholders the document holds as the character they stand for. The word joiner is already that character in the
// segment string, so it serialises as itself and needs no placeholder.
const CHAR_FOR_TAG = CHARACTER_TAGS
const PLACEHOLDER_FOR_CHAR = {'\n': LF, '\u00a0': NBSP, '\t': TAB}
const PLACEHOLDER_CHARS = /[\n\u00a0\t]/g
const WORD_JOINER = CHAR_FOR_TAG.wordJoiner

/**
 * The tags of a segment string with their raw offsets, in document order. XLIFF markup that `tokenize` does not
 * know (a `<ph>` without equiv-text, `<bpt>`, `<mrk>`…) is a tag too, with no token: it is kept as it is, as Draft
 * does. `space` tokens are not tags here: a space is text, shown by the display layer only.
 * @returns {Array<{token: object|null, from: number, to: number}>}
 */
export const locateTags = (raw, {isRtl = false} = {}) => {
  const out = []
  let at = 0
  const unknownUpTo = (end) => {
    for (const r of findTagMarkup(raw.slice(at, end))) {
      out.push({token: null, from: at + r.from, to: at + r.to})
    }
  }
  for (const token of tokenize(raw, {isRtl})) {
    if (token.type !== 'tag' || token.name === 'space') continue
    const from = raw.indexOf(token.raw, at)
    if (from < 0) continue
    unknownUpTo(from)
    at = from + token.raw.length
    out.push({token, from, to: at})
  }
  unknownUpTo(raw.length)
  return out
}

/**
 * Segment string -> document content.
 * @returns {{text: string, tags: Array<{from: number, to: number}>}} `tags` are the tag ranges in `text`
 */
export const toDoc = (raw) => {
  // A bare carriage return would be a line break in the document: it is the carriage return tag.
  raw = (raw ?? '').replace(/\r/g, CR)
  let text = ''
  const tags = []
  let at = 0
  for (const {token, from, to} of locateTags(raw)) {
    text += decodeEntities(raw.slice(at, from))
    at = to
    const char = token && CHAR_FOR_TAG[token.name]
    if (char) {
      text += char
    } else {
      tags.push({from: text.length, to: text.length + (to - from)})
      text += raw.slice(from, to)
    }
  }
  text += decodeEntities(raw.slice(at))
  return {text, tags}
}

// ---------- tag ranges ----------

/** Adds tag ranges ({from, to} in the new document). Emitted by `insertRaw` only. */
export const addTags = StateEffect.define({
  map: (list, mapping) =>
    list
      .map((r) => ({
        from: mapping.mapPos(r.from, 1),
        to: mapping.mapPos(r.to, -1),
      }))
      .filter((r) => r.to > r.from),
})

const tagMark = Decoration.mark({})

export const tagRangeSet = (tags) =>
  Decoration.set(
    tags.filter((r) => r.to > r.from).map((r) => tagMark.range(r.from, r.to)),
    true,
  )

/**
 * Which stretches of the document are tags. Mapped through every change (a fully deleted tag disappears, text
 * inserted at its edge stays outside it); ranges are added only by the `addTags` effect.
 */
export const tagRanges = StateField.define({
  create: () => Decoration.none,
  update(value, tr) {
    let next = value.map(tr.changes)
    for (const effect of tr.effects) {
      if (effect.is(addTags) && effect.value.length) {
        next = next.update({
          add: effect.value
            .filter((r) => r.to > r.from)
            .map((r) => tagMark.range(r.from, r.to)),
          sort: true,
        })
      }
    }
    return next
  },
})

/** The tag ranges of a state as a plain array. */
export const tagList = (state, from = 0, to = state.doc.length) => {
  const out = []
  state.field(tagRanges).between(from, to, (f, t) => {
    out.push({from: f, to: t})
  })
  return out
}

/** Grows a range so it never splits a tag. */
export const snapToTags = (state, from, to) => {
  state.field(tagRanges).between(from, to, (f, t) => {
    if (f < from && t > from) from = f
    if (f < to && t > to) to = t
  })
  return {from, to}
}

/** The tag range strictly containing `pos`, or null. */
export const tagAround = (state, pos) => {
  let found = null
  state.field(tagRanges).between(pos, pos, (f, t) => {
    if (f < pos && pos < t) found = {from: f, to: t}
  })
  return found
}

// ---------- serialisation ----------

const encodeDocText = (text) =>
  encodeText(text).replace(PLACEHOLDER_CHARS, (ch) => PLACEHOLDER_FOR_CHAR[ch])

/**
 * Document range -> segment string, with the raw offset of every tag range in it.
 * @returns {{raw: string, tagAt: Array<{from: number, to: number, raw: number}>}}
 */
export const serializeWithOffsets = (
  state,
  from = 0,
  to = state.doc.length,
) => {
  let raw = ''
  const tagAt = []
  let at = from
  for (const tag of tagList(state, from, to)) {
    if (tag.from < from || tag.to > to) continue
    raw += encodeDocText(state.sliceDoc(at, tag.from))
    tagAt.push({...tag, raw: raw.length})
    raw += state.sliceDoc(tag.from, tag.to)
    at = tag.to
  }
  raw += encodeDocText(state.sliceDoc(at, to))
  return {raw, tagAt}
}

/** Document range -> segment string (tags raw, text HTML-encoded, "\n"/nbsp/tab as placeholders). */
export const serialize = (state, from, to) =>
  serializeWithOffsets(state, from, to).raw

/**
 * Transaction spec putting document content (`toDoc` shape) at from..to, grown to whole tags; the caret goes after
 * it, or with `select` the content is selected.
 */
export const insertContent = (
  state,
  from,
  to,
  {text, tags},
  select = false,
) => {
  ;({from, to} = snapToTags(state, from, to))
  return {
    changes: {from, to, insert: text},
    effects: addTags.of(
      tags.map((r) => ({from: from + r.from, to: from + r.to})),
    ),
    selection: select
      ? {anchor: from, head: from + text.length}
      : {anchor: from + text.length},
  }
}

/**
 * Transaction spec putting a segment string (tags and all) at from..to. Every raw-string path goes through here, so
 * only these strings make tags.
 */
export const insertRaw = (state, from, to, raw) =>
  insertContent(state, from, to, toDoc(raw))

/**
 * The text `marks` offsets refer to (segmentModel `markableText`: every tag one character) and, per character, its
 * document position. A tag's character sits at the tag's start. The word joiner is a character here but a tag to
 * segmentModel, so it is `OPAQUE_TAG` too.
 * @returns {{text: string, pos: number[]}}
 */
export const visibleDoc = (state) => {
  const doc = state.doc.toString()
  let text = ''
  const pos = []
  let i = 0
  const take = (end) => {
    for (; i < end; i++) {
      text += doc[i] === WORD_JOINER ? OPAQUE_TAG : doc[i]
      pos.push(i)
    }
  }
  for (const tag of tagList(state)) {
    take(tag.from)
    text += markableText(doc.slice(tag.from, tag.to)) || OPAQUE_TAG
    pos.push(tag.from)
    i = tag.to
  }
  take(doc.length)
  return {text, pos}
}

/**
 * The selected part of the document: as a segment string, and as plain text with the tags left out.
 * @returns {{textWithTags: string, textWithoutTags: string}}
 */
export const selectionText = (state) => {
  const sel = state.selection.main
  const {from, to} = snapToTags(state, sel.from, sel.to)
  let textWithoutTags = ''
  let at = from
  for (const tag of tagList(state, from, to)) {
    if (tag.from < from || tag.to > to) continue
    textWithoutTags += state.sliceDoc(at, tag.from)
    at = tag.to
  }
  textWithoutTags += state.sliceDoc(at, to)
  return {textWithTags: serialize(state, from, to), textWithoutTags}
}
