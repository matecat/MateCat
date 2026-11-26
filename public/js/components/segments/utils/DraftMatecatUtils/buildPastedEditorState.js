import DraftMatecatUtils from './index'
import {tagSignatures} from './tagModel'
import {readSegmentClipboard} from '../../../segmentView/segmentModel'

/**
 * Works out what a paste should put in the editor.
 *
 * A copy made in Matecat -- in the editors or in a SegmentView (matches,
 * concordance, closed segments...) -- carries the raw segment slice in its
 * html; its tags, once validated, are inserted as tag entities, as when a
 * segment is loaded.
 *
 * A copy from anywhere else arrives as text and has its tags stripped and its
 * special characters turned back into tag placeholders.
 *
 * @returns the editor state to apply, or null to let Draft paste plain text
 *          itself
 */
export default function buildPastedEditorState({text, html, editorState}) {
  const segmentSlice = readSegmentClipboard(html)
  if (segmentSlice !== null) {
    const fragment = DraftMatecatUtils.buildFragmentFromText(segmentSlice)
    if (!fragment) return editorState
    return DraftMatecatUtils.duplicateFragment(fragment, editorState)
  }

  if (!text) return null

  // An external copy: drop any tags it carried, then put back the two
  // characters that stand for tags of our own.
  let cleanText = DraftMatecatUtils.removeTagsFromText(text)
  cleanText = cleanText
    .replace(/°/gi, tagSignatures['nbsp'].encodedPlaceholder)
    .replace(/\t/gi, tagSignatures['tab'].encodedPlaceholder)

  // Text made only of tags cleans to '', whose fragment is null: nothing to insert.
  const fragment = DraftMatecatUtils.buildFragmentFromText(cleanText)
  if (!fragment) return editorState

  return DraftMatecatUtils.duplicateFragment(fragment, editorState)
}
