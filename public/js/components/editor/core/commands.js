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

/**
 * Transaction spec replacing the text of the selection, run by run between tags, with `fn(text)`; the selection
 * still covers it. Null when the selection is empty.
 */
export const transformSelectionSpec = (state, fn) => {
  const sel = state.selection.main
  if (sel.empty) return null
  const changes = state.changes(
    textRuns(state, sel.from, sel.to).map(({from, to}) => ({
      from,
      to,
      insert: fn(state.sliceDoc(from, to)),
    })),
  )
  const map = (pos) => changes.mapPos(pos, pos === sel.from ? -1 : 1)
  return {changes, selection: {anchor: map(sel.anchor), head: map(sel.head)}}
}

/**
 * Transaction spec replacing the `index`-th match of `regex` in the text between tags (counted from 0 through the
 * document) with `text`. Null when there is no such match.
 */
export const replaceOccurrenceSpec = (state, regex, index, text) => {
  const global = new RegExp(
    regex.source,
    regex.flags.includes('g') ? regex.flags : regex.flags + 'g',
  )
  let n = 0
  for (const run of textRuns(state)) {
    const str = state.sliceDoc(run.from, run.to)
    global.lastIndex = 0
    let match
    while ((match = global.exec(str)) !== null) {
      if (!match[0].length) {
        global.lastIndex++
        continue
      }
      if (n++ === index) {
        const from = run.from + match.index
        return {changes: {from, to: from + match[0].length, insert: text}}
      }
    }
  }
  return null
}
