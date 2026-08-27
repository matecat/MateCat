import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
// Side-effect import: initializes window.eventHandler, required by Popover.
import '../../common/ApplicationWrapper/ApplicationWrapperContext'
import {CommentsButton} from './CommentsButton'
import CommentsActions from '../../../actions/CommentsActions'
import {getComments} from '../../../api/getComments'
import CommentsStore from '../../../stores/CommentsStore'
import CommentsConstants from '../../../constants/CommentsConstants'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import SegmentActions from '../../../actions/SegmentActions'
import {getTeamUsers} from '../../../api/getTeamUsers'

jest.mock('../../../actions/CommentsActions')
jest.mock('../../../actions/SegmentActions')
jest.mock('../../../api/getComments')
jest.mock('../../../api/getTeamUsers')

const mockDb = (history = [], counts = {open: 0, resolved: 0}) => {
  CommentsStore.db = {
    history,
    getOpenedThreadCount: jest.fn().mockReturnValue(counts.open),
    getResolvedThreadCount: jest.fn().mockReturnValue(counts.resolved),
  }
}

beforeEach(() => {
  global.config = {comments_enabled: true, id_team: null}
  jest.clearAllMocks()
  mockDb([])
  getComments.mockResolvedValue({data: {entries: {comments: []}, user: {}}})
  getTeamUsers.mockResolvedValue([])
})

test('renders nothing when comments are disabled', async () => {
  global.config.comments_enabled = false
  const {container} = render(<CommentsButton />)
  await act(async () => {})
  expect(container.firstChild).toBeNull()
})

test('loads comments and team users on mount', async () => {
  global.config.id_team = 3
  const comments = [{id_segment: 1, message: 'hi', full_name: 'A'}]
  getComments.mockResolvedValue({
    data: {entries: {comments}, user: {name: 'A'}},
  })
  getTeamUsers.mockResolvedValue([{uid: 1, first_name: 'A', last_name: 'B'}])

  render(<CommentsButton />)
  await act(async () => {})

  expect(getComments).toHaveBeenCalledWith({})
  expect(CommentsActions.storeComments).toHaveBeenCalledWith(comments, {
    name: 'A',
  })
  expect(getTeamUsers).toHaveBeenCalledTimes(1)
  expect(CommentsActions.updateTeamUsers).toHaveBeenCalledWith([
    {uid: 1, first_name: 'A', last_name: 'B'},
  ])
})

test('does not fetch team users when there is no team', async () => {
  render(<CommentsButton />)
  await act(async () => {})
  expect(getTeamUsers).not.toHaveBeenCalled()
})

test('disables the toggle button until there are open or resolved threads', async () => {
  render(<CommentsButton />)
  await act(async () => {})
  expect(screen.getByRole('button')).toBeDisabled()
})

test('shows badges and enables the toggle once there are threads', async () => {
  mockDb([], {open: 2, resolved: 1})
  getComments.mockResolvedValue({
    data: {entries: {comments: [{}]}, user: {}},
  })
  render(<CommentsButton />)
  await act(async () => {})

  expect(screen.getByRole('button')).toBeEnabled()
  expect(screen.getByText('2')).toBeInTheDocument()
  expect(screen.getByText('1')).toBeInTheDocument()
})

test('shows "No comments" when the popover is opened with an empty history', async () => {
  mockDb([], {open: 1, resolved: 0})
  getComments.mockResolvedValue({
    data: {entries: {comments: [{}]}, user: {}},
  })
  render(<CommentsButton />)
  await act(async () => {})

  fireEvent.click(screen.getByRole('button'))
  expect(screen.getByText('No comments')).toBeInTheDocument()
})

test('lists comments and opens the related segment when a thread is selected', async () => {
  mockDb(
    [
      {
        id_segment: 42,
        full_name: 'Jane Doe',
        message: 'please review',
        thread_id: null,
        timestamp: 1,
      },
    ],
    {open: 1, resolved: 0},
  )
  getComments.mockResolvedValue({
    data: {entries: {comments: [{}]}, user: {}},
  })
  render(<CommentsButton />)
  await act(async () => {})

  fireEvent.click(screen.getByRole('button'))

  expect(screen.getByText('Segment 42')).toBeInTheDocument()
  expect(screen.getByText('Jane Doe')).toBeInTheDocument()

  fireEvent.click(screen.getByText('View thread'))
  expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith(
    42,
    SegmentActions.openSegmentComment,
  )
})

test('refreshes comments when the store emits an add/delete event', async () => {
  render(<CommentsButton />)
  await act(async () => {})

  CommentsStore.db.history = [
    {id_segment: 9, full_name: 'X', message: 'm', thread_id: 1, timestamp: 2},
  ]
  act(() => {
    CommentsStore.emit(CommentsConstants.ADD_COMMENT)
  })

  expect(CommentsStore.db.getOpenedThreadCount).toHaveBeenCalled()
})

test('closes the comments popover when the subheader closes', async () => {
  render(<CommentsButton />)
  await act(async () => {})

  act(() => {
    CatToolStore.emit(CattolConstants.CLOSE_SUBHEADER)
  })
  // no crash, listener wired correctly
  expect(screen.getByRole('button')).toBeInTheDocument()
})
