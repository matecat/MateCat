/**
 * React Component for the warnings.

 */
import React from 'react'
import _ from 'lodash'

import CommentsStore from '../../stores/CommentsStore'
import CommentsActions from '../../actions/CommentsActions'
import CommentsConstants from '../../constants/CommentsConstants'
import SegmentActions from '../../actions/SegmentActions'
import MBC from '../../utils/mbc.main'
import {SegmentContext} from './SegmentContext'

class SegmentCommentsContainer extends React.Component {
  static contextType = SegmentContext

  constructor(props, context) {
    super(props)
    this.state = {
      comments: CommentsStore.getCommentsBySegment(
        context.segment.original_sid,
      ),
      user: CommentsStore.getUser(),
      teamUsers: CommentsStore.getTeamUsers(),
      sendCommentError: false,
    }
    this.types = {sticky: 3, resolve: 2, comment: 1}
    this.updateComments = this.updateComments.bind(this)
    this.setFocusOnInput = this.setFocusOnInput.bind(this)
    this.setTeamUsers = this.setTeamUsers.bind(this)
  }

  closeComments(e) {
    e.preventDefault()
    e.stopPropagation()
    SegmentActions.closeSegmentComment(this.context.segment.sid)
    localStorage.setItem(MBC.localStorageCommentsClosed, true)
  }

  sendComment() {
    let text = $(this.commentInput).html()
    if (this.commentInput.textContent.trim().length > 0) {
      CommentsActions.sendComment(text, this.context.segment.original_sid)
        .catch(() => {
          this.setState({sendCommentError: true})
        })
        .then(() => {
          this.setState({sendCommentError: false})
          setTimeout(() => {
            if (this.commentInput) {
              this.commentInput.textContent = ''
            }
          })
        })
    }
  }

  deleteComment = () => {
    const {comments} = this.state
    const lastCommentId = comments[comments.length - 1].id
    CommentsActions.deleteComment(
      lastCommentId,
      this.context.segment.original_sid,
    )
  }

  resolveThread() {
    CommentsActions.resolveThread(this.context.segment.original_sid)
  }

  updateComments(sid) {
    if (_.isUndefined(sid) || sid === this.context.segment.original_sid) {
      const comments = CommentsStore.getCommentsBySegment(
        this.context.segment.original_sid,
      )
      const user = CommentsStore.getUser()
      this.setState({
        comments: comments,
        user: user,
      })
    }
  }

  setTeamUsers(users) {
    this.setState({
      teamUsers: users,
    })
  }

