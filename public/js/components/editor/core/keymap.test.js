import {runScopeHandlers} from '@codemirror/view'
import {createSegmentEditor} from './createSegmentEditor'

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

  test('tab inserts a tab, ctrl/cmd + shift + space an nbsp', () => {
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
