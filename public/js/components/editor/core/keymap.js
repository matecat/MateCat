// Keys of the segment editor. Ctrl/Cmd+Enter is deliberately left unbound so it bubbles to the host page.
import {Prec} from '@codemirror/state'
import {keymap} from '@codemirror/view'
import {historyKeymap, standardKeymap} from '@codemirror/commands'
import matchTypingSequence from '../../../utils/matchTypingSequence/matchTypingSequence'

export const IS_MAC =
  typeof navigator !== 'undefined' &&
  /Mac|iPhone|iPad|iPod/.test(navigator.platform)
const IS_CHROMEBOOK =
  typeof navigator !== 'undefined' && navigator.userAgent.indexOf('CrOS') > -1

const NBSP = ' '
const WORD_JOINER = '⁠'
// The space bar as browsers report it; Option + Space on macOS reports an nbsp.
const SPACE_KEYS = [' ', 'Spacebar', NBSP]

/** Cmd on macOS, Ctrl elsewhere, without Alt: the modifier of the page shortcuts. */
export const hasCommandModifier = (e, isMac = IS_MAC) =>
  isMac ? e.metaKey && !e.altKey : e.ctrlKey && !e.altKey

/** Command replacing the selection with plain document text (never a tag). */
export const insertDocText = (text) => (view) => {
  if (view.state.readOnly) return false
  view.dispatch(view.state.replaceSelection(text), {
    userEvent: 'input',
    scrollIntoView: true,
  })
  return true
}

/**
 * What a key does in the target beyond typing, decided as the Draft target decides it (resolveEditorCommand):
 * `{text}` typed as text (the word joiner too: a character, never a tag), `{swallow: true}` only the browser default
 * stopped (the key still reaches the page shortcuts), or null.
 * @param {KeyboardEvent} e
 * @param {{isMac: boolean, isChromeBook: boolean, wordJoinerSequence: {get: Function, reset: Function}}} ctx
 */
export const resolveTargetKey = (
  e,
  {isMac, isChromeBook, wordJoinerSequence},
) => {
  const ctrlOnly = e.ctrlKey && !e.altKey
  const option = isMac && e.altKey
  const isSpace = SPACE_KEYS.includes(e.key)
  // Add issue (Ctrl+Alt+Enter) and Ctrl+K belong to the page: no browser default, no edit
  if (e.key === 'Enter') return e.altKey && e.ctrlKey ? {swallow: true} : null
  if (e.ctrlKey && e.key === 'k') return {swallow: true}
  if (isSpace && ((ctrlOnly && e.shiftKey) || (option && !e.ctrlKey)))
    return {text: NBSP}
  if (isSpace && !e.shiftKey && e.altKey && isChromeBook) return {text: NBSP}
  if (isSpace && ((e.ctrlKey && e.altKey) || (isMac && e.shiftKey)))
    return {text: WORD_JOINER}
  if (e.code === 'BracketLeft' && ctrlOnly)
    return {text: e.shiftKey ? '“' : '‘'}
  if (e.code === 'BracketRight' && ctrlOnly)
    return {text: e.shiftKey ? '”' : '’'}
  // Alt + 2060 typed on the keypad is the word joiner
  if (e.altKey && !e.shiftKey && !e.ctrlKey) {
    if (e.key === 'Alt') wordJoinerSequence.reset()
    else if (wordJoinerSequence.get(e.keyCode)) return {text: WORD_JOINER}
  }
  return null
}

const targetKeys = () => {
  const wordJoinerSequence = matchTypingSequence(
    [
      [50, 98],
      [48, 96],
      [54, 102],
      [48, 96],
    ],
    2000,
  )
  return {
    any(view, event) {
      if (view.state.readOnly || view.composing) return false
      const action = resolveTargetKey(event, {
        isMac: IS_MAC,
        isChromeBook: IS_CHROMEBOOK,
        wordJoinerSequence,
      })
      if (!action) return false
      if (action.text) return insertDocText(action.text)(view)
      return true
    },
  }
}

// standardKeymap without its Enter (insertNewlineAndIndent): Enter is ours.
const baseKeymap = standardKeymap.filter((binding) => binding.key !== 'Enter')

const editingKeys = [
  {key: 'Enter', run: insertDocText('\n'), shift: insertDocText('\n')},
  {key: 'Tab', run: insertDocText('\t')},
]

/** `editable`: the target gets the editing keys and history; the read-only source only moves and selects. */
export const keymapExtensions = ({editable}) => [
  editable ? Prec.highest(keymap.of([targetKeys(), ...editingKeys])) : [],
  keymap.of(editable ? [...historyKeymap, ...baseKeymap] : baseKeymap),
]