  getComments() {
    let htmlComments, htmlInsert, resolveButton
    const nl2br = function (str, is_xhtml) {
      var breakTag =
        is_xhtml || typeof is_xhtml === 'undefined' ? '<br />' : '<br>'
      return (str + '').replace(
        /([^>\r\n]?)(\r\n|\n\r|\r|\n)/g,
        '$1' + breakTag + '$2',
      )
    }

    var findUser = (id) => {
      return _.find(this.state.teamUsers, function (item) {
        return item.uid === id
      })
    }

    const parseCommentHtml = function (text) {
      var regExp = /{@([0-9]+|team)@}/gm
      if (regExp.test(text)) {
        text = text.replace(regExp, function (match, id) {
          id = id === 'team' ? id : parseInt(id)
          var user = findUser(id)
          if (user) {
            var html =
              '<span contenteditable="false" class="tagging-item" data-id="' +
              id +
              '">' +
              user.first_name +
              ' ' +
              user.last_name +
              '</span>'
            return match.replace(match, html)
          }
          return match
        })
      }

      return text
    }
    if (this.state.comments.length > 0) {
      let thread_wrap = [],
        thread_id = 0,
        count = 0,
        commentsHtml = [],
        threadClass
      let comments = this.state.comments.slice()
      comments.forEach((comment, i) => {
        let html = []
        if (comment.thread_id !== thread_id) {
          // start a new thread
          if (thread_wrap.length > 0) {
            commentsHtml.push(
              <div
                key={'thread-' + i}
                className={'mbc-thread-wrap mbc-clearfix ' + threadClass}
                data-count={count}
              >
                {thread_wrap}
              </div>,
            )
            count = 0
          }
          thread_wrap = []
        }
        if (Number(comment.message_type) === this.types.comment) {
          count++
        }
        if (comment.thread_id == null) {
          threadClass = 'mbc-thread-wrap-active'
        } else {
          threadClass = 'mbc-thread-wrap-resolved'
        }

        if (Number(comment.message_type) === this.types.resolve) {
          thread_wrap.push(
            <div className="mbc-resolved-comment" key={'comment-' + i}>
              <span className="mbc-comment-resolved-label">
                <span className="mbc-comment-username mbc-comment-resolvedby">
                  {`${comment.full_name} `}
                </span>
                <span className="">marked as resolved</span>
              </span>
            </div>,
          )
        } else {
          let text = nl2br(comment.message)
          text = parseCommentHtml(text)
          thread_wrap.push(
            <div className="mbc-show-comment mbc-clearfix" key={'comment-' + i}>
              <span className="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate">
                {comment.full_name}
              </span>
              <span className="mbc-comment-label mbc-comment-email-label mbc-truncate">
                {comment.email}
              </span>
              <div className="mbc-comment-info-wrap mbc-clearfix">
                <span className="mbc-comment-info mbc-comment-time pull-left">
                  {comment.formatted_date}
                </span>
              </div>
              <p
                className="mbc-comment-body"
                dangerouslySetInnerHTML={{__html: text}}
              />
            </div>,
          )
        }

        thread_id = comment.thread_id
      })
      // Thread is not resolved
      if (
        !_.isUndefined(comments.length - 1) &&
        !comments[comments.length - 1].thread_id
      ) {
        resolveButton = (
          <a
            className="ui button mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn pull-right"
            onClick={() => this.resolveThread()}
          >
            Resolve
          </a>
        )
      }
      if (thread_wrap.length > 0) {
        const isAuthorOfLastComment =
          comments[comments.length - 1].email === config.userMail

        commentsHtml.push(
          <div
            key={'thread-' + 900}
            className={'mbc-thread-wrap mbc-clearfix ' + threadClass}
            data-count={count}
          >
            {thread_wrap}
            {resolveButton}
            {isAuthorOfLastComment && (
              <a
                className="ui button mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn mbc-comment-delete-btn pull-right"
                onClick={this.deleteComment}
              >
                Delete
              </a>
            )}
          </div>,
        )
      }

      htmlComments = commentsHtml
    }
    let loggedUser = !!this.state.user
    // Se utente anonimo aggiungere mbc-comment-anonymous-label a mbc-comment-username
    htmlInsert = (
      <div
        className="mbc-thread-wrap mbc-post-comment-wrap mbc-clearfix mbc-first-input"
        ref={(container) => (this.container = container)}
      >
        {/*<div className="mbc-new-message-notification">*/}
        {/*<span className="mbc-new-message-icon mbc-new-message-arrowdown">&#8595;</span>*/}
        {/*<a className="mbc-new-message-link"/>*/}
        {/*</div>*/}
        <div className="mbc-post-comment">
          <span className="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate mbc-comment-anonymous-label">
            {this.state.user ? this.state.user.full_name : 'Anonymous'}
          </span>
          {!loggedUser ? (
            <a
              className="mbc-comment-link-btn mbc-login-link"
              onClick={() => {
                $('#modal').trigger('openlogin')
              }}
            >
              Login to receive comments
            </a>
          ) : null}
          <div
            ref={(input) => (this.commentInput = input)}
            onKeyDown={(e) => this.onKeyDown(e)}
            className="mbc-comment-input mbc-comment-textarea"
            contentEditable={true}
            data-placeholder="Write a comment..."
          />
          <div>
            <a
              className="ui primary tiny button mbc-comment-btn mbc-comment-send-btn hide"
              onClick={() => this.sendComment()}
            >
              Comment
            </a>
          </div>
          {this.state.sendCommentError ? (
            <div className="mbc-ajax-message-wrap">
              <span className="mbc-warnings">
                Oops, something went wrong. Please try again later.
              </span>
            </div>
          ) : null}

          <div></div>
        </div>
      </div>
    )

    return (
      <div className="mbc-comment-balloon-outer">
        <div className="mbc-comment-balloon-inner">
          <div className="mbc-triangle mbc-open-view mbc-re-messages" />
          <a
            className="re-close-balloon shadow-1"
            onClick={(e) => this.closeComments(e)}
          >
            <i className="icon-cancel3 icon" />
          </a>
          <div className="mbc-comments-wrap" ref={(wrap) => (this.wrap = wrap)}>
            {htmlComments}
          </div>
          {htmlInsert}
        </div>
      </div>
    )
  }

