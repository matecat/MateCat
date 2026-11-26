import {EditorState, Modifier} from 'draft-js'
import DraftMatecatUtils from './index'
import {readSegmentClipboard} from '../../../segmentView/segmentModel'

/**
 * Works out what a drop should do to the editor.
 *
 * A drag carries the raw segment slice in its html, as a copy does (see
 * segmentView/README.md, Copy). A drag from outside the editor is inserted as
 * a paste is. A drag within the editor has to be done by hand -- Draft's own
 * behaviour does not work here -- by cutting the dragged range out and putting
 * the slice back at the drop point. A drag that carries no slice (text from
 * another page) is left to Draft.
 *
 * Nothing may be dropped onto an entity; that is reported as handled so the
 * editor is left alone.
 *
 * @returns {{
 *   outcome: string,        'handled' or 'not-handled', for Draft
 *   editorState?: object,   the state to apply, absent when nothing changes
 *   highlightTags?: boolean tags need re-highlighting after the move
 * }}
 */
export default function resolveDrop({
  editorState,
  selection,
  html,
  draggingFromEditArea,
}) {
  const dragSelection = editorState.getSelection()

  let atDropPoint = EditorState.forceSelection(editorState, selection)

  const {entityKey} = DraftMatecatUtils.selectionIsEntity(atDropPoint)
  if (entityKey) return {outcome: 'handled'}

  const slice = readSegmentClipboard(html)
  if (slice === null) return {outcome: 'not-handled'}

  const fragment = DraftMatecatUtils.buildFragmentFromText(slice)
  if (!fragment) return {outcome: 'handled'}

  if (!draggingFromEditArea) {
    return {
      outcome: 'handled',
      editorState: DraftMatecatUtils.duplicateFragment(fragment, atDropPoint),
    }
  }

  try {
    let contentState = Modifier.removeRange(
      atDropPoint.getCurrentContent(),
      dragSelection,
      dragSelection.isBackward ? 'backward' : 'forward',
    )

    // Cutting the dragged range out shifts everything after it left, so a drop
    // further along the same block has to come back by the length removed.
    const dragSelectionLength =
      dragSelection.getEndOffset() - dragSelection.getStartOffset()
    const movedForwardInSameBlock =
      dragSelection.getStartOffset() < selection.anchorOffset &&
      dragSelection.getStartKey() === selection.getAnchorKey()
    const dropAt = movedForwardInSameBlock
      ? selection.merge({
          anchorOffset: selection.anchorOffset - dragSelectionLength,
          focusOffset: selection.focusOffset - dragSelectionLength,
        })
      : selection

    contentState = Modifier.replaceWithFragment(contentState, dropAt, fragment)

    atDropPoint = EditorState.push(atDropPoint, contentState, 'insert-fragment')
    return {
      outcome: 'handled',
      editorState: EditorState.forceSelection(atDropPoint, dropAt),
      highlightTags: true,
    }
  } catch (err) {
    console.log(err)
    return {outcome: 'not-handled'}
  }
}
