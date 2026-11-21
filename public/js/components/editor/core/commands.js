// Edits of the text between tags, which leave every tag as it is: a transform of the selected text (the toolbar's
// upper/lower case) and the replace of one search occurrence.
import {tagList} from './docModel'

/** The stretches of from..to that are not tags, as [{from, to}]. */
export const textRuns = (state, from = 0, to = state.doc.length) => {
  const runs = []
  let at = from
  for (const tag of tagList(state, from, to)) {
    if (tag.to <= at || tag.from >= to) continue
    if (tag.from > at) runs.push({from: at, to: tag.from})
    at = tag.to
  }
  if (at < to) runs.push({from: at, to})
  return runs
}

// The text character before `pos`, tags skipped; '' at the start of the document.
const charBefore = (state, pos) => {
  for (const tag of tagList(state, 0, pos).reverse()) {
    if (tag.to === pos) pos = tag.from
  }
  return pos > 0 ? state.sliceDoc(pos - 1, pos) : ''
}

/**
 * Transaction spec replacing the text of the selection, run by run between tags, with `fn(text)`; the selection
 * still covers it. Null when the selection is empty.
 *
 * A run after a tag continues the text before the tag (a tag can sit inside a word), so it is transformed together
 * with the character before the tag, which is then dropped: capitalize starts no word there. The selection's own
 * start is a start, as in the Draft editor.
 */
export const transformSelectionSpec = (state, fn) => {
  const sel = state.selection.main
  if (sel.empty) return null
  const transform = (from, to) => {
    const text = state.sliceDoc(from, to)
    const before = from === sel.from ? '' : charBefore(state, from)
    return before ? fn(before + text).slice(fn(before).length) : fn(text)
  }
  const changes = state.changes(
    textRuns(state, sel.from, sel.to).map(({from, to}) => ({
      from,
      to,
      insert: transform(from, to),
    })),
  )
  const map = (pos) => changes.mapPos(pos, pos === sel.from ? -1 : 1)
  return {changes, selection: {anchor: map(sel.anchor), head: map(sel.head)}}
}

// A character no term matches: by default a tag stands for it, so a match never runs across a tag.
const UNMATCHABLE = '\uFFFC'

/**
 * Transaction spec replacing the `index`-th match of `regex` (counted from 0 through the document) with `text`. Each
 * tag is searched as `tagText(its markup)`, so a match inside a tag takes its number; it is never replaced, a tag being
 * locked. Null when there is no such match, or when it touches a tag.
 */
export const replaceOccurrenceSpec = (
  state,
  regex,
  index,
  text,
  tagText = () => UNMATCHABLE,
) => {
  const global = new RegExp(
    regex.source,
    regex.flags.includes('g') ? regex.flags : regex.flags + 'g',
  )
  // The searched text, and the document position of each of its characters: null for those of a tag.
  let searched = ''
  const posOf = []
  const add = (str, from) => {
    searched += str
    for (let i = 0; i < str.length; i++)
      posOf.push(from === null ? null : from + i)
  }
  let at = 0
  for (const tag of tagList(state)) {
    add(state.sliceDoc(at, tag.from), at)
    add(tagText(state.sliceDoc(tag.from, tag.to)), null)
    at = tag.to
  }
  add(state.sliceDoc(at), at)
  const match = [...searched.matchAll(global)].filter((m) => m[0].length)[index]
  if (!match) return null
  // A match in one stretch of text has consecutive positions; one in a tag, or across one, has not.
  const positions = posOf.slice(match.index, match.index + match[0].length)
  const from = positions[0]
  if (from === null || positions.some((pos, i) => pos !== from + i)) return null
  return {changes: {from, to: from + match[0].length, insert: text}}
}
