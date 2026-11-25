import AppDispatcher from './AppDispatcher'
import CommentsStore from './CommentsStore'
import CommentsConstants from '../constants/CommentsConstants'

describe('CommentsStore', () => {
  beforeEach(() => {
    CommentsStore.db.segments = {}
    CommentsStore.db.history = []
    CommentsStore.db.history_count = 0
    CommentsStore.draftComments = {}
    CommentsStore.users = undefined
    CommentsStore.user = undefined
    jest.clearAllMocks()
  })

  test('saveDraftComment/getDraftComment stores and returns a draft', () => {
    CommentsStore.saveDraftComment(1, 'hello')

    expect(CommentsStore.getDraftComment(1)).toBe('hello')
  })

  test('setUsers/getTeamUsers prepends a team user to the list', () => {
    CommentsStore.setUsers([{uid: 1, first_name: 'John'}])

    expect(CommentsStore.getTeamUsers()).toEqual([
      {uid: 'team', first_name: 'Team', last_name: ''},
      {uid: 1, first_name: 'John'},
    ])
  })

  test('getUser returns the stored user', () => {
    CommentsStore.user = {uid: 1}

    expect(CommentsStore.getUser()).toEqual({uid: 1})
  })

  test('db.pushSegment creates a new segment entry', () => {
    CommentsStore.db.pushSegment({
      id: 1,
      id_segment: 10,
      message_type: 1,
    })

    expect(CommentsStore.db.segments[10]).toHaveLength(1)
  })

  test('db.pushSegment appends to an existing segment without duplicating ids', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})
    CommentsStore.db.pushSegment({id: 2, id_segment: 10, message_type: 1})
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})

    expect(CommentsStore.db.segments[10]).toHaveLength(2)
  })

  test('db.pushSegment sets thread_id on resolve messages missing one', () => {
    CommentsStore.db.pushSegment({
      id: 1,
      id_segment: 10,
      message_type: 1,
      thread_id: null,
    })
    CommentsStore.db.pushSegment({
      id: 2,
      id_segment: 10,
      message_type: 2,
      thread_id: 99,
    })

    expect(CommentsStore.db.segments[10][0].thread_id).toBe(99)
  })

  test('db.deleteSegment removes the matching comment', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})
    CommentsStore.db.pushSegment({id: 2, id_segment: 10, message_type: 1})

    CommentsStore.db.deleteSegment(1, 10)

    expect(CommentsStore.db.segments[10]).toHaveLength(1)
    expect(CommentsStore.db.segments[10][0].id).toBe(2)
  })

  test('getCommentsBySegment returns [] when no comments exist', () => {
    expect(CommentsStore.getCommentsBySegment(999)).toEqual([])
  })

  test('getCommentsBySegment returns the stored comments', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})

    expect(CommentsStore.getCommentsBySegment(10)).toHaveLength(1)
  })

  test('getCommentsCountBySegment counts active and resets on resolve', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})
    CommentsStore.db.pushSegment({id: 2, id_segment: 10, message_type: 2})

    expect(CommentsStore.getCommentsCountBySegment(10)).toEqual({
      active: 0,
      total: 2,
    })
  })

  test('getOpenedThreadCount counts segments whose last message is a comment', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})

    expect(CommentsStore.db.getOpenedThreadCount()).toBe(1)
  })

  test('getResolvedThreadCount counts segments whose last message is a resolve', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 2})

    expect(CommentsStore.db.getResolvedThreadCount()).toBe(1)
  })

  test('STORE_COMMENTS action resets, stores comments, sets user and emits change', () => {
    const emitSpy = jest.spyOn(CommentsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CommentsConstants.STORE_COMMENTS,
      comments: [{id: 1, id_segment: 10, message_type: 1}],
      user: {uid: 1},
    })

    expect(CommentsStore.db.segments[10]).toHaveLength(1)
    expect(CommentsStore.user).toEqual({uid: 1})
    expect(emitSpy).toHaveBeenCalledWith(CommentsConstants.STORE_COMMENTS)
  })

  test('ADD_COMMENT action pushes the comment and emits the segment id', () => {
    const emitSpy = jest.spyOn(CommentsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CommentsConstants.ADD_COMMENT,
      comment: {id: 1, id_segment: 10, message_type: 1},
      sid: 10,
    })

    expect(CommentsStore.db.segments[10]).toHaveLength(1)
    expect(emitSpy).toHaveBeenCalledWith(CommentsConstants.ADD_COMMENT, 10)
  })

  test('SET_TEAM_USERS action stores the users and emits change', () => {
    const emitSpy = jest.spyOn(CommentsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CommentsConstants.SET_TEAM_USERS,
      users: [{uid: 1, first_name: 'John'}],
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CommentsConstants.SET_TEAM_USERS,
      CommentsStore.users,
    )
  })

  test('DELETE_COMMENT action deletes the comment and emits the segment id', () => {
    CommentsStore.db.pushSegment({id: 1, id_segment: 10, message_type: 1})
    const emitSpy = jest.spyOn(CommentsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CommentsConstants.DELETE_COMMENT,
      idComment: 1,
      sid: 10,
    })

    expect(CommentsStore.db.segments[10]).toHaveLength(0)
    expect(emitSpy).toHaveBeenCalledWith(CommentsConstants.DELETE_COMMENT, 10)
  })

  test('SAVE_DRAFT action saves the draft without emitting change', () => {
    const emitSpy = jest.spyOn(CommentsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CommentsConstants.SAVE_DRAFT,
      sid: 10,
      comment: 'draft text',
    })

    expect(CommentsStore.getDraftComment(10)).toBe('draft text')
    expect(emitSpy).not.toHaveBeenCalled()
  })

  test('unhandled action types fall through to the default emit', () => {
    const emitSpy = jest.spyOn(CommentsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CommentsConstants.SET_FOCUS,
      data: {sid: 10},
    })

    expect(emitSpy).toHaveBeenCalledWith(CommentsConstants.SET_FOCUS, {
      sid: 10,
    })
  })
})
