// Undo history. Tag-ness lives in the `tagRanges` field, not in the characters, so a transaction that deletes a tag
// records an `addTags` effect for its inverse: undo and redo restore the tag as well as its markup.
import {history, invertedEffects} from '@codemirror/commands'
import {addTags, tagList} from './docModel'

export const restoreDeletedTags = invertedEffects.of((tr) => {
  if (!tr.docChanged) return []
  const lost = tagList(tr.startState).filter(
    (tag) => tr.changes.mapPos(tag.from, 1) >= tr.changes.mapPos(tag.to, -1),
  )
  return lost.length ? [addTags.of(lost)] : []
})

export const historyExtensions = () => [history(), restoreDeletedTags]
