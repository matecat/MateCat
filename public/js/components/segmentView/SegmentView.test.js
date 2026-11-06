import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Base64} from 'js-base64'
import SegmentView from './SegmentView'
import {readSegmentClipboard} from './segmentModel'
import {
  initTagSignature,
  setTagSignatureMiddleware,
} from '../segments/utils/DraftMatecatUtils/tagModel'

// The space placeholder is a user option (show_whitespace), off by default.
beforeAll(() => initTagSignature({show_whitespace: 0}))
afterAll(() => setTagSignatureMiddleware('space', () => undefined))

const ph = (html, id = 'm1') =>
  `<ph id="${id}" ctype="x-html" equiv-text="base64:${Base64.encode(html)}"/>`

// Matches the element with the given class whose whole text is `text`.
const byClassAndText = (className, text) => (_, element) =>
  element.classList.contains(className) && element.textContent === text

describe('SegmentView', () => {
  test('shows markup carried by the segment as text, never as HTML', () => {
    render(<SegmentView segment={'&lt;img src=x onerror=alert(1)&gt;'} />)
    expect(screen.getByText('<img src=x onerror=alert(1)>')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  test('shows a ph tag as its equiv-text', () => {
    render(<SegmentView segment={`Hi ${ph('&lt;br/&gt;')}there`} />)
    expect(screen.getByText('<br/>')).toHaveClass('tag', 'tag-ph')
  })

  test('shows the number of a compressible tag before its content', () => {
    render(
      <SegmentView
        segment={`<ph id="m1" ctype="x-original_pc_open" equiv-text="base64:${Base64.encode('&lt;b&gt;')}"/>x`}
      />,
    )
    expect(screen.getByText('1')).toHaveClass('index-counter')
    expect(screen.getByText('<b>')).toHaveAttribute('data-text', 'true')
  })

  test('applies className to the root', () => {
    render(<SegmentView segment="abc" className="qr-text-inner" />)
    expect(screen.getByText('abc')).toHaveClass('qr-text-inner')
  })

  describe('with compareTo', () => {
    test('wraps removed text in .deleted and added text in .added', () => {
      render(<SegmentView compareTo="Ciao mondo" segment="Ciao bel mondo" />)
      expect(
        screen.getByText(byClassAndText('added', 'bel ')),
      ).toBeInTheDocument()
      expect(
        screen.queryByText((_, element) =>
          element.classList.contains('deleted'),
        ),
      ).not.toBeInTheDocument()
    })

    test('wraps a removed tag whole in .deleted', () => {
      render(
        <SegmentView
          compareTo={`a ${ph('&lt;b&gt;')}b`}
          segment={`a ${ph('&lt;i&gt;')}b`}
        />,
      )
      expect(
        screen.getByText(byClassAndText('deleted', '<b>')),
      ).toBeInTheDocument()
      expect(
        screen.getByText(byClassAndText('added', '<i>')),
      ).toBeInTheDocument()
    })

    test('shows the whole segment as added when compared with an empty one', () => {
      render(<SegmentView compareTo="" segment="new" />)
      expect(screen.getByText('new')).toHaveClass('added')
    })
  })

  describe('marks', () => {
    test('wraps the marked text in a mark with its class', () => {
      render(
        <SegmentView
          segment="find &amp; replace"
          marks={[{start: 5, end: 6, className: 'searchMarker'}]}
        />,
      )
      expect(screen.getByText('&', {selector: 'mark'})).toHaveClass(
        'searchMarker',
      )
    })

    test('never puts a mark inside a tag chip', () => {
      render(
        <SegmentView
          segment={`ab${ph('cd')}ef`}
          marks={[{start: 3, end: 5, className: 'm'}]}
        />,
      )
      expect(screen.getByText('cd')).toHaveClass('tag')
      expect(screen.getByText('ef', {selector: 'mark'})).toBeInTheDocument()
    })

    test('ignores marks when showing a diff', () => {
      render(
        <SegmentView
          segment="new"
          compareTo="old"
          marks={[{start: 0, end: 3, className: 'm'}]}
        />,
      )
      expect(
        screen.queryByText(/.*/, {selector: 'mark'}),
      ).not.toBeInTheDocument()
    })
  })

  test('renders the root element asked for, with the extra props', () => {
    render(<SegmentView as="p" lang="it" data-testid="root" segment="x" />)
    expect(screen.getByTestId('root')).toHaveAttribute('lang', 'it')
    expect(screen.getByText('x', {selector: 'p'})).toBeInTheDocument()
  })
})

describe('copying from a SegmentView', () => {
  // The text node of `root` whose content is `text`.
  const textNode = (root, text) => {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
    while (walker.nextNode()) {
      if (walker.currentNode.data === text) return walker.currentNode
    }
    throw new Error(`no text node "${text}"`)
  }
  const select = (start, startOffset, end, endOffset) => {
    const range = document.createRange()
    range.setStart(start, startOffset)
    range.setEnd(end, endOffset)
    document.getSelection().removeAllRanges()
    document.getSelection().addRange(range)
  }
  const copy = (element) => {
    const data = {}
    const clipboardData = {setData: (type, value) => (data[type] = value)}
    const prevented = !fireEvent.copy(element, {clipboardData})
    return {prevented, data, slice: readSegmentClipboard(data['text/html'])}
  }
  afterEach(() => document.getSelection().removeAllRanges())

  test('copies the selection as a segment slice, a partly selected chip whole', () => {
    render(<SegmentView data-testid="view" segment='ab<x id="7"/>cd' />)
    const root = screen.getByTestId('view')
    // from inside "ab" to inside the chip's label
    select(textNode(root, 'ab'), 1, textNode(root, '7'), 0)

    const {prevented, data, slice} = copy(root)

    expect(prevented).toBe(true)
    expect(slice).toBe('b<x id="7"/>')
    expect(data['text/plain']).toBe('b7')
  })

  test('copies text encoded as the segment stores it, and nbsp as its placeholder', () => {
    render(<SegmentView data-testid="view" segment="1 &lt; 2##$_A0$##&amp;" />)
    const root = screen.getByTestId('view')
    const range = document.createRange()
    range.selectNodeContents(root)
    document.getSelection().addRange(range)

    const {data, slice} = copy(root)

    expect(slice).toBe('1 &lt; 2##$_A0$##&amp;')
    expect(data['text/plain']).toBe('1 < 2\u00a0&')
  })

  test('counts marked text like any other text', () => {
    render(
      <SegmentView
        data-testid="view"
        segment='one <g id="1">two</g> three'
        marks={[{start: 0, end: 3, className: 'searchMarker'}]}
      />,
    )
    const root = screen.getByTestId('view')
    select(textNode(root, 'two'), 0, textNode(root, ' three'), 3)

    expect(copy(root).slice).toBe('two</g> th')
  })

  test('copies only the new side of a diff', () => {
    render(
      <SegmentView
        data-testid="view"
        segment='the red <x id="1"/>car'
        compareTo='the blue <x id="1"/>car'
      />,
    )
    const root = screen.getByTestId('view')
    const range = document.createRange()
    range.selectNodeContents(root)
    document.getSelection().addRange(range)
    expect(copy(root).slice).toBe('the red <x id="1"/>car')

    // a selection starting inside a removed part starts where it was
    select(textNode(root, 'blue'), 2, textNode(root, 'car'), 1)
    expect(copy(root).slice).toBe('red <x id="1"/>c')
  })

  test('leaves a selection that runs outside the view to the browser', () => {
    render(
      <div>
        <p>outside</p>
        <SegmentView data-testid="view" segment="inside" />
      </div>,
    )
    const root = screen.getByTestId('view')
    select(textNode(document.body, 'outside'), 2, textNode(root, 'inside'), 3)

    const {prevented, data} = copy(root)

    expect(prevented).toBe(false)
    expect(data).toEqual({})
  })
})
