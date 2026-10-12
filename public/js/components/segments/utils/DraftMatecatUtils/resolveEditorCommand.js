import {EditorState, Modifier, KeyBindingUtil} from 'draft-js'
import {isMacOS} from '../../../../utils/Utils'
import {
  checkCaretIsNearEntity,
  checkCaretIsNearZwsp,
  isSelectedEntity,
} from './manageCaretPositionNearEntity'

const {isOptionKeyCommand, hasCommandModifier, isCtrlKeyCommand} =
  KeyBindingUtil

/**
 * Decides what a keystroke means, without touching the editor.
 *
 * The component used to make this decision and carry it out in the same pass,
 * which is why handleKeyCommand had five arms that only acknowledged work
 * already done. Here the decision and the edit it implies are returned
 * together, and the caller applies them.
 *
 * @returns {{
 *   command: string|null,   the Draft command, or null to fall back to the default binding
 *   editorState?: object,   an already-adjusted editor state the caller should apply
 *   applyVia?: string,      'onChange' when the adjustment must go through onChange
 *   typeText?: string,      text to insert at the selection
 *   clearTriggerText?: boolean,
 *   shiftOnNavigation?: boolean,
 * }}
 */
export default function resolveEditorCommand(e, ctx) {
  const {
    displayPopover,
    editorState,
    isRTL,
    isChromeBook,
    hasSpaceTag,
    selectionIsCaret, // thunk: only the Backspace/Delete arm reads the DOM selection
    typingWordJoiner,
  } = ctx
  const effects = {}
  const done = (command) => ({...effects, command})

  if (
    (e.keyCode === 84 || e.key === 't' || e.key === '™') &&
    (isOptionKeyCommand(e) || e.altKey) &&
    !e.shiftKey
  ) {
    effects.clearTriggerText = true
    return done('toggle-tag-menu')
  } else if (e.key === '<' && !hasCommandModifier(e)) {
    effects.typeText = '<'
    return done('toggle-tag-menu')
  } else if (e.key === 'ArrowUp' && !hasCommandModifier(e)) {
    if (displayPopover) return done('up-arrow-press')
  } else if (e.key === 'ArrowDown' && !hasCommandModifier(e)) {
    if (displayPopover) return done('down-arrow-press')
  } else if (e.key === 'Enter') {
    if (
      (e.altKey && e.ctrlKey) ||
      (e.ctrlKey && isOptionKeyCommand(e) && e.shiftKey)
    ) {
      return done('add-issue')
    } else if (displayPopover && !hasCommandModifier(e)) {
      return done('enter-press')
    } else if ((e.ctrlKey || e.metaKey) && e.shiftKey) {
      return done('next-translate')
    } else if (e.ctrlKey || e.metaKey) {
      return done('translate')
    }
  } else if (e.key === 'Escape') {
    return done('close-tag-menu')
  } else if (e.key === 'Tab') {
    return e.shiftKey ? done(null) : done('insert-tab-tag')
  } else if (
    e.code === 'Space' &&
    !e.ctrlKey &&
    !e.altKey &&
    !e.shiftKey &&
    hasSpaceTag
  ) {
    return done('insert-space-tag')
  } else if (
    (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
    ((isCtrlKeyCommand(e) && e.shiftKey) ||
      (isMacOS() && isOptionKeyCommand(e) && !e.ctrlKey))
  ) {
    return done('insert-nbsp-tag') // Windows && Mac
  } else if (
    (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
    !e.shiftKey &&
    e.altKey &&
    isChromeBook
  ) {
    return done('insert-nbsp-tag') // Chromebook
  } else if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && !e.altKey) {
    effects.shiftOnNavigation = e.shiftKey

    const direction = e.key === 'ArrowLeft' ? 'left' : 'right'

    // check caret is near zwsp char and move caret position
    const updatedStateNearZwsp = checkCaretIsNearZwsp({
      editorState: editorState,
      direction,
      isShiftPressed: e.shiftKey,
    })

    // check caret is near entity and move caret position
    const updatedStateNearEntity = checkCaretIsNearEntity({
      editorState: updatedStateNearZwsp ? updatedStateNearZwsp : editorState,
      direction,
      isShiftPressed: e.shiftKey,
    })

    if (updatedStateNearEntity || updatedStateNearZwsp) {
      effects.editorState = updatedStateNearEntity
        ? updatedStateNearEntity
        : updatedStateNearZwsp
      return done(`${direction}-nav`)
    }
  } else if (e.ctrlKey && e.key === 'k') {
    return done('tm-search')
  } else if (
    (e.key === ' ' || e.key === 'Spacebar' || e.key === ' ') &&
    ((e.ctrlKey && e.altKey) || (isMacOS() && e.shiftKey))
  ) {
    return done('insert-word-joiner-tag')
  } else if (e.code === 'BracketLeft' || e.code === 'BracketRight') {
    if (e.code === 'BracketLeft' && isCtrlKeyCommand(e)) {
      if (e.shiftKey) {
        effects.typeText = '“'
      } else {
        effects.typeText = '‘'
      }
      return done('quote-shortcut')
    }
    if (e.code === 'BracketRight' && isCtrlKeyCommand(e)) {
      if (e.shiftKey) {
        effects.typeText = '”'
      } else {
        effects.typeText = '’'
      }
      return done('quote-shortcut')
    }
  } else if (e.altKey && !e.shiftKey && !e.ctrlKey) {
    const {get, reset} = typingWordJoiner
    if (e.key !== 'Alt') {
      const result = get(e.keyCode)
      if (result) {
        return done('insert-word-joiner-tag')
      }
    } else {
      reset()
    }
  } else if (
    (e.key === 'Backspace' || e.key === 'Delete') &&
    !isSelectedEntity(editorState) &&
    selectionIsCaret()
  ) {
    const direction =
      e.key === 'Backspace'
        ? !isRTL
          ? 'left'
          : 'right'
        : !isRTL
          ? 'right'
          : 'left'

    const updatedStateNearZwsp = checkCaretIsNearZwsp({
      editorState: editorState,
      direction,
      isShiftPressed: true,
    })

    // check caret is near entity and move caret position
    const updatedStateNearEntity = checkCaretIsNearEntity({
      editorState: updatedStateNearZwsp ? updatedStateNearZwsp : editorState,
      direction,
      isShiftPressed: true,
      isBackspacePressed: e.key === 'Backspace',
    })

    if (updatedStateNearEntity) {
      const selectionState = updatedStateNearEntity.getSelection()
      const contentState = updatedStateNearEntity.getCurrentContent()

      const updatedEditorState = EditorState.push(
        updatedStateNearEntity,
        Modifier.replaceText(contentState, selectionState, null),
        'insert-characters',
      )
      effects.editorState = updatedEditorState
      effects.applyVia = 'onChange'
      return done('delete-entity')
    }
  }
  return done(null)
}
