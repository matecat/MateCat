import AppDispatcher from '../stores/AppDispatcher'
import CommentsConstants from '../constants/CommentsConstants'
import {deleteComment} from '../api/deleteComment/deleteComment'
import {submitComment as submitCommentApi} from '../api/submitComment'
import TeamsStore from '../stores/TeamsStore'
import {markAsResolvedThread} from '../api/markAsResolvedThread'

const CommentsActions = {
  storeComments: function (comments, user) {
    AppDispatcher.dispatch({
      actionType: CommentsConstants.STORE_COMMENTS,
      comments: comments,
      user: user,
    })
  },
  sendComment: function (text, sid) {
    return submitCommentApi({
      idSegment: sid,
      username: TeamsStore.getUserName(),
      sourcePage: config.revisionNumber ? config.revisionNumber + 1 : 1,
      message: text,
    }).then((resp) => {
      AppDispatcher.dispatch({
        actionType: CommentsConstants.ADD_COMMENT,
        comment: resp.data.entries[0],
        sid: sid,
      })
      return resp
    })
  },
  resolveThread: function (sid) {
    markAsResolvedThread({
      idSegment: sid,
      username: TeamsStore.getUserName(),
      sourcePage: config.revisionNumber ? config.revisionNumber + 1 : 1,
    })
      .then((resp) => {
        AppDispatcher.dispatch({
          actionType: CommentsConstants.ADD_COMMENT,
          comment: resp.data.entries[0],
          sid: sid,
        })
      })
      .catch(() => {
        // showGenericWarning();
      })
  },
  updateCommentsFromSse: function (data) {
    const {id_segment: sid} = data
    const isDeleteAction = data.message_type === '2'
    if (isDeleteAction) {
      AppDispatcher.dispatch({
        actionType: CommentsConstants.DELETE_COMMENT,
        sid,
        idComment: data.id,
      })
    } else {
      AppDispatcher.dispatch({
        actionType: CommentsConstants.ADD_COMMENT,
        comment: data,
        sid,
      })
    }
  },
  updateTeamUsers: function (users) {
    AppDispatcher.dispatch({
      actionType: CommentsConstants.SET_TEAM_USERS,
      users: users,
    })
  },
  setFocusOnCurrentInput: function () {
    AppDispatcher.dispatch({
      actionType: CommentsConstants.SET_FOCUS,
    })
  },
  deleteComment: function (idComment, sid) {
    deleteComment({idComment, idSegment: sid}).then(({data}) => {
      AppDispatcher.dispatch({
        actionType: CommentsConstants.DELETE_COMMENT,
        sid,
        idComment: data[0].id,
      })
    })
  },
  openCommentsMenu: () => {
    AppDispatcher.dispatch({
      actionType: CommentsConstants.OPEN_MENU,
    })
  },
  saveDraftComment: (sid, comment) => {
    AppDispatcher.dispatch({
      actionType: CommentsConstants.SAVE_DRAFT,
      sid,
      comment,
    })
  },
}

export default CommentsActions
