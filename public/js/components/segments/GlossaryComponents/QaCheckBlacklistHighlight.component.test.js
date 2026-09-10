import React from 'react'
import {render, screen} from '@testing-library/react'

import QaCheckBlacklistHighlight from './QaCheckBlacklistHighlight.component'
import TEXT_UTILS from '../../../utils/textUtils'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'

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
}

afterEach(() => {
  jest.clearAllMocks()
})

describe('QaCheckBlacklistHighlight.getTermDetails — space signature disabled', () => {
  const originalSpace = tagSignatures.space

  beforeEach(() => {
    tagSignatures.space = null
  })

  afterEach(() => {
    tagSignatures.space = originalSpace
  })

  const FakeDecoratedText = ({text}) => <span>{text}</span>

  test('matches using the decorated text of the first child', () => {
    const blackListedTerms = [
      {matching_words: ['hello'], source: {}, target: {term: 'hello'}},
    ]

    render(
      <QaCheckBlacklistHighlight
        {...baseProps}
        contentState={makeContentState('unused')}
        blackListedTerms={blackListedTerms}
        // eslint-disable-next-line react/no-children-prop -- explicit array needed to mirror children[0] access
        children={[<FakeDecoratedText text="  Hello  " key="0" />]}
      />,
    )

    expect(screen.getByTestId('tooltip-content')).toHaveTextContent(
      'hello is flagged as a forbidden word',
    )
  })
})

describe('QaCheckBlacklistHighlight render', () => {
  test('renders nothing when the highlighted range has no blacklisted term', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(20, 25),
    })

    const {container} = render(
      <QaCheckBlacklistHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        blackListedTerms={[{matching_words: ['hello']}]}
      >
        hello
      </QaCheckBlacklistHighlight>,
    )

    expect(container).toBeEmptyDOMElement()
  })

  test('shows a "forbidden translation" tooltip when a source term is set', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(0, 5),
    })

    render(
      <QaCheckBlacklistHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        blackListedTerms={[
          {
            matching_words: ['hello'],
            source: {term: 'ciao'},
            target: {term: 'hello'},
          },
        ]}
      >
        hello
      </QaCheckBlacklistHighlight>,
    )

    expect(screen.getByTestId('tooltip-content').textContent).toBe(
      'hello is flagged as a forbidden translation for ciao',
    )
  })

  test('shows a "forbidden word" tooltip when there is no source term', () => {
    TEXT_UTILS.getGlossaryMatchRegex.mockReturnValue({
      regex: /hello/i,
      regexCallback: (regex, block, callback) => callback(0, 5),
    })

    render(
      <QaCheckBlacklistHighlight
        {...baseProps}
        contentState={makeContentState('hello world')}
        blackListedTerms={[
          {matching_words: ['hello'], source: {}, target: {term: 'hello'}},
        ]}
      >
        hello
      </QaCheckBlacklistHighlight>,
    )

    expect(screen.getByTestId('tooltip-content').textContent).toBe(
      'hello is flagged as a forbidden word',
    )
  })
})
