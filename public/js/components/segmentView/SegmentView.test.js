import React from 'react'
import {render, screen} from '@testing-library/react'
import {Base64} from 'js-base64'
import SegmentView from './SegmentView'
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
})
