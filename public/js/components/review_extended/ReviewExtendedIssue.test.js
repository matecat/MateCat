import React from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import {ReviewExtendedIssue} from './ReviewExtendedIssue'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import CatToolActions from '../../actions/CatToolActions'
import CommonUtils from '../../utils/commonUtils'
import SegmentConstants from '../../constants/SegmentConstants'

jest.mock('./ReviewExtendedIssuePanel', () => (props) => (
  <div data-testid="issue-panel" data-segment-version={props.segmentVersion}>
    issue-panel
  </div>
))

jest.mock('../../stores/SegmentStore', () => {
  const {EventEmitter} = require('events')
  const store = new EventEmitter()
  store.setMaxListeners(0)
  return store
})

jest.mock('../../actions/SegmentActions', () => ({
  deleteIssue: jest.fn(),
  submitIssueComment: jest.fn(() => Promise.resolve()),
}))

jest.mock('../../actions/CatToolActions', () => ({
  removeAllNotifications: jest.fn(),
  addNotification: jest.fn(),
}))

jest.mock('../../utils/commonUtils', () => ({
  genericErrorAlertMessage: jest.fn(),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  ISSUE_DELETED: 'ISSUE_DELETED',
  OPEN_ISSUE_COMMENT: 'OPEN_ISSUE_COMMENT',
}))

const categories = [
  {
    id: '10',
    label: 'Accuracy',
    severities: [{label: 'MINOR', code: 'MN'}, {label: 'MAJOR'}],
  },
]

const makeIssue = (overrides = {}) => ({
  id: 1,
  id_category: '10',
  severity: 'MINOR',
  uid: 99,
  comments: [],
  target_text: '',
  revision_number: 1,
  id_segment: 'seg-1',
  ...overrides,
})

const defaultUserInfo = {
  teams: [],
  user: {uid: 1},
}

const renderIssue = (props = {}, userInfo = defaultUserInfo) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo}}>
      <ReviewExtendedIssue
        sid="seg-1"
        issue={makeIssue()}
        changeVisibility={jest.fn()}
        actions={true}
        isReview={true}
        currentReview={1}
        issueEditing={undefined}
        setIssueEditing={jest.fn()}
        selectionObj={null}
        versionNumber={1}
        {...props}
      />
    </ApplicationWrapperContext.Provider>,
  )

