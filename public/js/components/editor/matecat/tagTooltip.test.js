/* eslint-disable testing-library/no-node-access --
   The chips are plain CodeMirror DOM with no roles or labels: they are found by class. */
import {act, fireEvent, within} from '@testing-library/react'
import {Base64} from 'js-base64'
import {createSegmentEditor} from '../core/createSegmentEditor'
import {createTagTooltip} from './tagTooltip'

const mockIsTagsCompressed = jest.fn(() => false)
jest.mock('../../../stores/CatToolStore', () => ({
  isTagsCompressed: () => mockIsTagsCompressed(),
}))

const ph = (id, text, ctype = 'x-html') =>
  `<ph id="${id}" ctype="${ctype}" equiv-text="base64:${Base64.encode(text)}"/>`

const LONG =
  '<a href="https://www.matecat.com/a/very/long/path/that/never/fits" class="link">'
// The chip, and the tooltip, show the spaces of a tag as the space placeholder.
const LONG_LABEL = LONG.replace(/ /g, '\u00b7')

// jsdom has no layout: a text box is 7px per character, trimmed by Tag.scss at 320px.
beforeAll(() => {
  jest
    .spyOn(HTMLElement.prototype, 'scrollWidth', 'get')
    .mockImplementation(function () {
      return this.textContent.length * 7
    })
  jest
    .spyOn(HTMLElement.prototype, 'offsetWidth', 'get')
    .mockImplementation(function () {
      return Math.min(this.textContent.length * 7, 320)
    })
})

let editor
let tooltip

const mount = (value) => {
  tooltip = createTagTooltip()
  const parent = document.body.appendChild(document.createElement('div'))
  editor = createSegmentEditor({
    parent,
    value,
    mode: 'target',
    onEvent: tooltip.onEvent,
    extensions: [tooltip.extension],
  })
  return [...parent.querySelectorAll('.tag')]
}

const hover = (chip) => {
  fireEvent.mouseEnter(chip)
  act(() => jest.advanceTimersByTime(300))
}

const shownTooltip = () => document.querySelector('.tag-full-text-tooltip')

beforeEach(() => {
  jest.useFakeTimers()
  mockIsTagsCompressed.mockReturnValue(false)
})

afterEach(() => {
  act(() => {
    tooltip.destroy()
    editor.destroy()
    jest.runOnlyPendingTimers()
  })
  jest.useRealTimers()
  document.body.innerHTML = ''
})

describe('tag chip tooltip (TagEntity)', () => {
  test('a trimmed ph chip shows its full text above the chip after the delay', () => {
    const [chip] = mount('see ' + ph('1', LONG))
    fireEvent.mouseEnter(chip)
    act(() => jest.advanceTimersByTime(299))
    expect(shownTooltip()).toBeNull()

    act(() => jest.advanceTimersByTime(1))
    const text = within(shownTooltip()).getByText(LONG_LABEL)
    // Rendered as a tag, without the editor's trimming class
    expect(text.parentElement).toHaveClass('tag', 'tag-ph')
    expect(text.parentElement).not.toHaveClass('small')
  })

  test('a ph chip that fits shows no tooltip', () => {
    const [chip] = mount('see ' + ph('1', '<br/>'))
    hover(chip)
    expect(shownTooltip()).toBeNull()
  })

  test('a trimmed tag of a type without tooltips shows none', () => {
    const [chip] = mount('see <x id="' + 'x'.repeat(60) + '"/>')
    hover(chip)
    expect(shownTooltip()).toBeNull()
  })

  test('a closing tag never shows one, even trimmed or compressed', () => {
    mockIsTagsCompressed.mockReturnValue(true)
    const chips = mount(
      ph('1', '<b>', 'x-original_pc_open') +
        'bold' +
        ph('2', '</b>' + ' '.repeat(80), 'x-original_pc_close'),
    )
    hover(chips[1])
    expect(shownTooltip()).toBeNull()
  })

  test('a compressed opening tag shows its content although it fits', () => {
    mockIsTagsCompressed.mockReturnValue(true)
    const [open] = mount(
      ph('1', '<b>', 'x-original_pc_open') +
        'bold' +
        ph('2', '</b>', 'x-original_pc_close'),
    )
    hover(open)
    expect(within(shownTooltip()).getByText('<b>')).toBeInTheDocument()
  })

  test('leaving the chip before the delay shows nothing; leaving after hides it', () => {
    const [chip] = mount('see ' + ph('1', LONG))
    fireEvent.mouseEnter(chip)
    fireEvent.mouseLeave(chip)
    act(() => jest.advanceTimersByTime(300))
    expect(shownTooltip()).toBeNull()

    hover(chip)
    fireEvent.mouseLeave(chip)
    act(() => jest.runOnlyPendingTimers())
    expect(shownTooltip()).toBeNull()
  })

  test('clicking the chip, scrolling or editing hides it', () => {
    const [chip] = mount('see ' + ph('1', LONG))
    hover(chip)
    fireEvent.mouseDown(chip, {button: 0})
    act(() => jest.runOnlyPendingTimers())
    expect(shownTooltip()).toBeNull()

    hover(editor.view.dom.querySelector('.tag'))
    fireEvent.scroll(window)
    act(() => jest.runOnlyPendingTimers())
    expect(shownTooltip()).toBeNull()

    hover(editor.view.dom.querySelector('.tag'))
    act(() => {
      editor.view.dispatch({changes: {from: 0, insert: 'x'}})
      jest.runOnlyPendingTimers()
    })
    expect(shownTooltip()).toBeNull()
  })
})
