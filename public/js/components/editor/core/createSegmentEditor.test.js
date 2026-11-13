import {
  cursorCharLeft,
  cursorCharRight,
  deleteCharBackward,
  deleteCharForward,
  selectCharRight,
} from '@codemirror/commands'
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
const head = (editor) => editor.view.state.selection.main.head

describe('caret and deletion next to a tag (manageCaretPositionNearEntity, selectionAroundEntity)', () => {
  const segment = 'a<x id="1"/>b'
  const tagEnd = 12

  test('an arrow crosses a tag in one press, both ways', () => {
    const editor = mount(segment)
    caret(editor, 1)
    cursorCharRight(editor.view)
    expect(head(editor)).toBe(tagEnd)
    cursorCharLeft(editor.view)
    expect(head(editor)).toBe(1)
  })

  test('shift + arrow selects the whole tag', () => {
    const editor = mount(segment)
    caret(editor, 1)
    selectCharRight(editor.view)
    expect(editor.getSelection().textWithTags).toBe('<x id="1"/>')
  })

  test('backspace after a tag removes the whole tag', () => {
    const editor = mount(segment)
    caret(editor, tagEnd)
    deleteCharBackward(editor.view)
    expect(editor.getValue()).toBe('ab')
  })

  test('delete before a tag removes the whole tag', () => {
    const editor = mount(segment)
    caret(editor, 1)
    deleteCharForward(editor.view)
    expect(editor.getValue()).toBe('ab')
  })

  test('backspace after an entity removes the one character it decodes to', () => {
    const editor = mount('a&amp;b')
    caret(editor, 2)
    deleteCharBackward(editor.view)
    expect(editor.getValue()).toBe('ab')
  })
})

describe('api', () => {
  test('setValue is one undoable step and puts the caret at the end', () => {
    const changes = []
    const editor = mount('<g id="1">old</g>', {
      onChange: (v) => changes.push(v),
    })
    editor.setValue('<x id="2"/>new')
    expect(editor.getValue()).toBe('<x id="2"/>new')
    expect(head(editor)).toBe(editor.view.state.doc.length)
    expect(changes).toEqual(['<x id="2"/>new'])
    editor.undo()
    expect(editor.getValue()).toBe('<g id="1">old</g>')
    editor.redo()
    expect(editor.getValue()).toBe('<x id="2"/>new')
  })

  test('setValue without undo starts over with an empty history', () => {
    const editor = mount('a')
    editor.setValue('b', {undoable: false})
    expect(editor.undo()).toBe(false)
    expect(editor.getValue()).toBe('b')
  })

  test('insertRaw replaces the selection and makes tags (insertEntityAtSelection)', () => {
    const editor = mount('abc')
    editor.view.dispatch({selection: {anchor: 1, head: 2}})
    editor.insertRaw('<x id="1"/>')
    expect(editor.getValue()).toBe('a<x id="1"/>c')
    caret(editor, 1)
    deleteCharForward(editor.view)
    expect(editor.getValue()).toBe('ac')
  })

  test('insertText keeps markup as text', () => {
    const editor = mount('')
    editor.insertText('<x id="1"/> & °')
    expect(editor.getValue()).toBe('&lt;x id="1"/&gt; &amp; °')
  })

  test('a click on a chip selects the tag and reports it', () => {
    const events = []
    const editor = mount('a<g id="3">b</g>', {
      onEvent: (name, payload) => events.push([name, payload]),
    })
    const chip = editor.view.contentDOM.querySelector('.tag')
    chip.dispatchEvent(new MouseEvent('mousedown', {bubbles: true, button: 0}))
    expect(editor.getSelection().textWithTags).toBe('<g id="3">')
    expect(events).toEqual([
      [
        'tagClick',
        expect.objectContaining({
          name: 'g',
          raw: '<g id="3">',
          from: 1,
          to: 11,
        }),
      ],
    ])
  })

  test('chips carry the cattool classes and the pair number', () => {
    const editor = mount('<g id="1">a</g>')
    const [open, close] = editor.view.contentDOM.querySelectorAll('.tag')
    expect(open.className).toContain('tag small tag-open')
    expect(close.className).toContain('tag-close')
    expect(open).toHaveAttribute('contenteditable', 'false')
  })

  test('the content gets lang, dir and spellcheck', () => {
    const editor = mount('a', {lang: 'ar-SA', dir: 'rtl'})
    const {contentDOM} = editor.view
    expect(contentDOM).toHaveAttribute('lang', 'ar-SA')
    expect(contentDOM).toHaveAttribute('dir', 'rtl')
    expect(contentDOM).toHaveAttribute('spellcheck', 'true')
  })
})
