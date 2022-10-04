import AppDispatcher from '../stores/AppDispatcher'
import CommentsConstants from '../constants/CommentsConstants'
import MBC from '../utils/mbc.main'
import {deleteComment} from '../api/deleteComment/deleteComment'

const CommentsActions = {
  storeComments: function (comments, user) {
    AppDispatcher.dispatch({
      actionType: CommentsConstants.STORE_COMMENTS,
      comments: comments,
      user: user,
    })
  },
  sendComment: function (text, sid) {
    return MBC.submitComment(text, sid).then((resp) => {
      AppDispatcher.dispatch({
        actionType: CommentsConstants.ADD_COMMENT,
        comment: resp.data.entries[0],
        sid: sid,
      })
      $(document).trigger('mbc:comment:saved', resp.data.entries[0])
      return resp
    })
  },
  resolveThread: function (sid) {
    MBC.resolveThread(sid)
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
    AppDispatcher.dispatch({
      actionType: CommentsConstants.ADD_COMMENT,
      comment: data,
      sid: data.id_segment,
    })
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
      console.log(data)
      AppDispatcher.dispatch({
        actionType: CommentsConstants.DELETE_COMMENT,
        sid: sid,
        idComment: data[0].id,
      })
    })
  },
}

export default CommentsActions
