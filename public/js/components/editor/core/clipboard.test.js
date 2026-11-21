import {segmentClipboardHtml} from '../../segmentView/segmentModel'
import {createSegmentEditor} from './createSegmentEditor'
import {tagList} from './docModel'
import {transferContent} from './clipboard'

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

const select = (editor, anchor, head) =>
  editor.view.dispatch({selection: {anchor, head}})
const tagsOf = (editor) => tagList(editor.view.state)

// A DataTransfer: `Text` is the legacy name browsers map to `text/plain`.
const fakeTransfer = (initial = {}) => {
  const data = {...initial}
  const type = (t) => (t === 'Text' ? 'text/plain' : t)
  return {
    data,
    getData: (t) => data[type(t)] ?? '',
    setData: (t, value) => (data[type(t)] = value),
    clearData: () => Object.keys(data).forEach((t) => delete data[t]),
  }
}

const fire = (target, name, props = {}) => {
  const event = Object.assign(
    new Event(name, {bubbles: true, cancelable: true}),
    props,
  )
  target.dispatchEvent(event)
  return event
}

const slice = (raw, text) => segmentClipboardHtml({raw, text})

describe('clipboard content (buildPastedEditorState)', () => {
  test('a segment slice keeps its tags', () => {
    const html = slice('a<x id="1"/>&amp;', 'a1&')
    const {text, tags} = transferContent(fakeTransfer({'text/html': html}))
    expect(text).toBe('a<x id="1"/>&')
    expect(tags).toEqual([{from: 1, to: 12}])
  })

  test('a slice with forged markup gets no tags from it', () => {
    const html = slice('<img src=x onerror=alert(1)>', '')
    expect(transferContent(fakeTransfer({'text/html': html}))).toEqual({
      text: '<img src=x onerror=alert(1)>',
      tags: [],
    })
  })

  test('outside text loses its tag markup and keeps a degree sign as a degree sign', () => {
    const data = fakeTransfer({
      'text/html': '<b>bold</b>',
      'text/plain': 'a<x id="1"/>b25°C\r\nd',
    })
    expect(transferContent(data)).toEqual({text: 'ab25°C\nd', tags: []})
  })
})

describe('copy and cut (getSegmentSlice)', () => {
  test('copy writes the plain text and the raw slice', () => {
    const editor = mount('a<x id="1"/>&amp;b')
    select(editor, 0, 13)
    const clipboardData = fakeTransfer()
    fire(editor.view.contentDOM, 'copy', {clipboardData})
    expect(clipboardData.data).toEqual({
      'text/plain': 'a1&',
      'text/html': slice('a<x id="1"/>&amp;', 'a1&'),
    })
  })

  test('cut writes the slice grown to whole tags and removes it', () => {
    const editor = mount('a<x id="1"/>b')
    select(editor, 0, 3) // ends inside the tag
    const clipboardData = fakeTransfer({'text/plain': 'stale'})
    const event = fire(editor.view.contentDOM, 'cut', {clipboardData})
    expect(event.defaultPrevented).toBe(true)
    expect(clipboardData.data).toEqual({
      'text/plain': 'a1',
      'text/html': slice('a<x id="1"/>', 'a1'),
    })
    expect(editor.getValue()).toBe('b')
    editor.undo()
    expect(editor.getValue()).toBe('a<x id="1"/>b')
    expect(tagsOf(editor)).toEqual([{from: 1, to: 12}])
  })

  test('cut in the read-only source copies and deletes nothing', () => {
    const editor = mount('a<x id="1"/>b', {mode: 'source'})
    select(editor, 0, 12)
    const clipboardData = fakeTransfer()
    fire(editor.view.contentDOM, 'cut', {clipboardData})
    expect(clipboardData.data['text/html']).toBe(slice('a<x id="1"/>', 'a1'))
    expect(editor.getValue()).toBe('a<x id="1"/>b')
  })

  test('cut with an empty selection leaves the clipboard alone', () => {
    const editor = mount('ab')
    const clipboardData = fakeTransfer({'text/plain': 'kept'})
    fire(editor.view.contentDOM, 'cut', {clipboardData})
    expect(clipboardData.data).toEqual({'text/plain': 'kept'})
    expect(editor.getValue()).toBe('ab')
  })
})

describe('paste (buildPastedEditorState)', () => {
  test('a segment slice is inserted at the selection with its tags locked', () => {
    const editor = mount('abc')
    select(editor, 1, 2)
    const event = fire(editor.view.contentDOM, 'paste', {
      clipboardData: fakeTransfer({'text/html': slice('<x id="1"/>', '1')}),
    })
    expect(event.defaultPrevented).toBe(true)
    expect(editor.getValue()).toBe('a<x id="1"/>c')
    expect(tagsOf(editor)).toEqual([{from: 1, to: 12}])
  })

  test('outside text is inserted without its tag markup and makes no tags', () => {
    const editor = mount('ab')
    select(editor, 1, 1)
    fire(editor.view.contentDOM, 'paste', {
      clipboardData: fakeTransfer({
        'text/html': '<b>bold</b>',
        'text/plain': 'x<x id="1"/>y',
      }),
    })
    expect(editor.getValue()).toBe('axyb')
    expect(tagsOf(editor)).toEqual([])
  })

  test('a pasted degree sign is saved as a degree sign, not an nbsp, and gets no nbsp mark', () => {
    const editor = mount('ab')
    select(editor, 1, 1)
    fire(editor.view.contentDOM, 'paste', {
      clipboardData: fakeTransfer({'text/plain': '25°C'}),
    })
    expect(editor.getValue()).toBe('a25°Cb')
    expect(editor.view.contentDOM.querySelector('.special-char-nbsp')).toBeNull()
  })

  test('a paste into the read-only source changes nothing', () => {
    const editor = mount('ab', {mode: 'source'})
    select(editor, 1, 1)
    fire(editor.view.contentDOM, 'paste', {
      clipboardData: fakeTransfer({'text/plain': 'x'}),
    })
    expect(editor.getValue()).toBe('ab')
  })
})

