import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import ReviewExtendedTranslationIssuesSideButton from './ReviewExtendedTranslationIssuesSideButton'
import SegmentActions from '../../actions/SegmentActions'
import SegmentUtils from '../../utils/segmentUtils'

jest.mock('../../actions/SegmentActions', () => ({
  openIssuesPanel: jest.fn(),
}))

jest.mock('../../utils/segmentUtils', () => ({
  isIceSegment: jest.fn(() => false),
}))

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    shortCutsKeyType: 'standard',
    cattol: {
      events: {
        openIssuesPanel: {
          keystrokes: {standard: 'ctrl+i'},
        },
      },
    },
  },
}))

const makeSegment = (overrides = {}) => ({
  versions: [],
  unlocked: false,
  ...overrides,
})

describe('ReviewExtendedTranslationIssuesSideButton', () => {
  beforeEach(() => {
    global.config.isReview = true
    SegmentUtils.isIceSegment.mockReturnValue(false)
  })

  test('renders nothing when config.isReview is false', () => {
    global.config.isReview = false
    const {container} = render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="1"
        segment={makeSegment()}
      />,
    )
    expect(container).toBeEmptyDOMElement()
  })

  test('renders nothing when segment is ICE-locked and not unlocked', () => {
    SegmentUtils.isIceSegment.mockReturnValue(true)
    const {container} = render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="1"
        segment={makeSegment({unlocked: false})}
      />,
    )
    expect(container).toBeEmptyDOMElement()
  })

  test('renders when segment is ICE-locked but unlocked', () => {
    SegmentUtils.isIceSegment.mockReturnValue(true)
    render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="1"
        segment={makeSegment({unlocked: true})}
      />,
    )
    expect(screen.getByText('+')).toBeInTheDocument()
  })

  test('shows the "+" placeholder and no-object class when there are no issues', () => {
    const {container} = render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="1"
        segment={makeSegment()}
      />,
    )
    expect(screen.getByText('+')).toBeInTheDocument()
    expect(container.querySelector('.revise-button')).toHaveClass('no-object')
  })

  test('shows the issue count and a "Show Issues" title when there are issues', () => {
    const {container} = render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="1"
        segment={makeSegment({versions: [{issues: [{id: 1}, {id: 2}]}]})}
      />,
    )
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(container.querySelector('.revise-button')).not.toHaveClass(
      'no-object',
    )
    expect(
      container.querySelector('.revise-button').getAttribute('title'),
    ).toEqual(expect.stringContaining('Show Issues'))
  })

  test('sums issue counts across multiple versions', () => {
    render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="1"
        segment={makeSegment({
          versions: [{issues: [{id: 1}]}, {issues: [{id: 2}, {id: 3}]}],
        })}
      />,
    )
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  test('clicking the button calls SegmentActions.openIssuesPanel with the segment sid', () => {
    const {container} = render(
      <ReviewExtendedTranslationIssuesSideButton
        sid="42"
        segment={makeSegment()}
      />,
    )
    fireEvent.click(container.querySelector('.revise-button'))
    expect(SegmentActions.openIssuesPanel).toHaveBeenCalledWith(
      {sid: '42'},
      true,
    )
  })
})