  addTagging() {
    let teamUsers = this.state.teamUsers
    if (teamUsers && teamUsers.length > 0) {
      $('.mbc-comment-textarea').atwho({
        at: '@',
        displayTpl: '<li>${first_name} ${last_name}</li>',
        insertTpl:
          '<span contenteditable="false" class="tagging-item" data-id="${uid}">${first_name} ${last_name}</span>',
        data: teamUsers,
        searchKey: 'first_name',
        limit: teamUsers.length,
      })
    }
  }

  scrollToBottom() {
    const scrollHeight = this.wrap.scrollHeight
    const height = this.wrap.clientHeight
    const maxScrollTop = scrollHeight - height
    this.wrap.scrollTop = maxScrollTop > 0 ? maxScrollTop : 0
  }

  setFocusOnInput() {
    this.commentInput.focus()
  }

  onKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      this.sendComment()
    }
  }

  componentDidUpdate() {
    // const comments = CommentsStore.getCommentsBySegment(this.context.segment.sid);
    this.scrollToBottom()
  }

  componentDidMount() {
    this.updateComments(this.context.segment.sid)
    this.addTagging()
    CommentsStore.addListener(
      CommentsConstants.ADD_COMMENT,
      this.updateComments,
    )
    CommentsStore.addListener(
      CommentsConstants.DELETE_COMMENT,
      this.updateComments,
    )
    CommentsStore.addListener(
      CommentsConstants.STORE_COMMENTS,
      this.updateComments,
    )
    CommentsStore.addListener(CommentsConstants.SET_FOCUS, this.setFocusOnInput)
    CommentsStore.addListener(
      CommentsConstants.SET_TEAM_USERS,
      this.setTeamUsers,
    )
    this.scrollToBottom()
    this.commentInput.focus()
  }

  componentWillUnmount() {
    CommentsStore.removeListener(
      CommentsConstants.ADD_COMMENT,
      this.updateComments,
    )
    CommentsStore.removeListener(
      CommentsConstants.DELETE_COMMENT,
      this.updateComments,
    )
    CommentsStore.removeListener(
      CommentsConstants.STORE_COMMENTS,
      this.updateComments,
    )
    CommentsStore.removeListener(
      CommentsConstants.SET_FOCUS,
      this.setFocusOnInput,
    )
    CommentsStore.removeListener(
      CommentsConstants.SET_TEAM_USERS,
      this.setTeamUsers,
    )
  }

  render() {
    //if is not splitted or is the first of the splitted group
    if (
      (!this.context.segment.splitted ||
        this.context.segment.sid.split('-')[1] === '1') &&
      this.state.comments
    ) {
      if (this.context.segment.openComments) {
        return this.getComments()
      }
    } else {
      return null
    }
  }
}

export default SegmentCommentsContainer