describe('drag and drop (resolveDrop)', () => {
  // jsdom has no layout, so the drop point is given as a document position.
  const dropAt = (editor, pos, props = {}, target = editor.view.contentDOM) => {
    editor.view.posAtCoords = () => pos
    return fire(target, 'drop', props)
  }
  const dragOut = (editor, from, to) => {
    select(editor, from, to)
    const dataTransfer = fakeTransfer()
    fire(editor.view.contentDOM, 'dragstart', {dataTransfer})
    return dataTransfer
  }

  test('a drag writes the plain text without tag markup and the raw slice', () => {
    const editor = mount('a<x id="1"/>b')
    const dataTransfer = dragOut(editor, 0, 12)
    expect(dataTransfer.data['text/plain']).toBe('a1')
    expect(dataTransfer.data['text/html']).toBe(slice('a<x id="1"/>', 'a1'))
  })

  test('a slice dropped from elsewhere is inserted at the drop point with its tags', () => {
    const editor = mount('abc')
    const event = dropAt(editor, 2, {
      dataTransfer: fakeTransfer({'text/html': slice('<x id="1"/>', '1')}),
    })
    expect(event.defaultPrevented).toBe(true)
    expect(editor.getValue()).toBe('ab<x id="1"/>c')
    expect(tagsOf(editor)).toEqual([{from: 2, to: 13}])
  })

  test('a drag inside the editor moves the range, as one undo step', () => {
    const editor = mount('a<x id="1"/>bcd')
    const dataTransfer = dragOut(editor, 0, 12)
    dropAt(editor, 14, {dataTransfer})
    expect(editor.getValue()).toBe('bca<x id="1"/>d')
    expect(tagsOf(editor)).toEqual([{from: 3, to: 14}])
    expect(editor.getSelection().textWithTags).toBe('a<x id="1"/>')
    editor.undo()
    expect(editor.getValue()).toBe('a<x id="1"/>bcd')
  })

  test.each([['altKey'], ['ctrlKey']])(
    'a drag with %s copies instead of moving',
    (key) => {
      const editor = mount('a<x id="1"/>bcd')
      const dataTransfer = dragOut(editor, 0, 12)
      dropAt(editor, 14, {dataTransfer, [key]: true})
      expect(editor.getValue()).toBe('a<x id="1"/>bca<x id="1"/>d')
      expect(tagsOf(editor)).toEqual([
        {from: 1, to: 12},
        {from: 15, to: 26},
      ])
    },
  )

  test('a drop inside the dragged range changes nothing', () => {
    const editor = mount('abcd')
    const dataTransfer = dragOut(editor, 0, 3)
    const event = dropAt(editor, 2, {dataTransfer})
    expect(event.defaultPrevented).toBe(true)
    expect(editor.getValue()).toBe('abcd')
  })

  test('a drag that ended is no longer moved by a later drop', () => {
    const editor = mount('abcd')
    const dataTransfer = dragOut(editor, 0, 2)
    fire(editor.view.contentDOM, 'dragend')
    dropAt(editor, 4, {dataTransfer})
    expect(editor.getValue()).toBe('abcdab')
  })

  test('nothing is dropped inside a tag', () => {
    const editor = mount('a<x id="1"/>b')
    dropAt(editor, 5, {dataTransfer: fakeTransfer({'text/plain': 'z'})})
    expect(editor.getValue()).toBe('a<x id="1"/>b')
  })

  test('nothing is dropped onto a chip', () => {
    const editor = mount('a<x id="1"/>b')
    const chip = editor.view.contentDOM.querySelector('.tag')
    dropAt(editor, 1, {dataTransfer: fakeTransfer({'text/plain': 'z'})}, chip)
    expect(editor.getValue()).toBe('a<x id="1"/>b')
  })

  test('the target accepts a drop, the read-only source does not', () => {
    const target = mount('ab')
    const source = mount('ab', {mode: 'source'})
    expect(fire(target.view.contentDOM, 'dragover').defaultPrevented).toBe(true)
    expect(fire(source.view.contentDOM, 'dragover').defaultPrevented).toBe(
      false,
    )
    dropAt(source, 1, {dataTransfer: fakeTransfer({'text/plain': 'z'})})
    expect(source.getValue()).toBe('ab')
  })
})
