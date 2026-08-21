import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'

import QaCheckGlossaryHighlight from './QaCheckGlossaryHighlight.component'
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

const FakeDecoratedText = ({text}) => <span>{text}</span>

afterEach(() => {
  jest.clearAllMocks()
})

describe('QaCheckGlossaryHighlight.getTermDetails — space signature enabled (default)', () => {
  test('returns undefined when the callback offsets do not overlap the highlight', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(20, 25),
    })

    render(
      <QaCheckGlossaryHighlight
        {...baseProps}
        contentState={makeContentState('hello world, unrelated text')}
        missingTerms={[{matching_words: ['hello']}]}
      >
        hello
      </QaCheckGlossaryHighlight>,
    )

    fireEvent.click(screen.getByText('hello'))

    expect(highlightGlossaryTerm).not.toHaveBeenCalled()
  })

  test('skips regex matching entirely when there are no missing terms', () => {
    render(
      <QaCheckGlossaryHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        missingTerms={[]}
      >
        hello
      </QaCheckGlossaryHighlight>,
    )

    fireEvent.click(screen.getByText('hello'))

    expect(TEXT_UTILS.getGlossaryMatchRegex).not.toHaveBeenCalled()
    expect(highlightGlossaryTerm).not.toHaveBeenCalled()
  })
})

describe('QaCheckGlossaryHighlight.getTermDetails — space signature disabled', () => {
  const originalSpace = tagSignatures.space

  beforeEach(() => {
    tagSignatures.space = null
  })

  afterEach(() => {
    tagSignatures.space = originalSpace
  })

  test('matches using the decorated text of the first child', () => {
    const missingTerms = [{matching_words: ['hello'], term_id: 3}]

    render(
      <QaCheckGlossaryHighlight
        {...baseProps}
        contentState={makeContentState('unused')}
        missingTerms={missingTerms}
        // eslint-disable-next-line react/no-children-prop -- explicit array needed to mirror children[0] access
        children={[<FakeDecoratedText text="  Hello  " key="0" />]}
      />,
    )

    fireEvent.click(screen.getByText('Hello'))

    expect(highlightGlossaryTerm).toHaveBeenCalledWith({
      sid: 42,
      termId: 3,
      type: 'check',
    })
  })
})

describe('QaCheckGlossaryHighlight render', () => {
  test('renders the static tooltip content and forwards clicks to onClickTerm', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(0, 5),
    })
    const missingTerms = [{matching_words: ['hello'], term_id: 11}]

    render(
      <QaCheckGlossaryHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        missingTerms={missingTerms}
      >
        hello
      </QaCheckGlossaryHighlight>,
    )

    expect(screen.getByTestId('tooltip-content').textContent).toBe(
      'Termbase translation not found in target',
    )

    fireEvent.click(screen.getByText('hello'))

    expect(highlightGlossaryTerm).toHaveBeenCalledWith({
      sid: 42,
      termId: 11,
      type: 'check',
    })
  })
})
