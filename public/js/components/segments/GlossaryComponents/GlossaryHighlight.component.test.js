import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'

import GlossaryHighlight from './GlossaryHighlight.component'
import {highlightGlossaryTerm} from '../../../actions/segmentDispatchActions'
import TEXT_UTILS from '../../../utils/textUtils'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'

jest.mock('../../../actions/segmentDispatchActions', () => ({
  highlightGlossaryTerm: jest.fn(),
}))

jest.mock('../../../utils/textUtils', () => ({
  __esModule: true,
  default: {getGlossaryMatchRegex: jest.fn()},
}))

jest.mock('../utils/DraftMatecatUtils/tagModel', () => ({
  tagSignatures: {space: {regex: /\s/g, placeholder: '·'}},
}))

jest.mock('../../common/Tooltip', () => ({
  __esModule: true,
  default: ({children, content}) => (
    <div data-testid="tooltip">
      <div data-testid="tooltip-content">{content}</div>
      {children}
    </div>
  ),
}))

// Renders `props.text` as its own visible text node so tests can shape
// `children[0].props.text` the same way the space-disabled branch reads it.
const FakeDecoratedText = ({text}) => <span>{text}</span>

const makeContentState = (plainText) => ({
  getBlockBefore: jest.fn(() => null),
  getPlainText: () => plainText,
})

const baseProps = {
  start: 0,
  end: 5,
  blockKey: 'block-1',
  sid: 42,
}

// onClickTerm() reads `glossaryTerm.term_id` on whatever getTermDetails()
// returns, so a "no match" outcome throws when clicked — pre-existing
// behavior this migration must not "fix". The exception is thrown inside a
// native DOM event listener, so per the DOM spec it never reaches the code
// that called fireEvent.click (neither try/catch nor expect(fn).toThrow()
// observes it — verified empirically); it only surfaces as a `window` error
// event, which is what these tests listen for instead.
const clickAndCaptureThrow = (element) => {
  let caught = null
  const onError = (event) => {
    caught = event.error
    event.preventDefault()
  }
  window.addEventListener('error', onError)
  fireEvent.click(element)
  window.removeEventListener('error', onError)
  return caught
}

afterEach(() => {
  jest.clearAllMocks()
})

describe('GlossaryHighlight — space signature enabled (default)', () => {
  test('renders the tooltip content and dispatches highlightGlossaryTerm on click when the callback offsets overlap the highlight', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(0, 5),
    })
    const glossary = [{matching_words: ['hello'], term_id: 11}]

    render(
      <GlossaryHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        glossary={glossary}
      >
        hello
      </GlossaryHighlight>,
    )

    expect(screen.getByTestId('tooltip-content')).toHaveTextContent(
      'Termbase entry',
    )

    fireEvent.click(screen.getByText('hello'))

    expect(highlightGlossaryTerm).toHaveBeenCalledWith({
      sid: 42,
      termId: 11,
      type: 'glossary',
    })
  })

  test('does not dispatch when the callback offsets do not overlap the highlight (pre-existing throw on click)', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(20, 25),
    })

    const glossary = [{matching_words: ['hello'], term_id: 7}]

    render(
      <GlossaryHighlight
        {...baseProps}
        contentState={makeContentState('hello world, unrelated text')}
        glossary={glossary}
      >
        hello
      </GlossaryHighlight>,
    )

    const thrown = clickAndCaptureThrow(screen.getByText('hello'))

    expect(thrown).toBeInstanceOf(TypeError)
    expect(thrown.message).toMatch(/term_id/)
    expect(highlightGlossaryTerm).not.toHaveBeenCalled()
  })

  test('skips regex matching entirely when every glossary entry is a missingTerm', () => {
    const glossary = [{matching_words: ['hello'], missingTerm: true}]

    render(
      <GlossaryHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        glossary={glossary}
      >
        hello
      </GlossaryHighlight>,
    )

    // getTermDetails() returns undefined here too, so the click still throws —
    // what this test asserts is that getGlossaryMatchRegex was never reached.
    const thrown = clickAndCaptureThrow(screen.getByText('hello'))

    expect(thrown).toBeInstanceOf(TypeError)
    expect(thrown.message).toMatch(/term_id/)
    expect(TEXT_UTILS.getGlossaryMatchRegex).not.toHaveBeenCalled()
    expect(highlightGlossaryTerm).not.toHaveBeenCalled()
  })
})

describe('GlossaryHighlight — space signature disabled', () => {
  const originalSpace = tagSignatures.space

  beforeEach(() => {
    tagSignatures.space = null
  })

  afterEach(() => {
    tagSignatures.space = originalSpace
  })

  test('matches using the decorated text of the first child and dispatches on click', () => {
    const glossary = [{matching_words: ['hello'], term_id: 3}]

    render(
      <GlossaryHighlight
        {...baseProps}
        contentState={makeContentState('unused')}
        glossary={glossary}
      >
        {[<FakeDecoratedText text="  Hello  " key="child" />]}
      </GlossaryHighlight>,
    )

    fireEvent.click(screen.getByText('Hello'))

    expect(highlightGlossaryTerm).toHaveBeenCalledWith({
      sid: 42,
      termId: 3,
      type: 'glossary',
    })
  })

  test('does not dispatch when no term matches the decorated text (pre-existing throw on click)', () => {
    const glossary = [{matching_words: ['other'], term_id: 3}]

    render(
      <GlossaryHighlight
        {...baseProps}
        contentState={makeContentState('unused')}
        glossary={glossary}
      >
        {[<FakeDecoratedText text="hello" key="child" />]}
      </GlossaryHighlight>,
    )

    const thrown = clickAndCaptureThrow(screen.getByText('hello'))

    expect(thrown).toBeInstanceOf(TypeError)
    expect(thrown.message).toMatch(/term_id/)
    expect(highlightGlossaryTerm).not.toHaveBeenCalled()
  })
})