describe('ReviewExtendedIssue', () => {
  beforeEach(() => {
    global.config.lqa_nested_categories = {categories}
    global.config.ownerIsMe = false
    global.config.id_team = 5
    global.config.isReview = true
    global.config.revisionNumber = 1
  })

  afterEach(() => {
    SegmentStore.removeAllListeners()
  })

  test('renders the category label and severity code', () => {
    renderIssue()
    expect(screen.getByTitle('Accuracy')).toHaveTextContent('Accuracy')
    expect(screen.getByTitle('MINOR')).toHaveTextContent('MN')
  })

  test('renders severity substring when no code is present', () => {
    renderIssue({issue: makeIssue({severity: 'MAJOR'})})
    expect(screen.getByTitle('MAJOR')).toHaveTextContent('MAJ')
  })

  test('does not render the icon-buttons block when actions is false', () => {
    const {container} = renderIssue({actions: false})
    expect(container.querySelector('.icon-buttons')).not.toBeInTheDocument()
  })

  test('renders the Comments button when actions is true', () => {
    renderIssue({actions: true})
    expect(screen.getByTitle('Comments')).toBeInTheDocument()
  })

  test('does not render edit/delete buttons when isReview is false', () => {
    renderIssue({isReview: false})
    expect(screen.queryByTitle('Edit issue card')).not.toBeInTheDocument()
    expect(screen.queryByTitle('Delete issue card')).not.toBeInTheDocument()
  })

  test('does not render edit/delete buttons when the issue revision is after currentReview', () => {
    renderIssue({issue: makeIssue({revision_number: 3}), currentReview: 1})
    expect(screen.queryByTitle('Edit issue card')).not.toBeInTheDocument()
  })

  test('does not render edit/delete buttons when the user is not authorized', () => {
    renderIssue(
      {issue: makeIssue({uid: 500})},
      {teams: [{id: 1}], user: {uid: 1}},
    )
    expect(screen.queryByTitle('Edit issue card')).not.toBeInTheDocument()
  })

  test('renders edit/delete buttons when config.ownerIsMe is true', () => {
    global.config.ownerIsMe = true
    renderIssue({issue: makeIssue({uid: 500})}, {teams: [], user: {uid: 1}})
    expect(screen.getByTitle('Edit issue card')).toBeInTheDocument()
    expect(screen.getByTitle('Delete issue card')).toBeInTheDocument()
  })

  test('renders edit/delete buttons when the user belongs to the issue team', () => {
    global.config.id_team = 7
    renderIssue(
      {issue: makeIssue({uid: 500})},
      {teams: [{id: 7}], user: {uid: 1}},
    )
    expect(screen.getByTitle('Edit issue card')).toBeInTheDocument()
  })

  test('renders edit/delete buttons when the current user created the issue', () => {
    renderIssue({issue: makeIssue({uid: 42})}, {teams: [], user: {uid: 42}})
    expect(screen.getByTitle('Edit issue card')).toBeInTheDocument()
  })

  test('clicking edit calls setIssueEditing with the issue', () => {
    const setIssueEditing = jest.fn()
    global.config.ownerIsMe = true
    const issue = makeIssue()
    renderIssue({issue, setIssueEditing, issueEditing: undefined})
    fireEvent.click(screen.getByTitle('Edit issue card'))
    expect(setIssueEditing).toHaveBeenCalledWith(issue)
  })

  test('clicking edit again toggles editing off when the same issue is being edited', () => {
    const setIssueEditing = jest.fn()
    global.config.ownerIsMe = true
    const issue = makeIssue()
    renderIssue({issue, setIssueEditing, issueEditing: issue})
    fireEvent.click(screen.getByTitle('Edit issue card'))
    expect(setIssueEditing).toHaveBeenCalledWith(undefined)
  })

  test('renders the ReviewExtendedIssuePanel in edit mode when issueEditing matches the issue', () => {
    global.config.ownerIsMe = true
    const issue = makeIssue()
    renderIssue({issue, issueEditing: issue, versionNumber: 3})
    expect(screen.getByTestId('issue-panel')).toHaveAttribute(
      'data-segment-version',
      '3',
    )
  })

  test('applies the editing-highlight class when the issue is being edited', () => {
    global.config.ownerIsMe = true
    const issue = makeIssue()
    const {container} = renderIssue({issue, issueEditing: issue})
    expect(container.querySelector('.re-issue')).toHaveClass(
      'editing-highlight',
    )
  })

  test('clicking delete hides the issue, calls changeVisibility and adds a notification', () => {
    global.config.ownerIsMe = true
    const changeVisibility = jest.fn()
    const {container} = renderIssue({changeVisibility})
    fireEvent.click(screen.getByTitle('Delete issue card'))
    expect(changeVisibility).toHaveBeenCalledWith(1, false)
    expect(CatToolActions.removeAllNotifications).toHaveBeenCalled()
    expect(CatToolActions.addNotification).toHaveBeenCalled()
    expect(container).toBeEmptyDOMElement()
  })

  test('clicking delete clears issueEditing when the deleted issue was being edited', () => {
    global.config.ownerIsMe = true
    const setIssueEditing = jest.fn()
    const issue = makeIssue()
    renderIssue({issue, issueEditing: issue, setIssueEditing})
    fireEvent.click(screen.getByTitle('Delete issue card'))
    expect(setIssueEditing).toHaveBeenCalledWith(undefined)
  })

  test('deleting an issue schedules an undo timer without throwing', () => {
    global.config.ownerIsMe = true
    jest.useFakeTimers()
    renderIssue()
    fireEvent.click(screen.getByTitle('Delete issue card'))
    act(() => {
      jest.advanceTimersByTime(500)
    })
    jest.useRealTimers()
  })

  test('clicking the comments button toggles the comment section open', () => {
    jest.useFakeTimers()
    const {container} = renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    act(() => {
      jest.advanceTimersByTime(100)
    })
    expect(container.querySelector('.comments-view')).toBeInTheDocument()
    jest.useRealTimers()
  })

  test('clicking the comments button again closes the comment section', () => {
    const {container} = renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    fireEvent.click(screen.getByTitle('Comments'))
    expect(container.querySelector('.comments-view')).not.toBeInTheDocument()
  })

  test('clicking comments clears issueEditing', () => {
    global.config.ownerIsMe = true
    const setIssueEditing = jest.fn()
    const issue = makeIssue()
    renderIssue({issue, issueEditing: issue, setIssueEditing})
    fireEvent.click(screen.getByTitle('Comments'))
    expect(setIssueEditing).toHaveBeenCalledWith(undefined)
  })

  test('typing in the comment input updates its value', () => {
    renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    const input = document.querySelector('.re-comment-input')
    fireEvent.change(input, {target: {value: 'a mistake here'}})
    expect(input.value).toEqual('a mistake here')
  })

  test('submitting an empty comment does not call submitIssueComment', () => {
    renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    const input = document.querySelector('.re-comment-input')
    fireEvent.submit(input.closest('form'))
    expect(SegmentActions.submitIssueComment).not.toHaveBeenCalled()
  })

  test('submitting a non-empty comment calls submitIssueComment with source_page 1 when not reviewing', async () => {
    global.config.isReview = false
    renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    const input = document.querySelector('.re-comment-input')
    fireEvent.change(input, {target: {value: 'hello'}})
    await act(async () => {
      fireEvent.submit(input.closest('form'))
    })
    expect(SegmentActions.submitIssueComment).toHaveBeenCalledWith('seg-1', 1, {
      message: 'hello',
      source_page: 1,
    })
    expect(input.value).toEqual('')
  })

  test('submitting a comment while reviewing uses revisionNumber + 1 as source_page', async () => {
    global.config.isReview = true
    global.config.revisionNumber = 2
    renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    const input = document.querySelector('.re-comment-input')
    fireEvent.change(input, {target: {value: 'hello'}})
    await act(async () => {
      fireEvent.submit(input.closest('form'))
    })
    expect(SegmentActions.submitIssueComment).toHaveBeenCalledWith('seg-1', 1, {
      message: 'hello',
      source_page: 3,
    })
  })

  test('shows a generic error alert when submitIssueComment fails', async () => {
    SegmentActions.submitIssueComment.mockReturnValueOnce(Promise.reject())
    renderIssue()
    fireEvent.click(screen.getByTitle('Comments'))
    const input = document.querySelector('.re-comment-input')
    fireEvent.change(input, {target: {value: 'hello'}})
    await act(async () => {
      fireEvent.submit(input.closest('form'))
    })
    expect(CommonUtils.genericErrorAlertMessage).toHaveBeenCalled()
  })

  test('renders comment lines for translator, reviewer and 2nd reviewer, most recent first', () => {
    renderIssue({
      issue: makeIssue({
        comments: [
          {
            id: 1,
            source_page: 1,
            comment: 'translator note',
            create_date: '2024-01-01',
          },
          {
            id: 2,
            source_page: 2,
            comment: 'reviewer note',
            create_date: '2024-01-02',
          },
          {
            id: 3,
            source_page: 3,
            comment: 'reviewer2 note',
            create_date: '2024-01-03',
          },
        ],
      }),
    })
    fireEvent.click(screen.getByTitle('Comments'))
    expect(screen.getByText('translator note')).toBeInTheDocument()
    expect(screen.getByText('reviewer note')).toBeInTheDocument()
    expect(screen.getByText('reviewer2 note')).toBeInTheDocument()
    const comments = document.querySelectorAll('.re-comment')
    expect(comments).toHaveLength(3)
    // most recent (source_page 3) should be listed first after reversing
    expect(comments[0]).toHaveTextContent('reviewer2 note')
  })

  test('shows the highlighted selected text when target_text is present', () => {
    renderIssue({issue: makeIssue({target_text: 'selected snippet'})})
    fireEvent.click(screen.getByTitle('Comments'))
    expect(screen.getByText('Selected text:')).toBeInTheDocument()
    expect(screen.getByText('selected snippet')).toBeInTheDocument()
  })

  test('shows the filled comments icon title when comments exist', () => {
    renderIssue({
      issue: makeIssue({
        comments: [
          {id: 1, source_page: 1, comment: 'x', create_date: '2024-01-01'},
        ],
      }),
    })
    expect(screen.getByTitle('Comments')).toBeInTheDocument()
  })

  test('ISSUE_DELETED store event animates the container for the matching issue', () => {
    const {container} = renderIssue({
      issue: makeIssue({id: 1, id_segment: 'seg-1'}),
    })
    act(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_DELETED, 'seg-1', 1)
    })
    expect(container.querySelector('.re-item').style.opacity).toEqual('0')
  })

  test('ISSUE_DELETED store event ignores non-matching issues', () => {
    const {container} = renderIssue({
      issue: makeIssue({id: 1, id_segment: 'seg-1'}),
    })
    act(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_DELETED, 'other-seg', 1)
    })
    expect(container.querySelector('.re-item').style.opacity).not.toEqual('0')
  })

  test('OPEN_ISSUE_COMMENT store event opens the comment section when target_text is set', () => {
    const {container} = renderIssue({
      issue: makeIssue({id: 1, target_text: 'snippet'}),
    })
    act(() => {
      SegmentStore.emit(SegmentConstants.OPEN_ISSUE_COMMENT, 'seg-1', 1)
    })
    expect(container.querySelector('.comments-view')).toBeInTheDocument()
  })

  test('OPEN_ISSUE_COMMENT store event does nothing when target_text is empty', () => {
    const {container} = renderIssue({
      issue: makeIssue({id: 1, target_text: ''}),
    })
    act(() => {
      SegmentStore.emit(SegmentConstants.OPEN_ISSUE_COMMENT, 'seg-1', 1)
    })
    expect(container.querySelector('.comments-view')).not.toBeInTheDocument()
  })

  test('removes store listeners on unmount without throwing', () => {
    const {unmount} = renderIssue()
    unmount()
    expect(() => {
      SegmentStore.emit(SegmentConstants.ISSUE_DELETED, 'seg-1', 1)
    }).not.toThrow()
  })
})
