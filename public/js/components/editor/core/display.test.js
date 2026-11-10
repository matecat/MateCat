import {createSegmentEditor} from './createSegmentEditor'
import {SPECIAL_CHARS} from './display'

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

describe('invisible characters (display only)', () => {
  test('an nbsp shows as the cattool ° mark over the nbsp itself, and the document keeps the nbsp', () => {
    const editor = mount('a##$_A0$##b')
    const mark = editor.view.contentDOM.querySelector('.cm-specialChar')
    expect(mark).toHaveClass('cm-nbsp')
    expect(mark.dataset.label).toBe('°')
    // the mark keeps the nbsp width
    expect(mark.firstChild.nodeValue).toBe('\u00a0')
    expect(mark).toHaveAttribute('title', 'Non-breaking space (U+00A0)')
    expect(editor.getValue()).toBe('a##$_A0$##b')
  })

  test('a typed degree sign is plain text with no mark, an nbsp next to it is marked', () => {
    const editor = mount('25°C##$_A0$##x')
    const marks = editor.view.contentDOM.querySelectorAll('.cm-specialChar')
    expect(marks).toHaveLength(1)
    expect(marks[0]).toHaveClass('cm-nbsp')
    expect(editor.view.contentDOM).toHaveTextContent('25°C')
    expect(editor.getValue()).toBe('25°C##$_A0$##x')
  })

  test('a tab is marked, and the document keeps the tab', () => {
    const editor = mount('a##$_09$##b')
    expect(editor.view.contentDOM.querySelectorAll('.cm-tab')).toHaveLength(1)
    expect(editor.getValue()).toBe('a##$_09$##b')
  })

  test('a word joiner is marked, is no tag chip, and the document keeps the character', () => {
    const editor = mount('a\u2060b')
    const mark = editor.view.contentDOM.querySelector('.cm-specialChar')
    expect(mark).toHaveClass('cm-word-joiner')
    expect(editor.view.contentDOM.querySelector('.tag')).toBeNull()
    expect(editor.getValue()).toBe('a\u2060b')
  })

  test.each(Object.entries(SPECIAL_CHARS))(
    '%j gets its own mark and tooltip, and the document keeps the character',
    (ch, {className, title, label, keepsWidth}) => {
      const editor = mount(`a${ch}b`)
      const marks = editor.view.contentDOM.querySelectorAll('.cm-specialChar')
      expect(marks).toHaveLength(1)
      expect(marks[0].className).toBe(`cm-specialChar ${className}`)
      expect(marks[0]).toHaveAttribute('title', title)
      expect(marks[0]).toHaveAttribute('aria-label', title)
      // a space keeps its width by holding itself; a zero-width character is replaced by its mark
      expect(marks[0].firstChild?.nodeValue ?? '').toBe(keepsWidth ? ch : label)
      expect(editor.view.state.doc.toString()).toBe(`a${ch}b`)
    },
  )

  test('the tab and bidi controls keep their CodeMirror marks', () => {
    const editor = mount('a\u200eb##$_09$##c')
    const bidi = editor.view.contentDOM.querySelector('.cm-specialChar')
    expect(bidi.className).toBe('cm-specialChar')
    expect(bidi).toHaveAttribute(
      'title',
      expect.stringMatching(/left-to-right mark/),
    )
    expect(bidi).toHaveTextContent('\u2022')
    expect(editor.view.contentDOM.querySelectorAll('.cm-tab')).toHaveLength(1)
    expect(editor.getValue()).toBe('a\u200eb##$_09$##c')
  })
})
