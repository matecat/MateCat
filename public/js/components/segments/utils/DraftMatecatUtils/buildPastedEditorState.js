import DraftMatecatUtils from './index'
import {tagSignatures} from './tagModel'

/**
 * Works out what a paste should put in the editor.
 *
 * Two kinds of paste reach here. One is a copy made inside the editor, which
 * the store kept as a fragment so the tag entities survive the round trip; it
 * is recognised by its plain text still matching what the system clipboard
 * holds. The other is a copy from anywhere else, which arrives as text and has
 * to have its tags stripped and its special characters turned back into tag
 * placeholders.
 *
 * @returns the editor state to apply, or null to let Draft paste plain text
 *          itself -- which is also the answer when the saved fragment cannot be
 *          parsed
 */
export default function buildPastedEditorState({
  text,
  clipboardFragment,
  clipboardPlainText,
  editorState,
}) {
  const sameAsStoredCopy =
    clipboardFragment &&
    text &&
    clipboardPlainText.replace(/\n/g, '') === text.replace(/\n/g, '')

  if (sameAsStoredCopy) {
    try {
      const fragmentContent = JSON.parse(clipboardFragment)
      const fragment = DraftMatecatUtils.buildFragmentFromJson(
        fragmentContent.orderedMap,
      )
      return DraftMatecatUtils.duplicateFragment(
        fragment,
        editorState,
        fragmentContent.entitiesMap,
      )
    } catch (e) {
      return null
    }
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
