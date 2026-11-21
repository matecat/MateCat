/* eslint-disable testing-library/no-node-access, testing-library/no-container --
   The chips are presentational spans with no roles or labels: they are found by class. */
import React from 'react'
import {act, fireEvent, render, within} from '@testing-library/react'
import {Base64} from 'js-base64'
import SegmentView from './SegmentView'
import {showsTagTooltip} from './tagTooltip'

const ph = (id, text, ctype = 'x-html') =>
  `<ph id="mtc_${id}" ctype="${ctype}" equiv-text="base64:${Base64.encode(text)}"/>`

const LONG =
  '<a href="https://www.matecat.com/a/very/long/path/that/never/fits" class="link">'

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

beforeEach(() => jest.useFakeTimers())
afterEach(() => {
  act(() => jest.runOnlyPendingTimers())
  jest.useRealTimers()
  document.body.classList.remove('tags-compressed')
})

const shownTooltip = () => document.querySelector('.tag-full-text-tooltip')
const chips = (container) => [...container.querySelectorAll('.tag')]
const hover = (chip, from = document.body) => {
  fireEvent.mouseOver(chip, {relatedTarget: from})
  act(() => jest.advanceTimersByTime(300))
}
const leave = (chip, to = document.body) => {
  fireEvent.mouseOut(chip, {relatedTarget: to})
  act(() => jest.runOnlyPendingTimers())
}

describe('SegmentView tag tooltip (closed segments, matches, QR)', () => {
  test('a trimmed ph chip shows its full text above it, as in the editor', () => {
    const {container} = render(<SegmentView segment={'see ' + ph('1', LONG)} />)
    const [chip] = chips(container)
    hover(chip)
    const text = within(shownTooltip()).getByText(LONG.replace(/ /g, '·'))
    expect(text.parentElement).toHaveClass('tag', 'tag-ph')
  })

  test('moving inside the chip keeps one tooltip; leaving it hides it', () => {
    const {container} = render(<SegmentView segment={'see ' + ph('1', LONG)} />)
    const [chip] = chips(container)
    hover(chip)
    const label = chip.querySelector('.tag-text')
    fireEvent.mouseOut(chip, {relatedTarget: label})
    fireEvent.mouseOver(label, {relatedTarget: chip})
    act(() => jest.runOnlyPendingTimers())
    expect(document.querySelectorAll('.tag-full-text-tooltip')).toHaveLength(1)
    leave(chip)
    expect(shownTooltip()).toBeNull()
  })

  test('a chip that fits, plain text, and a pressed chip show none', () => {
    const {container} = render(
      <SegmentView segment={'see ' + ph('1', '<br/>') + ' ' + ph('2', LONG)} />,
    )
    const [short, long] = chips(container)
    hover(short)
    expect(shownTooltip()).toBeNull()
    hover(container.firstChild)
    expect(shownTooltip()).toBeNull()
    hover(long)
    fireEvent.mouseDown(long)
    act(() => jest.runOnlyPendingTimers())
    expect(shownTooltip()).toBeNull()
  })

  test('with the tags compressed an opening tag shows its content, its closing tag never does', () => {
    document.body.classList.add('tags-compressed')
    const {container} = render(
      <SegmentView
        segment={
          ph('1', '<b>', 'x-original_pc_open') +
          'bold' +
          ph('2', '</b>', 'x-original_pc_close')
        }
      />,
    )
    const [open, close] = chips(container)
    hover(close)
    expect(shownTooltip()).toBeNull()
    hover(open)
    expect(within(shownTooltip()).getByText('<b>')).toBeInTheDocument()
  })

  test('hover handlers of the caller still run', () => {
    const onMouseOver = jest.fn()
    const onMouseOut = jest.fn()
    const onMouseDown = jest.fn()
    const {container} = render(
      <SegmentView
        segment="text"
        onMouseOver={onMouseOver}
        onMouseOut={onMouseOut}
        onMouseDown={onMouseDown}
      />,
    )
    fireEvent.mouseOver(container.firstChild)
    fireEvent.mouseOut(container.firstChild)
    fireEvent.mouseDown(container.firstChild)
    expect(onMouseOver).toHaveBeenCalled()
    expect(onMouseOut).toHaveBeenCalled()
    expect(onMouseDown).toHaveBeenCalled()
  })
})

describe('showsTagTooltip (TagEntity rule)', () => {
  const chip = (extra) => ({
    name: 'ph',
    index: null,
    role: null,
    truncated: false,
    ...extra,
  })

  test('trimmed chips of a type with tooltips, or numbered chips while compressed', () => {
    expect(showsTagTooltip(chip({truncated: true}), false)).toBe(true)
    expect(showsTagTooltip(chip({truncated: true, name: 'g'}), false)).toBe(
      false,
    )
    expect(showsTagTooltip(chip({index: 0}), true)).toBe(true)
    expect(showsTagTooltip(chip({index: 0}), false)).toBe(false)
  })

  test('never a closing tag', () => {
    expect(
      showsTagTooltip(chip({role: 'close', index: 0, truncated: true}), true),
    ).toBe(false)
  })
})
