import {EditorState, Modifier} from 'draft-js'
import DraftMatecatUtils from './index'
import getFragmentFromSelection from './DraftSource/src/component/handlers/edit/getFragmentFromSelection'

/**
 * Works out what a drop should do to the editor.
 *
 * Two cases, told apart by where the drag started. A drag from outside the
 * editor arrives as JSON on the dataTransfer and is duplicated in. A drag
 * within the editor has to be done by hand -- Draft's own behaviour does not
 * work here -- by cutting the dragged range out and putting the fragment back
 * at the drop point.
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
  text,
  draggingFromEditArea,
}) {
  const dragSelection = editorState.getSelection()
  const dragSelectionLength =
    dragSelection.focusOffset - dragSelection.anchorOffset
  // Draft's own fragment does not survive this, so rebuild it
  const tempFrag = DraftMatecatUtils.buildFragmentFromJson(
    getFragmentFromSelection(editorState),
  )

  let atDropPoint = EditorState.forceSelection(editorState, selection)

  const {entityKey} = DraftMatecatUtils.selectionIsEntity(atDropPoint)
  if (entityKey) return {outcome: 'handled'}

  if (text && !draggingFromEditArea) {
    try {
      const fragmentContent = JSON.parse(text)
      return {
        outcome: 'handled',
        editorState: DraftMatecatUtils.duplicateFragment(
          DraftMatecatUtils.buildFragmentFromJson(fragmentContent.orderedMap),
          atDropPoint,
          fragmentContent.entitiesMap,
        ),
      }
    } catch (err) {
      return {outcome: 'not-handled'}
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
    const movedForwardInSameBlock =
      dragSelection.anchorOffset < selection.anchorOffset &&
      dragSelection.getAnchorKey() === selection.getAnchorKey()
    const dropAt = movedForwardInSameBlock
      ? selection.merge({
          anchorOffset: selection.anchorOffset - dragSelectionLength,
          focusOffset: selection.focusOffset - dragSelectionLength,
        })
      : selection

    contentState = Modifier.replaceWithFragment(
      contentState,
      dropAt,
      tempFrag,
    )

    atDropPoint = EditorState.push(
      atDropPoint,
      contentState,
      'insert-fragment',
    )
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
