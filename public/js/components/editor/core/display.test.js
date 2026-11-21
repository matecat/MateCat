import {createSegmentEditor} from './createSegmentEditor'
import {
  LINE_BREAK,
  SPECIAL_CHARS,
} from '../../../utils/segmentText/specialChars'

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
    expect(mark).toHaveClass('special-char-nbsp')
    // the mark keeps the nbsp width
    expect(mark.firstChild.nodeValue).toBe('\u00a0')
    // The ° is a child element, never a pseudo-element of the mark: the cattool tooltip owns ::before/::after of
    // every [aria-label] element and hid it (opacity 0 until hover).
    const label = mark.querySelector(':scope > .special-char-mark-label')
    expect(label.dataset.label).toBe('°')
    expect(label).toHaveAttribute('aria-hidden', 'true')
    expect(mark.dataset.label).toBeUndefined()
    // only the cattool tooltip: a native title would show a second one
    expect(mark).toHaveAttribute('aria-label', 'Non-breaking space (U+00A0)')
    expect(mark).not.toHaveAttribute('title')
    expect(editor.getValue()).toBe('a##$_A0$##b')
  })

  test('a typed degree sign is plain text with no mark, an nbsp next to it is marked', () => {
    const editor = mount('25°C##$_A0$##x')
    const marks = editor.view.contentDOM.querySelectorAll('.cm-specialChar')
    expect(marks).toHaveLength(1)
    expect(marks[0]).toHaveClass('special-char-nbsp')
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
    expect(mark).toHaveClass('special-char-word-joiner')
    expect(editor.view.contentDOM.querySelector('.tag')).toBeNull()
    expect(editor.getValue()).toBe('a\u2060b')
  })

  test.each(Object.entries(SPECIAL_CHARS))(
    '%j gets its own mark and tooltip, and the document keeps the character',
    (ch, {className, title, label, shape, keepsWidth}) => {
      const editor = mount(`a${ch}b`)
      const marks = editor.view.contentDOM.querySelectorAll('.cm-specialChar')
      expect(marks).toHaveLength(1)
      expect(marks[0].className).toBe(`cm-specialChar ${className}`)
      expect(marks[0]).toHaveAttribute('aria-label', title)
      expect(marks[0]).not.toHaveAttribute('title')
      // a space keeps its width by holding itself; a zero-width character is replaced by its mark
      expect(marks[0].firstChild?.nodeValue ?? '').toBe(keepsWidth ? ch : '')
      // the drawing is child elements that hold no text: the mark reads as the character alone, or as nothing
      const parts = [...marks[0].children].map((el) => el.className)
      expect(parts).toEqual(
        [
          label && 'special-char-mark-label',
          shape && `special-char-mark-${shape}`,
        ].filter(Boolean),
      )
      for (const el of marks[0].children) expect(el).toBeEmptyDOMElement()
      expect(editor.view.state.doc.toString()).toBe(`a${ch}b`)
    },
  )

  test('the tab and bidi controls keep their CodeMirror marks', () => {
    const editor = mount('a\u200eb##$_09$##c')
    const bidi = editor.view.contentDOM.querySelector('.cm-specialChar')
    expect(bidi.className).toBe('cm-specialChar')
    expect(bidi).toHaveAttribute(
      'aria-label',
      expect.stringMatching(/left-to-right mark/),
    )
    expect(bidi).not.toHaveAttribute('title')
    expect(bidi).toHaveTextContent('\u2022')
    expect(editor.view.contentDOM.querySelectorAll('.cm-tab')).toHaveLength(1)
    expect(editor.getValue()).toBe('a\u200eb##$_09$##c')
  })
})

describe('line breaks (display only)', () => {
  const lineBreaks = (editor) =>
    [...editor.view.contentDOM.querySelectorAll('.cm-line')].map(
      (line) => line.querySelectorAll('.special-char-mark-line-break').length,
    )

  test('every line that ends with a line break shows the cattool line feed mark, the last line none', () => {
    const editor = mount('one##$_0A$##two##$_0A$####$_0A$##four')
    expect(lineBreaks(editor)).toEqual([1, 1, 1, 0])
    const mark = editor.view.contentDOM.querySelector(
      '.special-char-mark-line-break',
    )
    expect(mark).toHaveAttribute('aria-label', LINE_BREAK.title)
    expect(mark).not.toHaveAttribute('title')
    expect(
      mark.querySelector(':scope > .special-char-mark-label').dataset.label,
    ).toBe(LINE_BREAK.label)
    // the mark is after the text of its line and holds no text of its own
    const text = mark.parentElement.firstChild
    expect(text.nodeValue).toBe('one')
    expect(
      text.compareDocumentPosition(mark) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
    expect(mark.querySelector('.special-char-mark-label')).toBeEmptyDOMElement()
    expect(editor.getValue()).toBe('one##$_0A$##two##$_0A$####$_0A$##four')
  })

  test('a segment with no line break shows none, a typed line break gets one and loses it on undo', () => {
    const editor = mount('one two')
    expect(lineBreaks(editor)).toEqual([0])
    editor.view.dispatch({changes: {from: 3, to: 4, insert: '\n'}})
    expect(lineBreaks(editor)).toEqual([1, 0])
    expect(editor.getValue()).toBe('one##$_0A$##two')
    editor.view.dispatch({changes: {from: 3, to: 4, insert: ' '}})
    expect(lineBreaks(editor)).toEqual([0])
  })

  test('the mark adds no document position: the end of a line is right before its line break', () => {
    const editor = mount('ab##$_0A$##cd')
    const {view} = editor
    expect(view.state.doc.length).toBe(5)
    expect(view.state.doc.line(1).to).toBe(2)
    expect(view.state.doc.line(2).from).toBe(3)
  })

  test('a carriage return stays its tag chip and gets no line break mark', () => {
    const editor = mount('a##$_0D$##b')
    expect(
      editor.view.contentDOM.querySelector('.special-char-mark-line-break'),
    ).toBeNull()
    expect(editor.getValue()).toBe('a##$_0D$##b')
  })
})

describe('spaces (the show whitespace option, display only)', () => {
  const spaceMarks = (editor) => [
    ...editor.view.contentDOM.querySelectorAll('.special-char-space'),
  ]

  test('are marked only with the option, one mark per space, and the document keeps them', () => {
    expect(spaceMarks(mount('a  b'))).toHaveLength(0)

    const editor = mount('a  b', {showWhitespace: true})
    const marks = spaceMarks(editor)
    // a run of spaces is one mark per space, not one element for the run
    expect(marks).toHaveLength(2)
    expect(marks[0].firstChild.nodeValue).toBe(' ')
    expect(
      marks[0].querySelector(':scope > .special-char-mark-label').dataset.label,
    ).toBe('·')
    // no tooltip on every space of the text
    expect(marks[0]).not.toHaveAttribute('aria-label')
    expect(editor.getValue()).toBe('a  b')
  })

  test('the option turns on and off in a live editor, and stays across a new value', () => {
    const editor = mount('a b')
    editor.setShowWhitespace(true)
    expect(spaceMarks(editor)).toHaveLength(1)
    editor.setValue('c d e', {undoable: false})
    expect(spaceMarks(editor)).toHaveLength(2)
    editor.setShowWhitespace(false)
    expect(spaceMarks(editor)).toHaveLength(0)
    // the other marks stay
    editor.setValue('a##$_A0$##b', {undoable: false})
    expect(
      editor.view.contentDOM.querySelectorAll('.special-char-nbsp'),
    ).toHaveLength(1)
    expect(editor.getValue()).toBe('a##$_A0$##b')
  })
})
