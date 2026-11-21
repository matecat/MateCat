/* eslint-disable testing-library/no-node-access --
   The CodeMirror marks are plain DOM with no roles or labels: they are found by their layer attribute. */
import {fireEvent} from '@testing-library/react'
import {createSegmentEditor} from './createSegmentEditor'
import {HIDE_DELAY, HOVER_TIME, layerHover, rangesUnder} from './layerHover'
import {SEARCH_LAYER, setLayer} from './layers'

let editor
let render

const mount = (value, layerList) => {
  render = jest.fn((view, {ranges, close}) => {
    const dom = document.createElement('div')
    dom.className = 'hover-tip'
    dom.textContent = ranges.map((r) => r.name).join(',')
    const button = dom.appendChild(document.createElement('button'))
    button.addEventListener('click', close)
    return {dom, destroy: jest.fn()}
  })
  editor = createSegmentEditor({
    parent: document.body.appendChild(document.createElement('div')),
    value,
    mode: 'target',
    extensions: [layerHover({render})],
  })
  editor.view.dispatch({
    effects: layerList.map(([name, ranges]) => setLayer.of({name, ranges})),
  })
}

const mark = (name) =>
  editor.view.contentDOM.querySelector(`[data-layer="${name}"]`)
const tip = () => document.querySelector('.hover-tip')
const line = () => editor.view.contentDOM.querySelector('.cm-line')
const wait = (ms) => jest.advanceTimersByTime(ms)

beforeEach(() => jest.useFakeTimers())
afterEach(() => {
  editor.destroy()
  jest.useRealTimers()
  document.body.innerHTML = ''
})

const LEXIQA = [['lexiqa', [{from: 4, to: 7, className: 'lx'}]]]

describe('layerHover: when the tooltip of a mark shows', () => {
  test('after the pointer rests on the mark for the hover time', () => {
    mount('one tow three', LEXIQA)
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HOVER_TIME - 1)
    expect(tip()).toBeNull()
    wait(1)
    expect(tip()).toHaveTextContent('lexiqa')
  })

  test('leaving the mark before the hover time shows nothing', () => {
    mount('one tow three', LEXIQA)
    fireEvent.mouseOver(mark('lexiqa'))
    fireEvent.mouseOut(mark('lexiqa'), {relatedTarget: line()})
    wait(HOVER_TIME)
    expect(tip()).toBeNull()
  })

  test('nothing for text outside the marks, nor for ranges the host has no tooltip for', () => {
    mount('one tow three', LEXIQA)
    fireEvent.mouseOver(line())
    wait(HOVER_TIME)
    expect(tip()).toBeNull()
    render.mockReturnValue(null)
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HOVER_TIME)
    expect(tip()).toBeNull()
  })

  test('nested marks offer every range covering the hovered text', () => {
    mount('one tow three', [
      ['lexiqa', [{from: 4, to: 7, className: 'lx'}]],
      ['blacklist', [{from: 0, to: 7, className: 'bl'}]],
    ])
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HOVER_TIME)
    expect(tip().textContent.split(',').sort()).toEqual(['blacklist', 'lexiqa'])
  })
})

describe('layerHover: a hoverable tooltip (C22)', () => {
  const showLexiqa = () => {
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HOVER_TIME)
  }

  test('it stays open while the pointer crosses the gap into it and stays there', () => {
    mount('one tow three', LEXIQA)
    showLexiqa()
    // Off the mark, over the gap: the tooltip waits
    fireEvent.mouseOut(mark('lexiqa'), {relatedTarget: line()})
    wait(HIDE_DELAY - 1)
    fireEvent.mouseEnter(tip())
    wait(HIDE_DELAY * 4)
    expect(tip()).not.toBeNull()
  })

  test('it closes a moment after the pointer has left both the mark and the tooltip', () => {
    mount('one tow three', LEXIQA)
    showLexiqa()
    fireEvent.mouseOut(mark('lexiqa'), {relatedTarget: tip()})
    fireEvent.mouseEnter(tip())
    fireEvent.mouseLeave(tip(), {relatedTarget: document.body})
    wait(HIDE_DELAY - 1)
    expect(tip()).not.toBeNull()
    wait(1)
    expect(tip()).toBeNull()
  })

  test('going back from the tooltip to its mark keeps it open', () => {
    mount('one tow three', LEXIQA)
    showLexiqa()
    fireEvent.mouseLeave(tip(), {relatedTarget: mark('lexiqa')})
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HIDE_DELAY * 2)
    expect(tip()).not.toBeNull()
    expect(render).toHaveBeenCalledTimes(1)
  })

  test('a button of the tooltip can close it', () => {
    mount('one tow three', LEXIQA)
    showLexiqa()
    tip().querySelector('button').click()
    expect(tip()).toBeNull()
  })

  test('another mark replaces it after the hover time', () => {
    mount('one tow three', [
      ['lexiqa', [{from: 4, to: 7, className: 'lx'}]],
      ['glossary', [{from: 8, to: 13, className: 'gl'}]],
    ])
    showLexiqa()
    fireEvent.mouseOut(mark('lexiqa'), {relatedTarget: mark('glossary')})
    fireEvent.mouseOver(mark('glossary'))
    wait(HOVER_TIME)
    expect(document.querySelectorAll('.hover-tip')).toHaveLength(1)
    expect(tip()).toHaveTextContent('glossary')
  })
})

describe('layerHover: when it closes by itself', () => {
  const shown = () => {
    mount('one tow three', LEXIQA)
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HOVER_TIME)
    expect(tip()).not.toBeNull()
  }

  test('an edit of the text', () => {
    shown()
    editor.view.dispatch({changes: {from: 0, insert: 'x'}})
    wait(0)
    expect(tip()).toBeNull()
  })

  test('the range it explains is gone (an ignored warning), not a change of another layer', () => {
    shown()
    editor.view.dispatch({
      effects: setLayer.of({
        name: 'glossary',
        ranges: [{from: 0, to: 3, className: 'gl'}],
      }),
    })
    wait(0)
    expect(tip()).not.toBeNull()
    editor.view.dispatch({effects: setLayer.of({name: 'lexiqa', ranges: []})})
    wait(0)
    expect(tip()).toBeNull()
  })

  test('a scroll of the page, and the editor going away', () => {
    shown()
    fireEvent.scroll(window)
    expect(tip()).toBeNull()
    fireEvent.mouseOver(mark('lexiqa'))
    wait(HOVER_TIME)
    editor.destroy()
    expect(tip()).toBeNull()
    editor = {destroy: () => {}}
  })
})

describe('rangesUnder', () => {
  test('hidden layers are not offered while search shows', () => {
    mount('one tow three', [
      ['lexiqa', [{from: 4, to: 7, className: 'lx'}]],
      [SEARCH_LAYER, [{from: 4, to: 7, className: 'searchMarker'}]],
    ])
    const hovered = rangesUnder(editor.view, mark(SEARCH_LAYER))
    expect(hovered.ranges.map((r) => r.name)).toEqual([SEARCH_LAYER])
    expect(mark('lexiqa')).toBeNull()
  })

  test('nothing for an element outside the editor', () => {
    mount('one tow', LEXIQA)
    const outside = document.body.appendChild(document.createElement('span'))
    outside.setAttribute('data-layer', 'lexiqa')
    expect(rangesUnder(editor.view, outside)).toBeNull()
    expect(rangesUnder(editor.view, null)).toBeNull()
  })
})
