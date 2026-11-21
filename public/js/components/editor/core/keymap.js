// Keys of the segment editor. Ctrl/Cmd+Enter is deliberately left unbound so it bubbles to the host page.
import {Prec} from '@codemirror/state'
import {keymap} from '@codemirror/view'
import {historyKeymap, standardKeymap} from '@codemirror/commands'

/** Command replacing the selection with plain document text (never a tag). */
export const insertDocText = (text) => (view) => {
  if (view.state.readOnly) return false
  view.dispatch(view.state.replaceSelection(text), {
    userEvent: 'input',
    scrollIntoView: true,
  })
  return true
}

// standardKeymap without its Enter (insertNewlineAndIndent): Enter is ours.
const baseKeymap = standardKeymap.filter((binding) => binding.key !== 'Enter')

const editingKeys = [
  {key: 'Enter', run: insertDocText('\n'), shift: insertDocText('\n')},
  {key: 'Tab', run: insertDocText('\t')},
  {key: 'Mod-Shift-Space', run: insertDocText('\u00a0')},
]

/** `editable`: the target gets the editing keys and history; the read-only source only moves and selects. */
export const keymapExtensions = ({editable}) => [
  editable ? Prec.highest(keymap.of(editingKeys)) : [],
  keymap.of(editable ? [...historyKeymap, ...baseKeymap] : baseKeymap),
]
