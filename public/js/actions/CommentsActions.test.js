jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))
jest.mock('../constants/CommentsConstants', () => ({
  STORE_COMMENTS: 'STORE_COMMENTS',
  ADD_COMMENT: 'ADD_COMMENT',
  DELETE_COMMENT: 'DELETE_COMMENT',
  SET_TEAM_USERS: 'SET_TEAM_USERS',
  SET_FOCUS: 'SET_FOCUS',
  OPEN_MENU: 'OPEN_MENU',
  SAVE_DRAFT: 'SAVE_DRAFT',
}))
jest.mock('../api/deleteComment/deleteComment', () => ({
  deleteComment: jest.fn(),
}))
jest.mock('../api/submitComment', () => ({
  submitComment: jest.fn(),
}))
jest.mock('../stores/UserStore', () => ({
  getUserName: jest.fn(() => 'user1'),
}))
jest.mock('../api/markAsResolvedThread', () => ({
  markAsResolvedThread: jest.fn(),
}))

import CommentsActions from './CommentsActions'
import AppDispatcher from '../stores/AppDispatcher'
import {deleteComment as deleteCommentApi} from '../api/deleteComment/deleteComment'
import {submitComment as submitCommentApi} from '../api/submitComment'
import {markAsResolvedThread} from '../api/markAsResolvedThread'

describe('CommentsActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    global.config = {...global.config, revisionNumber: 1}
  })

  test('storeComments dispatches STORE_COMMENTS', () => {
    CommentsActions.storeComments(['c1'], {id: 1})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'STORE_COMMENTS',
      comments: ['c1'],
      user: {id: 1},
    })
  })

  test('sendComment dispatches ADD_COMMENT on success and returns response', async () => {
    const resp = {data: {entries: {comments: [{id: 1}]}}}
    submitCommentApi.mockResolvedValueOnce(resp)

    const result = await CommentsActions.sendComment('hello', false, 5)

    expect(submitCommentApi).toHaveBeenCalledWith({
      idSegment: 5,
      username: 'user1',
      sourcePage: 2,
      message: 'hello',
      isAnonymous: false,
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_COMMENT',
      comment: {id: 1},
      sid: 5,
    })
    expect(result).toBe(resp)
  })

  test('sendComment swallows errors', async () => {
    submitCommentApi.mockRejectedValueOnce(new Error('fail'))

    await expect(
      CommentsActions.sendComment('hello', true, 5),
    ).resolves.toBeUndefined()
  })

  test('resolveThread dispatches ADD_COMMENT on success', async () => {
    const resp = {data: {entries: {comments: [{id: 2}]}}}
    markAsResolvedThread.mockResolvedValueOnce(resp)

    CommentsActions.resolveThread(7)
    await Promise.resolve()
    await Promise.resolve()

    expect(markAsResolvedThread).toHaveBeenCalledWith({
      idSegment: 7,
      isAnonymous: false,
      username: 'user1',
      sourcePage: 2,
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_COMMENT',
      comment: {id: 2},
      sid: 7,
    })
  })

  test('resolveThread swallows errors', async () => {
    markAsResolvedThread.mockRejectedValueOnce(new Error('fail'))

    expect(() => CommentsActions.resolveThread(7)).not.toThrow()
    await Promise.resolve()
    await Promise.resolve()
  })

  test('updateCommentsFromSse dispatches DELETE_COMMENT for delete message', () => {
    CommentsActions.updateCommentsFromSse({
      id_segment: 3,
      message_type: '2',
      id: 9,
    })

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'DELETE_COMMENT',
      sid: 3,
      idComment: 9,
    })
  })

  test('updateCommentsFromSse dispatches ADD_COMMENT for non-delete message', () => {
    const data = {id_segment: 3, message_type: '1', id: 9}
    CommentsActions.updateCommentsFromSse(data)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_COMMENT',
      comment: data,
      sid: 3,
    })
  })

  test('updateTeamUsers dispatches SET_TEAM_USERS', () => {
    CommentsActions.updateTeamUsers(['u1'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_TEAM_USERS',
      users: ['u1'],
    })
  })

  test('setFocusOnCurrentInput dispatches SET_FOCUS', () => {
    CommentsActions.setFocusOnCurrentInput()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SET_FOCUS',
    })
  })

  test('deleteComment dispatches DELETE_COMMENT', async () => {
    deleteCommentApi.mockResolvedValueOnce({data: [{id: 4}]})

    await CommentsActions.deleteComment(4, 8)

    expect(deleteCommentApi).toHaveBeenCalledWith({idComment: 4, idSegment: 8})
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'DELETE_COMMENT',
      sid: 8,
      idComment: 4,
    })
  })

  test('openCommentsMenu dispatches OPEN_MENU', () => {
    CommentsActions.openCommentsMenu()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_MENU',
    })
  })

  test('saveDraftComment dispatches SAVE_DRAFT', () => {
    CommentsActions.saveDraftComment(1, 'draft text')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SAVE_DRAFT',
      sid: 1,
      comment: 'draft text',
    })
  })
})
