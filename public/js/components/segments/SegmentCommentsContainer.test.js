import React from 'react'
import {render, act} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentCommentsContainer from './SegmentCommentsContainer'
import {SegmentContext} from './SegmentContext'
import CommentsStore from '../../stores/CommentsStore'
import CommentsActions from '../../actions/CommentsActions'
import SegmentActions from '../../actions/SegmentActions'
import UserStore from '../../stores/UserStore'
import commonUtils from '../../utils/commonUtils'

jest.mock('react-mentions', () => ({
  MentionsInput: ({
    inputRef,
    value,
    onKeyDown,
    placeholder,
    className,
  }) => (
    <textarea
      ref={inputRef}
      data-testid="comment-input"
      value={value}
      readOnly
      onKeyDown={onKeyDown}
      placeholder={placeholder}
      className={className}
    />
  ),
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
  getUser: jest.fn(() => ({user: {uid: 42, first_name: 'Jane', last_name: 'Doe'}})),
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

const renderContainer = (contextOverrides = {}) => {
  const ref = React.createRef()
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
      <SegmentCommentsContainer ref={ref} />
    </SegmentContext.Provider>,
  )
  return {...utils, ref, contextValue}
}

describe('SegmentCommentsContainer', () => {
  beforeEach(() => {
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
    expect(container.textContent).toContain('Jane Doe')
    expect(container.textContent).toContain('hello there')
    expect(container.querySelector('.comment-thread-active')).toBeInTheDocument()
  })

  test('shows the translator/revisor label for a non-anonymous comment', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({source_page: 2}),
    ])
    const {container} = renderContainer()
    expect(container.textContent).toContain('(revisor)')
  })

  test('hides the author label for an anonymous comment', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({is_anonymous: 1}),
    ])
    const {container} = renderContainer()
    expect(
      container.querySelector('.comment-username-label').textContent,
    ).toBe('Jane Doe')
  })

  test('renders a resolved marker for a resolve-type comment', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({message_type: '2', is_anonymous: 0}),
    ])
    const {container} = renderContainer()
    expect(container.querySelector('.comment-thread-resolved')).toBeInTheDocument()
    expect(container.textContent).toContain('marked as resolved')
  })

  test('shows the resolve button when the last comment is not a resolve entry', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    const {container} = renderContainer()
    expect(container.textContent).toContain('Resolve')
  })

  test('does not show the resolve button when the thread is already resolved', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({message_type: '2'}),
    ])
    const {container} = renderContainer()
    expect(container.textContent).not.toContain('Resolve')
  })

  test('shows a delete button for the author of the last comment on the current pass', () => {
    window.config.revisionNumber = 0
    CommentsStore.getCommentsBySegment.mockReturnValue([
      buildComment({uid: 42, source_page: 1}),
    ])
    const {container, ref} = renderContainer()
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
    expect(container.querySelector('.comment-item button')).not.toBeInTheDocument()
  })

  test('clicking resolve dispatches resolveThread', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    const {container} = renderContainer()
    const resolveButton = Array.from(
      container.querySelectorAll('button'),
    ).find((btn) => btn.textContent.includes('Resolve'))

    act(() => {
      resolveButton.click()
    })

    expect(CommentsActions.resolveThread).toHaveBeenCalledWith(1, false)
  })

  test('shows the send-comment error message when sendCommentError is set', () => {
    const {container, ref} = renderContainer()
    act(() => {
      ref.current.setState({sendCommentError: true})
    })
    expect(container.textContent).toContain(
      'Oops, something went wrong. Please try again later.',
    )
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

  test('sendComment dispatches when mentionsMarkup is present and clears the input on success', async () => {
    const {ref} = renderContainer()

    act(() => {
      ref.current.setState({mentionsMarkup: '{@42@} hi', mentionsInputValue: 'hi'})
    })
    await act(async () => {
      await ref.current.sendComment()
    })

    expect(CommentsActions.sendComment).toHaveBeenCalledWith(
      '{@42@} hi',
      false,
      1,
    )
  })

  test('sendComment is a no-op when there is no mentionsMarkup', async () => {
    const {ref} = renderContainer()

    await act(async () => {
      await ref.current.sendComment()
    })

    expect(CommentsActions.sendComment).not.toHaveBeenCalled()
  })

  test('sendComment recovers from a rejected send and clears the error afterwards', async () => {
    CommentsActions.sendComment.mockReturnValueOnce(Promise.reject())
    const {ref} = renderContainer()

    act(() => {
      ref.current.setState({mentionsMarkup: '{@42@} hi'})
    })
    await act(async () => {
      ref.current.sendComment()
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()
    })

    expect(ref.current.state.sendCommentError).toBe(false)
  })

  test('handleChangeMentionsInputValue converts mention placeholders and updates state', () => {
    const {ref} = renderContainer()

    act(() => {
      ref.current.handleChangeMentionsInputValue(
        {},
        'hello {@1||John@}',
        'hello John',
        [{id: 1, display: 'John'}],
      )
    })

    expect(ref.current.state.mentionsInputValue).toBe('hello {@1||John@}')
    expect(ref.current.state.mentionsMarkup).toBe('hello {@1@}')
  })

  test('onKeyDown sends the comment on Enter when there is input', () => {
    const {ref} = renderContainer()
    act(() => {
      ref.current.setState({mentionsMarkup: '{@42@} hi', mentionsInputValue: 'hi'})
    })
    const sendCommentSpy = jest.spyOn(ref.current, 'sendComment')
    const fakeEvent = {key: 'Enter', shiftKey: false, preventDefault: jest.fn()}

    act(() => {
      ref.current.onKeyDown(fakeEvent)
    })

    expect(fakeEvent.preventDefault).toHaveBeenCalled()
    expect(sendCommentSpy).toHaveBeenCalled()
  })

  test('onKeyDown debounces a draft save for non-Enter key presses', () => {
    jest.useFakeTimers()
    const {ref} = renderContainer()
    const fakeEvent = {key: 'a', shiftKey: false, preventDefault: jest.fn()}

    act(() => {
      ref.current.onKeyDown(fakeEvent)
      jest.advanceTimersByTime(600)
    })

    expect(CommentsActions.saveDraftComment).toHaveBeenCalledWith(1, '')
    jest.useRealTimers()
  })

  test('onKeyDown does nothing special for other keys', () => {
    const {ref} = renderContainer()
    const fakeEvent = {key: 'a', shiftKey: false, preventDefault: jest.fn()}

    act(() => {
      ref.current.onKeyDown(fakeEvent)
    })

    expect(fakeEvent.preventDefault).not.toHaveBeenCalled()
  })

  test('updateComments refreshes comments and user when the sid matches', () => {
    CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
    CommentsStore.getUser.mockReturnValue({uid: 1})
    const {ref} = renderContainer()

    act(() => {
      ref.current.updateComments('1')
    })

    expect(ref.current.state.comments).toEqual([buildComment()])
    expect(ref.current.state.user).toEqual({uid: 1})
  })

  test('updateComments ignores a non-matching sid', () => {
    const {ref} = renderContainer()
    const previousComments = ref.current.state.comments

    act(() => {
      CommentsStore.getCommentsBySegment.mockReturnValue([buildComment()])
      ref.current.updateComments('999')
    })

    expect(ref.current.state.comments).toBe(previousComments)
  })

  test('setTeamUsers replaces the teamUsers state', () => {
    const {ref} = renderContainer()

    act(() => {
      ref.current.setTeamUsers([{uid: 7, first_name: 'A', last_name: 'B'}])
    })

    expect(ref.current.state.teamUsers).toEqual([
      {uid: 7, first_name: 'A', last_name: 'B'},
    ])
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
    const {ref} = renderContainer()
    expect(ref.current.state.mentionsInputValue).toBe('draft text')
  })

  test('renders anonymously with the reviewer label when posting anonymously in review mode', () => {
    commonUtils.getFromStorage.mockReturnValue('true')
    window.config = {isReview: true, revisionNumber: 2}
    const {container} = renderContainer()
    expect(container.textContent).toContain('2nd pass revisor')
  })

  test('renders anonymously with the translator label outside review mode', () => {
    commonUtils.getFromStorage.mockReturnValue('true')
    window.config = {isReview: false, revisionNumber: 0}
    const {container} = renderContainer()
    expect(container.textContent).toContain('Translator')
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
