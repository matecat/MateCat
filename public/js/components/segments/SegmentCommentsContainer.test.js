import React from 'react'
import {render, act, fireEvent, screen, waitFor} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentCommentsContainer from './SegmentCommentsContainer'
import {SegmentContext} from './SegmentContext'
import CommentsStore from '../../stores/CommentsStore'
import CommentsActions from '../../actions/CommentsActions'
import SegmentActions from '../../actions/SegmentActions'
import UserStore from '../../stores/UserStore'
import commonUtils from '../../utils/commonUtils'

// The real MentionsInput owns the caret and the mention parsing, so the mock
// keeps the props it was last rendered with: that is how a test types into the
// composer, the same way the component's own user would.
const mockMentionsProps = {current: null}

jest.mock('react-mentions', () => ({
  MentionsInput: (props) => {
    mockMentionsProps.current = props
    const {inputRef, value, onKeyDown, placeholder, className} = props
    return (
      <textarea
        ref={inputRef}
        data-testid="comment-input"
        value={value}
        readOnly
        onKeyDown={onKeyDown}
        placeholder={placeholder}
        className={className}
      />
    )
  },
}))

jest.mock('../common/Mention', () => () => null)

jest.mock('../../stores/CommentsStore', () => ({
  getCommentsBySegment: jest.fn(() => []),
  getTeamUsers: jest.fn(() => []),
  getUser: jest.fn(() => null),
  getDraftComment: jest.fn(() => ''),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../actions/CommentsActions', () => ({
  saveDraftComment: jest.fn(),
  sendComment: jest.fn(() => Promise.resolve()),
  deleteComment: jest.fn(),
  resolveThread: jest.fn(),
}))

jest.mock('../../constants/CommentsConstants', () => ({
  ADD_COMMENT: 'ADD_COMMENT',
  DELETE_COMMENT: 'DELETE_COMMENT',
  STORE_COMMENTS: 'STORE_COMMENTS',
  SET_FOCUS: 'SET_FOCUS',
  SET_TEAM_USERS: 'SET_TEAM_USERS',
}))

jest.mock('../../actions/SegmentActions', () => ({
  closeSegmentComment: jest.fn(),
}))

jest.mock('../../stores/UserStore', () => ({
  getUser: jest.fn(() => ({
    user: {uid: 42, first_name: 'Jane', last_name: 'Doe'},
  })),
}))

jest.mock('../../utils/commonUtils', () => ({
  getFromStorage: jest.fn(() => 'false'),
  addInStorage: jest.fn(),
}))

const buildComment = (overrides = {}) => ({
  id: 1,
  thread_id: 1,
  message_type: '1',
  message: 'hello there',
  is_anonymous: 0,
  full_name: 'Jane Doe',
  uid: 42,
  source_page: 1,
  timestamp: 1700000000,
  ...overrides,
})

// Types into the composer the way the real MentionsInput would report it.
const typeComment = (value, mentions = []) =>
  act(() => {
    mockMentionsProps.current.onChange({}, value, value, mentions)
  })

const commentInput = () => screen.getByTestId('comment-input')

const postButton = () => screen.getByRole('button', {name: 'Comment'})

// Hands a store event to whichever listener the component registered for it.
const emitStoreEvent = (constant, ...args) =>
  act(() => {
    CommentsStore.addListener.mock.calls
      .filter(([event]) => event === constant)
      .forEach(([, listener]) => listener(...args))
  })

const renderContainer = (contextOverrides = {}) => {
  const contextValue = {
    segment: {
      sid: '1-1',
      original_sid: 1,
      splitted: false,
      openComments: true,
    },
    userInfo: {user: {uid: 42, first_name: 'Jane', last_name: 'Doe'}},
    ...contextOverrides,
  }
  const utils = render(
    <SegmentContext.Provider value={contextValue}>
      <SegmentCommentsContainer />
    </SegmentContext.Provider>,
  )
  return {...utils, contextValue}
}

