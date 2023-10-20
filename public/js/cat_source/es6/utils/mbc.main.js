import {find} from 'lodash'

import CommentsActions from '../actions/CommentsActions'
import SegmentActions from '../actions/SegmentActions'
import CommentsStore from '../stores/CommentsStore'
import TextUtils from './textUtils'
import {getTeamUsers as getTeamUsersApi} from '../api/getTeamUsers'
import {submitComment as submitCommentApi} from '../api/submitComment'
import {getComments} from '../api/getComments'
import {markAsResolvedThread} from '../api/markAsResolvedThread'
import TeamsStore from '../stores/TeamsStore'

const MBC = {
  enabled: function () {
    return config.comments_enabled && !!window.EventSource
  },
}

MBC.init = function () {
  return (function ($, config, window, MBC) {
    MBC.const = {
      get commentAction() {
        return 'comment'
      },
    }

    MBC.localStorageCommentsClosed =
      'commentsPanelClosed-' + config.id_job + config.password

    const getUsername = () => {
      const userInfo = TeamsStore.getUser()
      return userInfo
        ? `${userInfo.user.first_name} ${userInfo.user.last_name}`
        : 'Anonymous'
    }

    const getSourcePage = () => {
      return config.revisionNumber ? config.revisionNumber + 1 : 1
    }

    var openSegmentCommentNoScroll = function (idSegment) {
      SegmentActions.openSegmentComment(idSegment)
      SegmentActions.scrollToSegment(idSegment)

      // $( 'article' ).removeClass('comment-opened-0').removeClass('comment-opened-1').removeClass('comment-opened-2').removeClass('comment-opened-empty-0');
      localStorage.setItem(MBC.localStorageCommentsClosed, false)
    }

    var parseCommentHtmlBeforeSend = function (text) {
      var elem = $('<div></div>').html(text)
      elem.find('.atwho-inserted').each(function () {
        var id = $(this).find('.tagging-item').data('id')
        $(this).html('{@' + id + '@}')
      })
      elem.find('.tagging-item').remove()
      return elem.text()
    }

    var submitComment = function (text, sid) {
      text = parseCommentHtmlBeforeSend(text)

      return submitCommentApi({
        idSegment: sid,
        username: getUsername(),
        sourcePage: getSourcePage(),
        message: text,
      })
    }

    var resolveThread = function (sid) {
      return markAsResolvedThread({
        idSegment: sid,
        username: getUsername(),
        sourcePage: getSourcePage(),
      }).then((resp) => {
        $(document).trigger('mbc:comment:new', resp.data.entries[0])
        return resp
      })
    }

    var checkOpenSegmentComment = function (id_segment) {
      if (
        CommentsStore.db.getCommentsCountBySegment &&
        UI.currentSegmentId === id_segment
      ) {
        var comments_obj =
          CommentsStore.db.getCommentsCountBySegment(id_segment)
        var panelClosed =
          localStorage.getItem(MBC.localStorageCommentsClosed) === 'true'
        if (comments_obj.active > 0 && !panelClosed) {
          openSegmentCommentNoScroll(id_segment)
        }
      }
    }

    // Interfaces
    $.extend(MBC, {
      submitComment: submitComment,
      resolveThread: resolveThread,
    })

    $(window).on('segmentOpened', function (e, data) {
      // var fn = function () {
      //   if (MBC.wasAskedByCommentHash(data.segmentId)) {
      //     openSegmentComment($(UI.getSegmentById(data.segmentId)))
      //   }
      checkOpenSegmentComment(data.segmentId)
      // }

      // if (MBC.commentsLoaded) {
      //   fn()
      // } else {
      //   setTimeout(fn, 1000)
      // }
    })
  })(jQuery, config, window, MBC)
}

// document.addEventListener('DOMContentLoaded', function (event) {
if (MBC.enabled()) {
  MBC.init()
}
// })

export default MBC
