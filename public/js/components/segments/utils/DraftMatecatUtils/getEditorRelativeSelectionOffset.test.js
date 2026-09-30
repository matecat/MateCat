import getEditorRelativeSelectionOffset from './getEditorRelativeSelectionOffset'

const rect = (overrides = {}) => ({
  x: 0,
  y: 0,
  top: 0,
  left: 0,
  right: 0,
  bottom: 0,
  width: 0,
  height: 0,
  ...overrides,
})

const editorNode = (overrides) => ({
  getBoundingClientRect: () => rect(overrides),
})

/** Stubs the one DOM read the function makes. */
const withSelectionRect = (overrides) => {
  window.getSelection = () => ({
    getRangeAt: () => ({getBoundingClientRect: () => rect(overrides)}),
  })
}

const realGetSelection = window.getSelection
afterEach(() => {
  window.getSelection = realGetSelection
})

test('positions below the selection, relative to the editor', () => {
  withSelectionRect({x: 120, left: 120, bottom: 200, height: 16})

  const offset = getEditorRelativeSelectionOffset(
    editorNode({x: 100, top: 150, right: 900}),
  )

  // bottom - editor.top + height, and x - editor.x
  expect(offset).toEqual({top: 200 - 150 + 16, left: 20})
})

test('pulls left when the selection is too close to the right edge', () => {
  // only 100px of room to the right, for an element that wants 300
  withSelectionRect({x: 500, left: 500, bottom: 200, height: 16})

  const offset = getEditorRelativeSelectionOffset(
    editorNode({x: 100, top: 150, right: 600}),
  )

  expect(offset.left).toBe(400 - (300 - 100))
})

test('respects a caller-supplied minimum width', () => {
  withSelectionRect({x: 500, left: 500, bottom: 200, height: 16})
  const node = editorNode({x: 100, top: 150, right: 600})

  // 100px of room; asking for only 80 needs no adjustment
  expect(getEditorRelativeSelectionOffset(node, 80).left).toBe(400)
})

test('falls back to a visible corner when the selection has no geometry', () => {
  // jsdom, or a range the browser cannot measure: every value is zero
  withSelectionRect({})

  expect(getEditorRelativeSelectionOffset(editorNode({}))).toEqual({
    top: 50,
    left: 50,
  })
})