describe('SegmentCommentsContainer', () => {
  beforeEach(() => {
    // emitStoreEvent reads these call lists, so they must only hold the
    // listeners registered by the component under test in this test.
    CommentsStore.addListener.mockClear()
    CommentsStore.removeListener.mockClear()
    window.config = {revisionNumber: 0}
    CommentsStore.getCommentsBySegment.mockReturnValue([])
    CommentsStore.getTeamUsers.mockReturnValue([])
    CommentsStore.getUser.mockReturnValue(null)
    CommentsStore.getDraftComment.mockReturnValue('')
    UserStore.getUser.mockReturnValue({
      user: {uid: 42, first_name: 'Jane', last_name: 'Doe'},
    })
    commonUtils.getFromStorage.mockReturnValue('false')
  })

  test('renders the comment composer with an empty thread', () => {
    const {container} = renderContainer()
    expect(
      container.querySelector('.comment-balloon-outer'),
    ).toBeInTheDocument()
    expect(
      container.querySelector('[data-testid="comment-input"]'),
    ).toBeInTheDocument()
  })

  test('renders a regular comment with its author name and body', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    const {container} = renderContainer()
    expect(container).toHaveTextContent(/Jane Doe/)
    expect(container).toHaveTextContent(/hello there/)
    expect(
      container.querySelector('.comment-thread-active'),
    ).toBeInTheDocument()
  })

  test('shows the translator/revisor label for a non-anonymous comment', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({source_page: 2}),
    ])
    const {container} = renderContainer()
    expect(container).toHaveTextContent(/\(revisor\)/)
  })

  test('hides the author label for an anonymous comment', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({is_anonymous: 1}),
    ])
    const {container} = renderContainer()
    expect(container.querySelector('.comment-username-label')).toHaveTextContent(
      'Jane Doe',
    )
  })

  test('renders a resolved marker for a resolve-type comment', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({message_type: '2', is_anonymous: 0}),
    ])
    const {container} = renderContainer()
    expect(
      container.querySelector('.comment-thread-resolved'),
    ).toBeInTheDocument()
    expect(container).toHaveTextContent(/marked as resolved/)
  })

  test('shows the resolve button when the last comment is not a resolve entry', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    const {container} = renderContainer()
    expect(container).toHaveTextContent(/Resolve/)
  })

  test('does not show the resolve button when the thread is already resolved', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({message_type: '2'}),
    ])
    const {container} = renderContainer()
    expect(container).not.toHaveTextContent(/Resolve/)
  })

  test('shows a delete button for the author of the last comment on the current pass', () => {
    window.config.revisionNumber = 0
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({uid: 42, source_page: 1}),
    ])
    const {container} = renderContainer()
    const deleteBtn = container.querySelector('.comment-item button')
    expect(deleteBtn).toBeInTheDocument()

    act(() => {
      deleteBtn.click()
    })

    expect(CommentsActions.deleteComment).toHaveBeenCalledWith(1, 1)
  })

  test('does not show a delete button for a comment from another user', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({uid: 999}),
    ])
    const {container} = renderContainer()
    expect(
      container.querySelector('.comment-item button'),
    ).not.toBeInTheDocument()
  })

  test('clicking resolve dispatches resolveThread', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    const {container} = renderContainer()
    const resolveButton = Array.from(container.querySelectorAll('button')).find(
      (btn) => btn.textContent.includes('Resolve'),
    )

    act(() => {
      resolveButton.click()
    })

    expect(CommentsActions.resolveThread).toHaveBeenCalledWith(1, false)
  })

  test('closeComments prevents default and dispatches closeSegmentComment', () => {
    const {container} = renderContainer()
    const closeBtn = container.querySelector('.comment-close-btn')
    expect(closeBtn).toBeInTheDocument()

    act(() => {
      closeBtn.click()
    })

    expect(SegmentActions.closeSegmentComment).toHaveBeenCalledWith('1-1')
  })

  test('posting a comment sends what was typed, for the segment being commented', async () => {
    renderContainer()

    typeComment('{@42@} hi')
    fireEvent.click(postButton())

    await waitFor(() =>
      expect(CommentsActions.sendComment).toHaveBeenCalledWith(
        '{@42@} hi',
        false,
        1,
      ),
    )
  })

  test('the post button stays disabled until something is typed', () => {
    renderContainer()

    expect(postButton()).toBeDisabled()
    expect(CommentsActions.sendComment).not.toHaveBeenCalled()
  })

  test('a send that fails tells the user it went wrong', async () => {
    CommentsActions.sendComment.mockReturnValueOnce(Promise.reject())
    const {container} = renderContainer()

    typeComment('{@42@} hi')
    fireEvent.click(postButton())

    await waitFor(() =>
      expect(container).toHaveTextContent('Oops, something went wrong'),
    )
  })

  test('a send that succeeds leaves no error message behind', async () => {
    const {container} = renderContainer()

    typeComment('{@42@} hi')
    fireEvent.click(postButton())

    await waitFor(() => expect(CommentsActions.sendComment).toHaveBeenCalled())
    expect(container).not.toHaveTextContent('Oops, something went wrong')
  })

  test('a mention typed into the composer is sent as a bare id', async () => {
    renderContainer()

    typeComment('hello {@1||John@}', [{id: 1, display: 'John'}])
    fireEvent.click(postButton())

    await waitFor(() =>
      expect(CommentsActions.sendComment).toHaveBeenCalledWith(
        'hello {@1@}',
        false,
        1,
      ),
    )
  })

  test('pressing Enter posts the comment', async () => {
    renderContainer()

    typeComment('{@42@} hi')
    fireEvent.keyDown(commentInput(), {key: 'Enter', shiftKey: false})

    await waitFor(() =>
      expect(CommentsActions.sendComment).toHaveBeenCalledWith(
        '{@42@} hi',
        false,
        1,
      ),
    )
  })

  test('Shift+Enter writes a newline instead of posting', () => {
    renderContainer()

    typeComment('{@42@} hi')
    fireEvent.keyDown(commentInput(), {key: 'Enter', shiftKey: true})

    expect(CommentsActions.sendComment).not.toHaveBeenCalled()
  })

  test('typing anything else schedules a draft save', () => {
    jest.useFakeTimers()
    renderContainer()

    fireEvent.keyDown(commentInput(), {key: 'a'})
    act(() => jest.advanceTimersByTime(600))

    expect(CommentsActions.saveDraftComment).toHaveBeenCalledWith(1, '')
    jest.useRealTimers()
  })

  test('a comment added to this segment appears in the thread', () => {
    const {container} = renderContainer()
    expect(container).not.toHaveTextContent('hello there')

    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    emitStoreEvent('ADD_COMMENT', '1')

    expect(container).toHaveTextContent('hello there')
  })

  // CommentsStore returns the array it stores and pushes into it, so the
  // reference never changes. Resolving a thread has no other state change to
  // ride on, so a component that trusts the reference never repaints it.
  test('a thread resolved in place still repaints', () => {
    const thread = [buildComment()]
    CommentsStore.getCommentsBySegment.mockReturnValue(thread)
    const {container} = renderContainer()
    expect(container).not.toHaveTextContent('marked as resolved')

    thread.push(buildComment({id: 2, thread_id: 1, message_type: '2'}))
    emitStoreEvent('ADD_COMMENT', '1')

    expect(container).toHaveTextContent('marked as resolved')
  })

  test('a comment added to another segment is ignored', () => {
    const {container} = renderContainer()

    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    emitStoreEvent('ADD_COMMENT', '999')

    expect(container).not.toHaveTextContent('hello there')
  })

  test('a mention shows the team member name once the team arrives', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({message: 'ping {@7@}'}),
    ])
    const {container} = renderContainer()
    expect(container).not.toHaveTextContent('Ada Lovelace')

    emitStoreEvent('SET_TEAM_USERS', [
      {uid: 7, first_name: 'Ada', last_name: 'Lovelace'},
    ])

    expect(container).toHaveTextContent('Ada Lovelace')
  })

  test('registers and unregisters CommentsStore listeners on mount/unmount', () => {
    const {unmount} = renderContainer()
    expect(CommentsStore.addListener).toHaveBeenCalledWith(
      'ADD_COMMENT',
      expect.any(Function),
    )
    expect(CommentsStore.addListener).toHaveBeenCalledWith(
      'SET_FOCUS',
      expect.any(Function),
    )
    unmount()
    expect(CommentsStore.removeListener).toHaveBeenCalledWith(
      'ADD_COMMENT',
      expect.any(Function),
    )
    expect(CommentsStore.removeListener).toHaveBeenCalledWith(
      'SET_FOCUS',
      expect.any(Function),
    )
  })

  test('picks up a draft comment on mount', () => {
    CommentsStore.getDraftComment.mockReturnValue('draft text')
    renderContainer()
    expect(commentInput()).toHaveValue('draft text')
  })

  test('renders anonymously with the reviewer label when posting anonymously in review mode', () => {
    commonUtils.getFromStorage.mockReturnValue('true')
    window.config = {isReview: true, revisionNumber: 2}
    const {container} = renderContainer()
    expect(container).toHaveTextContent(/2nd pass revisor/)
  })

  test('renders anonymously with the translator label outside review mode', () => {
    commonUtils.getFromStorage.mockReturnValue('true')
    window.config = {isReview: false, revisionNumber: 0}
    const {container} = renderContainer()
    expect(container).toHaveTextContent(/Translator/)
  })

  test('toggling the anonymous checkbox persists the preference', () => {
    const {container} = renderContainer()
    const checkbox = container.querySelector('input[type="checkbox"]')
    expect(checkbox).toBeInTheDocument()

    act(() => {
      checkbox.click()
    })

    expect(commonUtils.addInStorage).toHaveBeenCalledWith(
      'anonymous-comments42',
      true,
    )
  })
})
