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

describe('invisible characters (display only)', () => {
  test('an nbsp shows as the cattool ° mark, and the document keeps the nbsp', () => {
    const editor = mount('a##$_A0$##b')
    const mark = editor.view.contentDOM.querySelector('.cm-specialChar')
    expect(mark).toHaveClass('cm-nbsp')
    expect(mark).toHaveTextContent('°')
    expect(editor.getValue()).toBe('a##$_A0$##b')
  })

  test('a tab is marked, and the document keeps the tab', () => {
    const editor = mount('a##$_09$##b')
    expect(editor.view.contentDOM.querySelectorAll('.cm-tab')).toHaveLength(1)
    expect(editor.getValue()).toBe('a##$_09$##b')
  })

  test('any other invisible character keeps the default mark, not the nbsp one', () => {
    const editor = mount('a\u200bb')
    const mark = editor.view.contentDOM.querySelector('.cm-specialChar')
    expect(mark).toHaveAttribute(
      'title',
      expect.stringMatching(/zero width space/),
    )
    expect(mark).not.toHaveClass('cm-nbsp')
    expect(mark).toHaveTextContent('\u2022')
    expect(editor.getValue()).toBe('a\u200bb')
  })
})
