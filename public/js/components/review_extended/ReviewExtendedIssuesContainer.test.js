import React from 'react'
import {act, render, screen, within} from '@testing-library/react'
import ReviewExtendedIssuesContainer from './ReviewExtendedIssuesContainer'
import {SegmentContext} from '../segments/SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import CatToolActions from '../../actions/CatToolActions'
import SegmentUtils from '../../utils/segmentUtils'
import SegmentConstants from '../../constants/SegmentConstants'

jest.mock('./ReviewExtendedIssue', () => ({
  ReviewExtendedIssue: (props) => (
    <div
      data-testid={`issue-${props.issue.id}`}
      data-actions={String(props.actions)}
    >
      <button onClick={() => props.changeVisibility(props.issue.id, false)}>
        hide-{props.issue.id}
      </button>
      {props.issue.id}
    </div>
  ),
}))

jest.mock('../../stores/SegmentStore', () => {
  const {EventEmitter} = require('events')
  const store = new EventEmitter()
  store.setMaxListeners(0)
  return store
})

jest.mock('../../actions/SegmentActions', () => ({
  openIssueComments: jest.fn(),
}))

jest.mock('../../actions/CatToolActions', () => ({
  removeAllNotifications: jest.fn(),
}))

jest.mock('../../utils/segmentUtils', () => ({
  isIceSegment: jest.fn(() => false),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  ISSUE_ADDED: 'ISSUE_ADDED',
}))

const flatCategories = [
  {id: '10', label: 'Accuracy', id_parent: null},
  {id: '11', label: 'Mistranslation', id_parent: '10'},
  {id: '20', label: 'Fluency', id_parent: null},
]

const nestedCategoriesFlatOnly = [
  {id: '10', label: 'Accuracy', subcategories: []},
  {id: '20', label: 'Fluency', subcategories: []},
]

const nestedCategoriesWithSub = [
  {id: '10', label: 'Accuracy', subcategories: [{id: '11'}]},
  {id: '20', label: 'Fluency', subcategories: []},
]

const makeIssue = (overrides = {}) => ({
  id: 1,
  id_category: '10',
  revision_number: 1,
  created_at: '2024-01-01T00:00:00Z',
  visible: true,
  ...overrides,
})

const segmentContext = {segment: {sid: 'seg-1', unlocked: false}}

const renderContainer = (props = {}, context = segmentContext) =>
  render(
    <SegmentContext.Provider value={context}>
      <ReviewExtendedIssuesContainer issues={[]} isReview={true} {...props} />
    </SegmentContext.Provider>,
  )

