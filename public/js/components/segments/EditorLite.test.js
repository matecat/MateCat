import React from 'react'
import {render, act} from '@testing-library/react'

// draft-js and DraftMatecatUtils are exercised for real here (same convention as
// Editarea.test.js) — only the Flux action layer is mocked.
jest.mock('../../actions/SegmentActions', () => ({
  copyFragmentToClipboard: jest.fn(),
}))

import SegmentActions from '../../actions/SegmentActions'
import {EditorLite} from './EditorLite'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

// Disable the whitespace-visualization dot (same convention as SimpleEditor.test.js)
// so assertions can match plain rendered text.
setTagSignatureMiddleware('space', () => false)

describe('EditorLite', () => {
  beforeEach(() => {
    global.config = {isTargetRTL: false}

    if (!navigator.clipboard) {
      Object.defineProperty(navigator, 'clipboard', {
        value: {writeText: jest.fn()},
        configurable: true,
      })
    } else {
      navigator.clipboard.writeText = jest.fn()
    }

    jest.clearAllMocks()
  })

  test('renders plain text content', () => {
    const {container} = render(
      <EditorLite content="Hello world" highlightSnippet={{}} />,
    )

    expect(container.textContent).toContain('Hello world')
  })

  test('renders content when config.isTargetRTL is true', () => {
    global.config.isTargetRTL = true

    const {container} = render(
      <EditorLite content="Ciao mondo" highlightSnippet={{}} />,
    )

    expect(container.textContent).toContain('Ciao mondo')
  })

  test('updates rendered content when the content prop changes', () => {
    const {container, rerender} = render(
      <EditorLite content="First version" highlightSnippet={{}} />,
    )
    expect(container.textContent).toContain('First version')

    rerender(<EditorLite content="Second version" highlightSnippet={{}} />)

    expect(container.textContent).toContain('Second version')
    expect(container.textContent).not.toContain('First version')
  })

  describe('imperative handle', () => {
    test('copyToClipboard copies the full plain text and dispatches the action', () => {
      const ref = React.createRef()
      render(
        <EditorLite ref={ref} content="Hello world" highlightSnippet={{}} />,
      )

      act(() => {
        ref.current.copyToClipboard()
      })

      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        'Hello world',
      )
      expect(SegmentActions.copyFragmentToClipboard).toHaveBeenCalledWith(
        expect.any(String),
        'Hello world',
      )
    })

    test('copyToClipboardHighlight copies the matched snippet when highlightSnippet matches content', () => {
      const ref = React.createRef()
      render(
        <EditorLite
          ref={ref}
          content="Hello world, nice to meet you"
          highlightSnippet={{text: 'nice to meet you'}}
        />,
      )

      act(() => {
        ref.current.copyToClipboardHighlight()
      })

      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        'nice to meet you',
      )
      expect(SegmentActions.copyFragmentToClipboard).toHaveBeenCalledWith(
        expect.any(String),
        'nice to meet you',
      )
    })

    test('copyToClipboardHighlight is a no-op when highlightSnippet has no text', () => {
      const ref = React.createRef()
      render(
        <EditorLite ref={ref} content="Hello world" highlightSnippet={{}} />,
      )

      act(() => {
        ref.current.copyToClipboardHighlight()
      })

      expect(SegmentActions.copyFragmentToClipboard).not.toHaveBeenCalled()
    })

    test('copyToClipboardHighlight is a no-op when the snippet text has no match in content', () => {
      const ref = React.createRef()
      render(
        <EditorLite
          ref={ref}
          content="Hello world"
          highlightSnippet={{text: 'not present anywhere'}}
        />,
      )

      act(() => {
        ref.current.copyToClipboardHighlight()
      })

      expect(SegmentActions.copyFragmentToClipboard).not.toHaveBeenCalled()
    })
  })
})
