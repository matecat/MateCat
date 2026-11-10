import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Base64} from 'js-base64'
import SegmentView from './SegmentView'
import {readSegmentClipboard} from './segmentModel'
import {SPECIAL_CHARS} from '../../utils/segmentText/specialChars'
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

describe('SegmentView line breaks', () => {
  const lineBreaks = (segment) => {
    const {container} = render(<SegmentView segment={segment} />)
    // eslint-disable-next-line testing-library/no-node-access, testing-library/no-container -- a <br> has no role or text to query by
    return container.querySelectorAll('br').length
  }

  test('a line feed breaks the line after its mark', () => {
    expect(lineBreaks('one##$_0A$##two##$_0A$##three')).toBe(2)
  })

  test('a CRLF is one line break, and so is a lone carriage return', () => {
    expect(lineBreaks('one##$_0D$####$_0A$##two')).toBe(1)
    expect(lineBreaks('one##$_0D$##two')).toBe(1)
  })

  test('a segment without line feeds stays on one line', () => {
    expect(lineBreaks('one <x id="1"/> two')).toBe(0)
  })
})

describe('SegmentView', () => {
  test('shows markup carried by the segment as text, never as HTML', () => {
    render(<SegmentView segment={'&lt;img src=x onerror=alert(1)&gt;'} />)
    expect(screen.getByText('<img src=x onerror=alert(1)>')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  test('shows a ph tag as its equiv-text, the label in its own element (what Tag.scss trims)', () => {
    render(<SegmentView segment={`Hi ${ph('&lt;br/&gt;')}there`} />)
    expect(
      screen.getByText('<br/>', {selector: '.tag.tag-ph > .tag-text'}),
    ).toBeInTheDocument()
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
      expect(
        screen.getByText('cd', {selector: '.tag > .tag-text'}),
      ).toBeInTheDocument()
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

describe('SegmentView special characters (the marks of the editor)', () => {
  const rootText = () => screen.getByTestId('view').textContent
  const spaceMarks = () =>
    screen.queryAllByText((_, el) =>
      el.classList.contains('special-char-space'),
    )

  test.each(Object.entries(SPECIAL_CHARS))(
    '%j shows its mark and tooltip, and the text keeps the character',
    (ch, {title}) => {
      render(<SegmentView data-testid="view" segment={`a${ch}b`} />)
      const mark = screen.getByLabelText(title)
      expect(mark).toHaveClass('special-char-mark')
      expect(mark).not.toHaveAttribute('title')
      expect(mark).not.toHaveClass('tag')
      // the drawing holds no text: the view reads as the segment's text
      expect(rootText()).toBe(`a${ch}b`)
    },
  )

  test('the nbsp, tab, word joiner and line feed tags are marks, not chips; the carriage return stays a chip', () => {
    render(
      <SegmentView
        data-testid="view"
        segment={'a##$_A0$##b##$_09$##c\u2060d##$_0A$##e##$_0D$##f'}
      />,
    )
    for (const title of [
      'Non-breaking space (U+00A0)',
      'Tab (U+0009)',
      'Word joiner (U+2060)',
      'Line break',
    ]) {
      expect(screen.getByLabelText(title)).not.toHaveClass('tag')
    }
    expect(screen.getByText(byClassAndText('tag-cr', '\n'))).toBeInTheDocument()
    expect(rootText()).toBe('a\u00a0b\tc\u2060d\ne\nf')
  })

  test('inside the label of a tag chip nothing becomes a mark', () => {
    render(<SegmentView segment={`x ${ph('a##$_A0$##b')}y`} />)
    expect(
      screen.queryByLabelText('Non-breaking space (U+00A0)'),
    ).not.toBeInTheDocument()
    expect(
      screen.getByText(byClassAndText('tag-ph', 'a°b')),
    ).toBeInTheDocument()
  })

  test('a mark is wrapped in a search mark like the character it stands for', () => {
    render(
      <SegmentView
        segment={'a\u2003b'}
        marks={[{start: 1, end: 3, className: 'searchMarker'}]}
      />,
    )
    expect(
      screen.getByLabelText('Em space (U+2003)', {
        selector: 'mark.searchMarker > span',
      }),
    ).toBeInTheDocument()
  })

  describe('spaces', () => {
    afterEach(() => initTagSignature({show_whitespace: 0}))

    test('are marked with showWhitespace, one mark per space, and stay spaces', () => {
      render(<SegmentView data-testid="view" segment="a  b" showWhitespace />)
      expect(spaceMarks()).toHaveLength(2)
      expect(spaceMarks()[0]).not.toHaveClass('tag')
      expect(rootText()).toBe('a  b')
    })

    test("without the prop follow the user's switch, and the prop wins over it", () => {
      initTagSignature({show_whitespace: 1})
      const {rerender} = render(<SegmentView segment="a b" />)
      expect(spaceMarks()).toHaveLength(1)
      rerender(<SegmentView segment="a b" showWhitespace={false} />)
      expect(spaceMarks()).toHaveLength(0)
    })

    test('a special character matched as a space keeps its own mark', () => {
      initTagSignature({show_whitespace: 1})
      render(<SegmentView data-testid="view" segment={'a\u2003b c'} />)
      expect(screen.getByLabelText('Em space (U+2003)')).toBeInTheDocument()
      expect(spaceMarks()).toHaveLength(1)
      expect(rootText()).toBe('a\u2003b c')
    })
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

  test('counts every mark as the one character it stands for', () => {
    render(
      <SegmentView
        data-testid="view"
        segment={'a\u2003b##$_A0$##c##$_0A$##d'}
        showWhitespace
      />,
    )
    const root = screen.getByTestId('view')
    select(textNode(root, 'a'), 1, textNode(root, 'd'), 0)

    const {data, slice} = copy(root)

    expect(slice).toBe('\u2003b##$_A0$##c##$_0A$##')
    expect(data['text/plain']).toBe('\u2003b\u00a0c\n')
  })

  test('a selection that ends inside a mark counts it whole', () => {
    render(<SegmentView data-testid="view" segment={'ab\u2003cd'} />)
    const root = screen.getByTestId('view')
    // from inside "ab" to the em space itself
    select(textNode(root, 'ab'), 1, textNode(root, '\u2003'), 1)

    expect(copy(root).slice).toBe('b\u2003')
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
