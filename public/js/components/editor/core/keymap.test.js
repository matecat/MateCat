import {runScopeHandlers} from '@codemirror/view'
import {createSegmentEditor} from './createSegmentEditor'
import {resolveTargetKey} from './keymap'
import {tagList} from './docModel'

// jsdom has no layout: CodeMirror only needs these to exist. Nothing here asserts caret geometry.
beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
})

let editors = []
const mount = (value, options = {}) => {
  const parent = document.body.appendChild(document.createElement('div'))
  const editor = createSegmentEditor({parent, value, ...options})
  editors.push(editor)
  return editor
}
afterEach(() => {
  editors.forEach((e) => e.destroy())
  editors = []
  document.body.innerHTML = ''
})

const caret = (editor, pos) => editor.view.dispatch({selection: {anchor: pos}})
const press = (editor, key, mods = {}) =>
  runScopeHandlers(
    editor.view,
    new KeyboardEvent('keydown', {
      key,
      bubbles: true,
      cancelable: true,
      ...mods,
    }),
    'editor',
  )

describe('keys (resolveEditorCommand)', () => {
  test('enter and shift + enter insert a line feed at the caret', () => {
    const editor = mount('ab')
    caret(editor, 1)
    expect(press(editor, 'Enter')).toBe(true)
    expect(press(editor, 'Enter', {shiftKey: true})).toBe(true)
    expect(editor.getValue()).toBe('a##$_0A$####$_0A$##b')
  })

  test('tab inserts a tab, ctrl + shift + space an nbsp', () => {
    const editor = mount('ab')
    caret(editor, 1)
    press(editor, 'Tab')
    press(editor, ' ', {ctrlKey: true, shiftKey: true})
    expect(editor.getValue()).toBe('a##$_09$####$_A0$##b')
  })

  test('ctrl/cmd + enter is not handled, so it reaches the page', () => {
    const editor = mount('ab')
    expect(press(editor, 'Enter', {ctrlKey: true})).toBe(false)
    expect(press(editor, 'Enter', {metaKey: true})).toBe(false)
    expect(editor.getValue()).toBe('ab')
  })

  test('the read-only source takes no editing key', () => {
    const editor = mount('ab', {mode: 'source'})
    caret(editor, 1)
    press(editor, 'Enter')
    press(editor, 'Tab')
    expect(editor.getValue()).toBe('ab')
  })

  test('undo and redo are bound in the target', () => {
    const editor = mount('ab')
    caret(editor, 1)
    press(editor, 'Enter')
    press(editor, 'z', {ctrlKey: true})
    expect(editor.getValue()).toBe('ab')
  })
})

describe('target keys (resolveEditorCommand)', () => {
  const sequence = () => ({get: jest.fn(() => false), reset: jest.fn()})
  const resolve = (init, ctx = {}) =>
    resolveTargetKey(
      {ctrlKey: false, altKey: false, shiftKey: false, metaKey: false, ...init},
      {
        isMac: false,
        isChromeBook: false,
        wordJoinerSequence: sequence(),
        ...ctx,
      },
    )

  test('the nbsp: ctrl + shift + space everywhere, option + space on macOS, alt + space on a Chromebook', () => {
    expect(resolve({key: ' ', ctrlKey: true, shiftKey: true})).toEqual({
      text: '\u00a0',
    })
    // Option + Space on macOS reports the nbsp itself as the key
    expect(resolve({key: '\u00a0', altKey: true}, {isMac: true})).toEqual({
      text: '\u00a0',
    })
    expect(resolve({key: ' ', altKey: true}, {isMac: false})).toBe(null)
    expect(resolve({key: ' ', altKey: true}, {isChromeBook: true})).toEqual({
      text: '\u00a0',
    })
  })

  test('the word joiner: ctrl + alt + space, shift + space on macOS, alt + 2060 on the keypad', () => {
    expect(resolve({key: ' ', ctrlKey: true, altKey: true})).toEqual({
      text: '\u2060',
    })
    expect(resolve({key: ' ', shiftKey: true}, {isMac: true})).toEqual({
      text: '\u2060',
    })
    expect(resolve({key: ' ', shiftKey: true})).toBe(null)
    const typed = {get: jest.fn(() => true), reset: jest.fn()}
    expect(
      resolve(
        {key: '0', keyCode: 96, altKey: true},
        {wordJoinerSequence: typed},
      ),
    ).toEqual({text: '\u2060'})
    resolve({key: 'Alt', altKey: true}, {wordJoinerSequence: typed})
    expect(typed.reset).toHaveBeenCalled()
  })

  test('ctrl + [ and ] type the smart quotes, with shift the double ones', () => {
    expect(resolve({code: 'BracketLeft', ctrlKey: true})).toEqual({
      text: '\u2018',
    })
    expect(
      resolve({code: 'BracketLeft', ctrlKey: true, shiftKey: true}),
    ).toEqual({text: '\u201c'})
    expect(resolve({code: 'BracketRight', ctrlKey: true})).toEqual({
      text: '\u2019',
    })
    expect(
      resolve({code: 'BracketRight', ctrlKey: true, shiftKey: true}),
    ).toEqual({text: '\u201d'})
    expect(resolve({code: 'BracketLeft', ctrlKey: true, altKey: true})).toBe(
      null,
    )
  })

  test('add issue (ctrl + alt + enter) and ctrl + k only lose their browser default; ctrl + enter is left alone', () => {
    expect(resolve({key: 'Enter', ctrlKey: true, altKey: true})).toEqual({
      swallow: true,
    })
    expect(resolve({key: 'k', ctrlKey: true})).toEqual({swallow: true})
    expect(resolve({key: 'Enter', ctrlKey: true})).toBe(null)
  })

  test('in the target the keys type into the document as text: quotes and the word joiner, never a tag', () => {
    const editor = mount('ab')
    caret(editor, 1)
    expect(press(editor, '[', {code: 'BracketLeft', ctrlKey: true})).toBe(true)
    expect(press(editor, ' ', {ctrlKey: true, altKey: true})).toBe(true)
    expect(editor.getValue()).toBe('a\u2018\u2060b')
    expect(tagList(editor.view.state)).toEqual([])
  })

  test('add issue and ctrl + k are handled without touching the document', () => {
    const editor = mount('ab')
    caret(editor, 1)
    expect(press(editor, 'Enter', {ctrlKey: true, altKey: true})).toBe(true)
    expect(press(editor, 'k', {ctrlKey: true})).toBe(true)
    expect(editor.getValue()).toBe('ab')
  })

  test('a read-only target takes none of them', () => {
    const editor = mount('ab', {readOnly: true})
    caret(editor, 1)
    expect(press(editor, ' ', {ctrlKey: true, altKey: true})).toBe(false)
    expect(press(editor, 'Tab')).toBe(false)
    expect(editor.getValue()).toBe('ab')
  })
})
