import $ from 'jquery'
import CursorUtils from './cursorUtils'

const buildContainer = (children) => {
  const div = document.createElement('div')
  children.forEach((child) => div.appendChild(child))
  return $(div)
}

test('getSelectionData computes offsets when selection nodes are direct children', () => {
  const firstText = document.createTextNode('Hello ')
  const secondText = document.createTextNode('World')
  const container = buildContainer([firstText, secondText])

  const selection = {
    anchorNode: firstText,
    anchorOffset: 2,
    focusNode: secondText,
    focusOffset: 3,
    toString: () => 'llo World',
  }

  const data = CursorUtils.getSelectionData(selection, container)

  expect(data.start_node).toBe(0)
  expect(data.start_offset).toBe(2)
  expect(data.end_node).toBe(0)
  // end node is the second text node (index 1), so offset accumulates the
  // length of the first node plus the focus offset
  expect(data.end_offset).toBe(firstText.textContent.length + 3)
  expect(data.selected_string).toBe('llo World')
})

test('getSelectionData falls back to the parent node when the selection node is nested', () => {
  const span = document.createElement('span')
  const nestedText = document.createTextNode('nested')
  span.appendChild(nestedText)

  const plainText = document.createTextNode('plain')
  const container = buildContainer([span, plainText])

  const selection = {
    anchorNode: nestedText,
    anchorOffset: 1,
    focusNode: nestedText,
    focusOffset: 4,
    toString: () => 'este',
  }

  const data = CursorUtils.getSelectionData(selection, container)

  expect(data.start_node).toBe(0)
  expect(data.start_offset).toBe(1)
  expect(data.end_node).toBe(0)
  expect(data.end_offset).toBe(4)
  expect(data.selected_string).toBe('este')
})

test('getSelectionData accumulates offset when the anchor is a later direct child', () => {
  const firstText = document.createTextNode('one')
  const secondText = document.createTextNode('two')
  const thirdText = document.createTextNode('three')
  const container = buildContainer([firstText, secondText, thirdText])

  const selection = {
    anchorNode: thirdText,
    anchorOffset: 2,
    focusNode: firstText,
    focusOffset: 1,
    toString: () => 'partial',
  }

  const data = CursorUtils.getSelectionData(selection, container)

  expect(data.start_node).toBe(0)
  expect(data.start_offset).toBe(
    firstText.textContent.length + secondText.textContent.length + 2,
  )
  expect(data.end_node).toBe(0)
  expect(data.end_offset).toBe(1)
  expect(data.selected_string).toBe('partial')
})
