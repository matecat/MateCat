// The tag menu of the target. `<` (typed, then replaced by the tag) or Alt+T opens it at the caret; while it is open
// the arrow keys and Enter drive it and Escape closes it; any other edit or caret move closes it. The list and its
// look belong to the host, which hears about the menu through `onChange` and `onKey` and puts the chosen tag in with
// `insertMenuTag`.
import {Prec, StateEffect, StateField} from '@codemirror/state'
import {EditorView, keymap} from '@codemirror/view'
import {insertContent, insertRaw} from './docModel'
import {hasCommandModifier} from './keymap'

const setMenu = StateEffect.define()

/** The open menu as `{trigger}`, the `<` that opened it as a document range (or null), or null when closed. */
export const tagMenu = StateField.define({
  create: () => null,
  update(value, tr) {
    for (const effect of tr.effects) if (effect.is(setMenu)) return effect.value
    return value && (tr.docChanged || tr.selection) ? null : value
  },
})

/** Alt+T, or Option+T on macOS (which reports '†' or '™'), as the Draft target reads it. */
const isMenuShortcut = (e) =>
  (e.keyCode === 84 || e.key === 't' || e.key === '™') &&
  e.altKey &&
  !e.shiftKey

const MENU_KEYS = {ArrowUp: 'up', ArrowDown: 'down', Enter: 'enter'}

const menuKey = (view, e, {canOpen, onKey}) => {
  if (view.state.readOnly || view.composing) return false
  if (isMenuShortcut(e)) {
    if (canOpen()) view.dispatch({effects: setMenu.of({trigger: null})})
    return true
  }
  if (e.key === '<' && !hasCommandModifier(e)) {
    if (!canOpen()) return false
    const {from, to} = view.state.selection.main
    const spec = insertContent(view.state, from, to, {text: '<', tags: []})
    const at = spec.changes.from
    view.dispatch(spec, {
      effects: setMenu.of({trigger: {from: at, to: at + 1}}),
      userEvent: 'input.type',
      scrollIntoView: true,
    })
    return true
  }
  if (!view.state.field(tagMenu)) return false
  if (e.key === 'Escape') {
    view.dispatch({effects: setMenu.of(null)})
    return true
  }
  const name = MENU_KEYS[e.key]
  if (!name || e.ctrlKey || e.metaKey || e.altKey) return false
  onKey(name)
  return true
}

/**
 * Puts the tag `raw` (segment markup) in from the menu, over the `<` that opened it, and closes the menu.
 * @returns {boolean} false when the editor is read-only
 */
export const insertMenuTag = (view, raw) => {
  if (view.state.readOnly) return false
  const trigger = view.state.field(tagMenu)?.trigger
  const range =
    trigger && view.state.sliceDoc(trigger.from, trigger.to) === '<'
      ? trigger
      : view.state.selection.main
  view.dispatch(insertRaw(view.state, range.from, range.to, raw), {
    effects: setMenu.of(null),
    userEvent: 'input',
    scrollIntoView: true,
  })
  view.focus()
  return true
}

/** Closes the menu, if it is open. */
export const closeTagMenu = (view) => {
  if (view.state.field(tagMenu)) view.dispatch({effects: setMenu.of(null)})
}

/**
 * @param {object} options
 * @param {() => boolean} [options.canOpen] whether there are tags to offer; the menu stays shut otherwise
 * @param {(menu: {trigger: object|null, coords: object|null}|null) => void} [options.onChange] the menu opened (with
 *   the caret coordinates, viewport based) or closed
 * @param {(key: 'up'|'down'|'enter') => void} [options.onKey] a key for the open menu
 */
export const tagMenuExtensions = ({
  canOpen = () => false,
  onChange = () => {},
  onKey = () => {},
} = {}) => [
  tagMenu,
  Prec.highest(
    keymap.of([{any: (view, event) => menuKey(view, event, {canOpen, onKey})}]),
  ),
  EditorView.updateListener.of((update) => {
    const menu = update.state.field(tagMenu)
    if (menu === update.startState.field(tagMenu)) return
    if (!menu) {
      onChange(null)
      return
    }
    const head = update.state.selection.main.head
    // Layout is read in the measure phase, never during an update.
    update.view.requestMeasure({
      read: (view) => view.coordsAtPos(head),
      write: (coords, view) => {
        if (view.state.field(tagMenu) === menu) onChange({...menu, coords})
      },
    })
  }),
]
