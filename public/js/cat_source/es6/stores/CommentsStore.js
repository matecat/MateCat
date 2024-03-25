/*
 * Analyze Store
 */

import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import assign from 'object-assign'
import CommentsConstants from '../constants/CommentsConstants'

EventEmitter.prototype.setMaxListeners(0)

let CommentsStore = assign({}, EventEmitter.prototype, {
  users: undefined,
  draftComments: {},
  db: {
    types: {sticky: 3, resolve: 2, comment: 1},
    segments: {},
    history: {},
    refreshHistory: function () {
      function includeInHistory(comment) {
        return (
          Number(comment.message_type) === CommentsStore.db.types.comment &&
          sids.indexOf(comment.id_segment) === -1
        )
      }

      this.history = []
      this.history_count = 0
      var comment
      var sids = []

      for (var i in this.segments) {
        if (isNaN(i)) {
          continue
        }

        var new_array = this.segments[i].slice() // quick clone
        new_array.reverse()

        for (var ii in new_array) {
          comment = new_array[ii]

          if (includeInHistory(comment)) {
            this.history_count++
            this.history.push(comment)
            sids.push(comment.id_segment)
          }
        }
      }

      this.history.sort(function (x, y) {
        return x.timestamp - y.timestamp
      })
    },

    resetSegments: function () {
      this.segments = {}
    },

    storeSegments: function (array) {
      for (var i = 0; i < array.length; i++) {
        this.pushSegment(array[i])
      }
    },

    pushSegment: function (data) {
      var s = Number(data.id_segment)

      if (typeof CommentsStore.db.segments[s] === 'undefined') {
        CommentsStore.db.segments[s] = [data]
      } else if (!CommentsStore.db.segments[s].find((e) => e.id === data.id)) {
        CommentsStore.db.segments[s].push(data)
      }
      if (Number(data.message_type) === this.types.resolve) {
        $(CommentsStore.db.segments[s]).each(function (i, x) {
          if (x.thread_id == null) {
            x.thread_id = data.thread_id
          }
        })
      }
      CommentsStore.db.refreshHistory()
      CommentsStore.saveDraftComment(data.id_segment, '')
    },
    deleteSegment: function (idComment, idSegment) {
      const segmentComments = CommentsStore.db.segments[idSegment]
      CommentsStore.db.segments[idSegment] = segmentComments.filter(
        ({id}) => id !== idComment,
      )
      CommentsStore.db.refreshHistory()
    },
    getCommentsBySegment: function (s) {
      var s = Number(s)

      if (typeof this.segments[s] === 'undefined') {
        return []
      } else {
        return this.segments[s]
      }
    },

    getCommentsCountBySegment: function (s) {
      var active = 0,
        total = 0

      $(this.getCommentsBySegment(s)).each(function (i, x) {
        if (Number(x.message_type) === CommentsStore.db.types.comment) {
          if (null == x.thread_id) active++
          total++
        }
      })
      return {active: active, total: total}
    },
    getOpenedThreadCount: function () {
      var count = 0

      for (var segmentID in CommentsStore.db.segments) {
        const el =
          CommentsStore.db.segments[segmentID][
            CommentsStore.db.segments[segmentID].length - 1
          ]
        if (el && el.message_type && parseInt(el.message_type) === 1) count++
      }
      return count
    },
    getResolvedThreadCount: function () {
      var count = 0

      for (var segmentID in CommentsStore.db.segments) {
        const el =
          CommentsStore.db.segments[segmentID][
            CommentsStore.db.segments[segmentID].length - 1
          ]
        if (el && el.message_type && parseInt(el.message_type) === 2) count++
      }
      return count
    },
  },
  saveDraftComment: (sid, comment) => {
    CommentsStore.draftComments[sid] = comment
  },
  getDraftComment: (sid) => {
    return CommentsStore.draftComments[sid]
  },
  setUsers: (users) => {
    const teamTemp = {
      uid: 'team',
      first_name: 'Team',
      last_name: '',
    }
    CommentsStore.users = [teamTemp, ...users]
  },
  getCommentsBySegment: function (sid) {
    return CommentsStore.db.getCommentsBySegment(sid)
  },
  getUser: function () {
    return CommentsStore.user
  },
  getCommentsCountBySegment: function (sid) {
    return CommentsStore.db.getCommentsCountBySegment(sid)
  },
  getTeamUsers: function () {
    return CommentsStore.users
  },
  emitChange: function (event, args) {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case CommentsConstants.STORE_COMMENTS:
      CommentsStore.db.resetSegments()
      CommentsStore.db.storeSegments(action.comments)
      CommentsStore.user = action.user
      CommentsStore.db.refreshHistory()
      CommentsStore.emitChange(action.actionType)
      break
    case CommentsConstants.ADD_COMMENT:
      CommentsStore.db.pushSegment(action.comment)
      CommentsStore.emitChange(action.actionType, action.sid)
      break
    case CommentsConstants.SET_TEAM_USERS:
      CommentsStore.setUsers(action.users)
      CommentsStore.emitChange(action.actionType, CommentsStore.users)
      break
    case CommentsConstants.DELETE_COMMENT:
      CommentsStore.db.deleteSegment(action.idComment, action.sid)
      CommentsStore.emitChange(action.actionType, action.sid)
      break
    case CommentsConstants.SAVE_DRAFT:
      CommentsStore.saveDraftComment(action.sid, action.comment)
      break
    default:
      CommentsStore.emitChange(action.actionType, action.data)
  }
})
export default CommentsStore
