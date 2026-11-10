import {runScopeHandlers} from '@codemirror/view'
import {createSegmentEditor} from './createSegmentEditor'
import {tagMenu} from './tagMenu'
import {tagList} from './docModel'

// jsdom has no layout: CodeMirror only needs these to exist. Nothing here asserts caret geometry.
beforeAll(() => {
  const rect = {left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0}
  Range.prototype.getClientRects = () => []
  Range.prototype.getBoundingClientRect = () => rect
})

let editors = []
const mount = (value, {canOpen = () => true, ...options} = {}) => {
  const onChange = jest.fn()
  const onKey = jest.fn()
  const parent = document.body.appendChild(document.createElement('div'))
  const editor = createSegmentEditor({
    parent,
    value,
    tagMenu: {canOpen, onChange, onKey},
    ...options,
  })
  editors.push(editor)
  return {editor, onChange, onKey}
}
afterEach(() => {
  editors.forEach((e) => e.destroy())
  editors = []
  document.body.innerHTML = ''
})

const caret = (editor, pos) =>
  editor.view.dispatch({selection: {anchor: pos}, userEvent: 'select'})
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
const menuOf = (editor) => editor.view.state.field(tagMenu)
const measured = () => new Promise((resolve) => setTimeout(resolve, 30))

describe('tag menu', () => {
  test('typing < puts the < in and opens the menu on it; the host hears of it', async () => {
    const {editor, onChange} = mount('ab')
    caret(editor, 1)
    expect(press(editor, '<')).toBe(true)
    expect(editor.getValue()).toBe('a&lt;b')
    expect(menuOf(editor)).toEqual({trigger: {from: 1, to: 2}})
    await measured()
    expect(onChange).toHaveBeenLastCalledWith({
      trigger: {from: 1, to: 2},
      coords: null,
    })
  })

  test('the chosen tag replaces the < that opened the menu, as a tag, and closes it', async () => {
    const {editor, onChange} = mount('ab')
    caret(editor, 1)
    press(editor, '<')
    expect(editor.insertMenuTag('<x id="1"/>')).toBe(true)
    expect(editor.getValue()).toBe('a<x id="1"/>b')
    expect(tagList(editor.view.state)).toEqual([{from: 1, to: 12}])
    expect(menuOf(editor)).toBe(null)
    await measured()
    expect(onChange).toHaveBeenLastCalledWith(null)
  })

  test('alt + t opens the menu without a trigger: the tag goes in at the caret', () => {
    const {editor} = mount('ab')
    caret(editor, 2)
    expect(press(editor, 't', {altKey: true, keyCode: 84})).toBe(true)
    expect(menuOf(editor)).toEqual({trigger: null})
    editor.insertMenuTag('<x id="1"/>')
    expect(editor.getValue()).toBe('ab<x id="1"/>')
  })

  test('when the < is gone the tag goes in at the selection', () => {
    const {editor} = mount('ab')
    caret(editor, 1)
    press(editor, '<')
    // A programmatic change keeps the menu open but takes the < away
    editor.view.dispatch({
      changes: {from: 1, to: 2, insert: 'z'},
      effects: [],
      selection: {anchor: 2},
    })
    expect(menuOf(editor)).toBe(null)
  })

  test('while open, the arrows and enter go to the host and escape closes it', () => {
    const {editor, onKey} = mount('ab')
    caret(editor, 1)
    press(editor, 't', {altKey: true, keyCode: 84})
    expect(press(editor, 'ArrowDown')).toBe(true)
    expect(press(editor, 'ArrowUp')).toBe(true)
    expect(press(editor, 'Enter')).toBe(true)
    expect(onKey.mock.calls).toEqual([['down'], ['up'], ['enter']])
    expect(editor.getValue()).toBe('ab')
    expect(press(editor, 'Escape')).toBe(true)
    expect(menuOf(editor)).toBe(null)
  })

  test('closed, the arrows, enter and escape do their usual thing', () => {
    const {editor, onKey} = mount('ab')
    caret(editor, 1)
    expect(press(editor, 'Escape')).toBe(false)
    press(editor, 'Enter')
    expect(onKey).not.toHaveBeenCalled()
    expect(editor.getValue()).toBe('a##$_0A$##b')
  })

  test('typing or moving the caret closes the menu', () => {
    const {editor} = mount('ab')
    caret(editor, 1)
    press(editor, 't', {altKey: true, keyCode: 84})
    caret(editor, 0)
    expect(menuOf(editor)).toBe(null)
    press(editor, '<')
    editor.insertText('c')
    expect(menuOf(editor)).toBe(null)
    expect(editor.getValue()).toBe('&lt;ca' + 'b')
  })

  test('with nothing to offer the menu stays shut: < is typed by the browser, alt + t does nothing', () => {
    const {editor} = mount('ab', {canOpen: () => false})
    caret(editor, 1)
    expect(press(editor, '<')).toBe(false)
    expect(press(editor, 't', {altKey: true, keyCode: 84})).toBe(true)
    expect(menuOf(editor)).toBe(null)
    expect(editor.getValue()).toBe('ab')
  })

  test('a command-modified < and a read-only target never open it', () => {
    const {editor} = mount('ab', {readOnly: true})
    caret(editor, 1)
    expect(press(editor, 't', {altKey: true, keyCode: 84})).toBe(false)
    expect(editor.insertMenuTag('<x id="1"/>')).toBe(false)
    const other = mount('ab').editor
    expect(press(other, '<', {ctrlKey: true})).toBe(false)
    expect(menuOf(other)).toBe(null)
  })

  test('closeTagMenu closes an open menu and leaves a closed one alone', () => {
    const {editor, onChange} = mount('ab')
    editor.closeTagMenu()
    expect(onChange).not.toHaveBeenCalled()
    press(editor, 't', {altKey: true, keyCode: 84})
    editor.closeTagMenu()
    expect(menuOf(editor)).toBe(null)
  })
})
