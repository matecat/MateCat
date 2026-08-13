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

afterEach(() => {
  jest.clearAllMocks()
})

describe('GlossaryHighlight.getTermDetails — space signature enabled (default)', () => {
  test('finds the matching glossary term through the regex callback', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(0, 5),
    })

    const glossary = [
      {matching_words: ['hello'], term_id: 7, source: {term: 'ciao'}, target: {term: 'hello'}},
    ]

    const instance = new GlossaryHighlight({
      ...baseProps,
      contentState: makeContentState('hello world'),
      glossary,
      children: [],
    })

    expect(instance.getTermDetails()).toEqual(glossary[0])
  })

  test('returns undefined when the callback offsets do not overlap the highlight', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(20, 25),
    })

    const glossary = [{matching_words: ['hello'], term_id: 7}]

    const instance = new GlossaryHighlight({
      ...baseProps,
      contentState: makeContentState('hello world, unrelated text'),
      glossary,
      children: [],
    })

    expect(instance.getTermDetails()).toBeUndefined()
  })

  test('skips regex matching entirely when every term is a missingTerm', () => {
    const glossary = [{matching_words: ['hello'], missingTerm: true}]

    const instance = new GlossaryHighlight({
      ...baseProps,
      contentState: makeContentState('hello world'),
      glossary,
      children: [],
    })

    expect(instance.getTermDetails()).toBeUndefined()
    expect(TEXT_UTILS.getGlossaryMatchRegex).not.toHaveBeenCalled()
  })
})

describe('GlossaryHighlight.getTermDetails — space signature disabled', () => {
  const originalSpace = tagSignatures.space

  beforeEach(() => {
    tagSignatures.space = null
  })

  afterEach(() => {
    tagSignatures.space = originalSpace
  })

  test('matches using the decorated text of the first child', () => {
    const glossary = [{matching_words: ['hello'], term_id: 3}]
    const instance = new GlossaryHighlight({
      ...baseProps,
      contentState: makeContentState('unused'),
      glossary,
      children: [{props: {text: '  Hello  '}}],
    })

    expect(instance.getTermDetails()).toEqual(glossary[0])
  })

  test('returns undefined when no term matches the decorated text', () => {
    const glossary = [{matching_words: ['other'], term_id: 3}]
    const instance = new GlossaryHighlight({
      ...baseProps,
      contentState: makeContentState('unused'),
      glossary,
      children: [{props: {text: 'hello'}}],
    })

    expect(instance.getTermDetails()).toBeUndefined()
  })
})

describe('GlossaryHighlight.onClickTerm', () => {
  test('dispatches highlightGlossaryTerm with the matched term id', () => {
    const instance = new GlossaryHighlight({
      ...baseProps,
      contentState: makeContentState('unused'),
      glossary: [],
      children: [],
    })
    jest.spyOn(instance, 'getTermDetails').mockReturnValue({term_id: 99})

    instance.onClickTerm()

    expect(highlightGlossaryTerm).toHaveBeenCalledWith({
      sid: 42,
      termId: 99,
      type: 'glossary',
    })
  })
})

describe('GlossaryHighlight render', () => {
  test('renders the tooltip content and forwards clicks to onClickTerm', () => {
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

    expect(screen.getByTestId('tooltip-content').textContent).toBe(
      'Termbase entry',
    )

    fireEvent.click(screen.getByText('hello'))

    expect(highlightGlossaryTerm).toHaveBeenCalledWith({
      sid: 42,
      termId: 11,
      type: 'glossary',
    })
  })
})