describe('ReviewExtendedIssuesContainer', () => {
  beforeEach(() => {
    global.config.lqa_flat_categories = flatCategories
    global.config.lqa_nested_categories = {categories: nestedCategoriesFlatOnly}
    global.config.secondRevisionsCount = 0
    global.config.revisionNumber = 1
    SegmentUtils.isIceSegment.mockReturnValue(false)
  })

  afterEach(() => {
    SegmentStore.removeAllListeners()
  })

  test('renders nothing when there are no issues', () => {
    const {container} = renderContainer({issues: []})
    expect(container).toBeEmptyDOMElement()
  })

  test('renders the "Issues" header and issue items when 2nd pass review is disabled', () => {
    renderContainer({issues: [makeIssue()]})
    expect(screen.getByText('Issues')).toBeInTheDocument()
    expect(screen.getByTestId('issue-1')).toBeInTheDocument()
  })

  test('renders a WrapperLoader overlay when loader prop is true', () => {
    const {container} = renderContainer({issues: [makeIssue()], loader: true})
    expect(container.querySelector('.overlayLoader')).toBeInTheDocument()
  })

  test('does not render a WrapperLoader overlay when loader prop is false', () => {
    const {container} = renderContainer({issues: [makeIssue()], loader: false})
    expect(container.querySelector('.overlayLoader')).not.toBeInTheDocument()
  })

  test('passes actions=true to issues when the segment is not ICE-locked', () => {
    renderContainer({issues: [makeIssue()]})
    expect(screen.getByTestId('issue-1')).toHaveAttribute(
      'data-actions',
      'true',
    )
  })

  test('passes actions=false to issues when the segment is ICE-locked and not unlocked', () => {
    SegmentUtils.isIceSegment.mockReturnValue(true)
    renderContainer(
      {issues: [makeIssue()]},
      {segment: {sid: 'seg-1', unlocked: false}},
    )
    expect(screen.getByTestId('issue-1')).toHaveAttribute(
      'data-actions',
      'false',
    )
  })

  test('passes actions=true to issues when the segment is ICE-locked but unlocked', () => {
    SegmentUtils.isIceSegment.mockReturnValue(true)
    renderContainer(
      {issues: [makeIssue()]},
      {segment: {sid: 'seg-1', unlocked: true}},
    )
    expect(screen.getByTestId('issue-1')).toHaveAttribute(
      'data-actions',
      'true',
    )
  })

  test('sorts issues by created_at descending (most recent first)', () => {
    renderContainer({
      issues: [
        makeIssue({id: 1, created_at: '2024-01-01T00:00:00Z'}),
        makeIssue({id: 2, created_at: '2024-06-01T00:00:00Z'}),
      ],
    })
    const testIds = screen
      .getAllByTestId(/^issue-/)
      .map((el) => el.getAttribute('data-testid'))
    expect(testIds).toEqual(['issue-2', 'issue-1'])
  })

  test('adds the re-issues-box-empty class once all issues are hidden via changeVisibility', () => {
    const {container} = renderContainer({issues: [makeIssue({id: 1})]})
    expect(container.querySelector('.re-issues-box')).not.toHaveClass(
      're-issues-box-empty',
    )
    act(() => {
      screen.getByText('hide-1').click()
    })
    expect(container.querySelector('.re-issues-box')).toHaveClass(
      're-issues-box-empty',
    )
  })

  test('componentDidUpdate resets visible to true when more issues are added', () => {
    const {container, rerender} = renderContainer({
      issues: [makeIssue({id: 1})],
    })
    act(() => {
      screen.getByText('hide-1').click()
    })
    expect(container.querySelector('.re-issues-box')).toHaveClass(
      're-issues-box-empty',
    )

    rerender(
      <SegmentContext.Provider value={segmentContext}>
        <ReviewExtendedIssuesContainer
          issues={[makeIssue({id: 1}), makeIssue({id: 2})]}
          isReview={true}
        />
      </SegmentContext.Provider>,
    )
    expect(container.querySelector('.re-issues-box')).not.toHaveClass(
      're-issues-box-empty',
    )
  })

  test('renders tab group with R1/R2 tabs when 2nd pass review is enabled', () => {
    global.config.secondRevisionsCount = 1
    global.config.revisionNumber = 1
    renderContainer({issues: [makeIssue({id: 1, revision_number: 1})]})
    expect(screen.getByText('R1 issues')).toBeInTheDocument()
    expect(screen.getByText('R2 issues')).toBeInTheDocument()
  })

  test('renders R2 issues in the tab group when revision_number is 2', () => {
    global.config.secondRevisionsCount = 1
    global.config.revisionNumber = 2
    renderContainer({
      issues: [
        makeIssue({
          id: 1,
          revision_number: 2,
          created_at: '2024-01-01T00:00:00Z',
        }),
      ],
    })
    const r2Tab = screen.getByText('R2 issues')
    expect(r2Tab).toHaveClass('active')
  })

  test('renders subcategory grouping (getSubCategoriesHtml) when nested categories have subcategories', () => {
    global.config.lqa_nested_categories = {categories: nestedCategoriesWithSub}
    renderContainer({issues: [makeIssue({id: 1, id_category: '11'})]})
    expect(screen.getByText('Accuracy')).toBeInTheDocument()
    expect(screen.getByTestId('issue-1')).toBeInTheDocument()
  })

  test('renders subcategory grouping with 2nd pass review enabled', () => {
    global.config.lqa_nested_categories = {categories: nestedCategoriesWithSub}
    global.config.secondRevisionsCount = 1
    global.config.revisionNumber = 1
    renderContainer({issues: [makeIssue({id: 1, id_category: '11'})]})
    expect(screen.getByText('R1 issues')).toBeInTheDocument()
    expect(screen.getByTestId('issue-1')).toBeInTheDocument()
  })

  test('emits ISSUE_ADDED and calls SegmentActions.openIssueComments for the matching segment', () => {
    jest.useFakeTimers()
    renderContainer({issues: [makeIssue({id: 1})]})
    act(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_ADDED, 'seg-1', 1)
    })
    act(() => {
      jest.advanceTimersByTime(200)
    })
    expect(SegmentActions.openIssueComments).toHaveBeenCalledWith('seg-1', 1)
    jest.useRealTimers()
  })

  test('does not call SegmentActions.openIssueComments for a non-matching segment', () => {
    jest.useFakeTimers()
    renderContainer({issues: [makeIssue({id: 1})]})
    act(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_ADDED, 'other-seg', 1)
    })
    act(() => {
      jest.advanceTimersByTime(200)
    })
    expect(SegmentActions.openIssueComments).not.toHaveBeenCalled()
    jest.useRealTimers()
  })

  test('calls CatToolActions.removeAllNotifications on unmount', () => {
    jest.useFakeTimers()
    const {unmount} = renderContainer({issues: [makeIssue({id: 1})]})
    unmount()
    act(() => {
      jest.runOnlyPendingTimers()
    })
    expect(CatToolActions.removeAllNotifications).toHaveBeenCalled()
    jest.useRealTimers()
  })
})
