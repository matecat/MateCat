import {EditorState, SelectionState} from 'draft-js'
import selectionIsEntity from './selectionIsEntity'

/**
 *
 * Replace the entire selection behavior made with left arrow or right arrow key (and optionally shiftKey).
 * When handling left or right arrow press, even if the command won't be handled in the end, the event fallback
 * behavior won't be called by the editor and the cursor remain at its initial position.
 * Here we check if there are entities to jump on key press or move the cursor accordingly to standard text-area behavior.
 *
 * @param editorState - initial EditorState
 * @param step - integer (-1 , 1) representing cursor step (backward or forward)
 * @param shift - shiftKey pressed
 * @returns editorState - EditorState with new selection forced
 */

const moveCursorJumpEntity = (editorState, step, shift = false, isRTL) => {
  const selectionState = editorState.getSelection()
  const contentState = editorState.getCurrentContent()

  // ------ previous selection state
  const prevSelectionIsBackward = selectionState.getIsBackward()
  const prevAnchorOffset = selectionState.getAnchorOffset()
  const prevFocusOffset = selectionState.getFocusOffset()
  const anchorKey = selectionState.getAnchorKey()
  const focusKey = selectionState.getFocusKey()

  // ------ cursor position after moving
  let newCursorPosition = selectionState.getFocusOffset() + step // +1 / -1
  const currentBlock = contentState.getBlockForKey(focusKey)

  // ------ new selection to merge
  let nextSelection = null
  let newSelection = null
  const currentBlockText = currentBlock.getText()

  const start = selectionState.getStartOffset()
  // content is:   [ZWSP_1]<Tag>[ZWSP_2]
  // cursor has to add an extra step and skip ZWSP_1 (when step > 0) or ZWSP_2 (when step < 0) to place itself inside the Tag
  // otherwise it won't be catched in the next check
  let selectedText =
    step > 0
      ? currentBlockText.slice(start, start + 1)
      : currentBlockText.slice(start - 1, start)
  const checkZeroWidthSpace =
    String.fromCharCode(parseInt('200B', 16)) === selectedText
  if (checkZeroWidthSpace) {
    newCursorPosition = step > 0 ? newCursorPosition + 1 : newCursorPosition - 1
  }

  // find entities in block
  currentBlock.findEntityRanges(
    (character) => character.getEntity() !== null,
    (start, end) => {
      // get entity
      const entityKey = currentBlock.getEntityAt(start)
      const entity = contentState.getEntity(entityKey)
      // jump every immutable entity
      const goingBack =
        start <= newCursorPosition && step < 0 && end > newCursorPosition
      const goingForward = start < newCursorPosition && end > newCursorPosition
      const jumpingSingleCharEntity =
        start < newCursorPosition &&
        end >= newCursorPosition &&
        end - start === 1

      if (
        entity.getMutability() === 'IMMUTABLE' &&
        // if you cursor inside entity
        (goingBack || goingForward || jumpingSingleCharEntity) &&
        // nothing already jumped
        nextSelection === null
      ) {
        nextSelection = {}
        nextSelection.nextAnchorKey = anchorKey // same
        nextSelection.nextFocusKey = focusKey // same

        // content is:   [ZWSP_1]<Tag>[ZWSP_2]
        // cursor will skip <Tag> and will be placed after ZWSP_2 (when step > 0) or before ZWSP_1 (when step < 0)
        selectedText =
          step > 0
            ? currentBlockText.slice(end, end + 1)
            : currentBlockText.slice(start - 1, start)
        const addZwspExtraStep =
          String.fromCharCode(parseInt('200B', 16)) === selectedText ? 1 : 0
        if (step > 0) {
          // jump on entity end
          nextSelection.nextAnchorOffset = shift
            ? prevAnchorOffset
            : end + addZwspExtraStep
          nextSelection.nextFocusOffset = end + addZwspExtraStep
        } else {
          // jump on entity start
          nextSelection.nextAnchorOffset = shift
            ? prevAnchorOffset
            : start - addZwspExtraStep
          nextSelection.nextFocusOffset = start - addZwspExtraStep
        }
      }
    },
  )

  newSelection = nextSelection
    ? SelectionState.createEmpty(nextSelection.nextAnchorKey).merge({
        anchorOffset: nextSelection.nextAnchorOffset,
        focusOffset: nextSelection.nextFocusOffset,
        focusKey: nextSelection.nextFocusKey,
        isBackward: checkIsBackward(
          nextSelection,
          prevSelectionIsBackward,
          shift,
          step > 0,
        ),
      })
    : null

  return newSelection
    ? EditorState.forceSelection(editorState, newSelection)
    : newSelection
}

/*
 * Comparing anchorOffset and focusOffset is made with their block-relative position. Even if the anchor is lower than focus
 * in document, it could have a block-relative offset higher than the offset of the focus.
 */
const checkIsBackward = (nextSelection, prevBackwardState, shift, forward) => {
  const {
    nextAnchorOffset,
    nextAnchorKey,
    nextFocusOffset,
    nextFocusKey,
  } = nextSelection
  // Same blocks && anchor > focus
  const cond1 =
    nextAnchorOffset > nextFocusOffset && nextAnchorKey === nextFocusKey
  // Different blocks && anchor < focus
  const cond2 =
    nextAnchorKey !== nextFocusKey && nextAnchorOffset <= nextFocusOffset
  const cond2Forward =
    nextAnchorKey !== nextFocusKey &&
    nextAnchorOffset <= nextFocusOffset &&
    prevBackwardState
  // Different blocks && anchor > focus
  const cond3 =
    nextAnchorKey !== nextFocusKey &&
    nextAnchorOffset >= nextFocusOffset &&
    prevBackwardState
  return !forward
    ? (shift && cond1) || cond2 || cond3
    : (shift && cond1) || cond2Forward || cond3
}

export default moveCursorJumpEntity
