/**
 * React Component for the warnings.

 */
import React from 'react'
import {isUndefined} from 'lodash'
import {debounce} from 'lodash/function'
import CommentsStore from '../../stores/CommentsStore'
import CommentsActions from '../../actions/CommentsActions'
import CommentsConstants from '../../constants/CommentsConstants'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import {MentionsInput} from 'react-mentions'
import Mention from '../common/Mention'

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
      showTagging: false,
      mentionsInputValue: '',
    }
    this.types = {sticky: 3, resolve: 2, comment: 1}
    this.updateComments = this.updateComments.bind(this)
    this.setFocusOnInput = this.setFocusOnInput.bind(this)
    this.setTeamUsers = this.setTeamUsers.bind(this)
    this.saveDraft = debounce(() => {
      CommentsActions.saveDraftComment(
        this.context.segment.original_sid,
        this.state.mentionsInputValue,
      )
    }, 500)
  }

  closeComments(e) {
    e.preventDefault()
    e.stopPropagation()
    SegmentActions.closeSegmentComment(this.context.segment.sid)
  }

  sendComment() {
    const {mentionsMarkup} = this.state
    if (mentionsMarkup?.length > 0) {
      CommentsActions.sendComment(
        mentionsMarkup,
        this.context.segment.original_sid,
      )
        .catch(() => {
          this.setState({sendCommentError: true})
        })
        .then(() => {
          this.setState({sendCommentError: false})
          setTimeout(() => {
            if (this.commentInput) {
              this.setState({
                mentionsInputValue: '',
              })
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
    if (isUndefined(sid) || sid === this.context.segment.original_sid) {
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

  handleChangeMentionsInputValue = (
    event,
    newValue,
    newPlainTextValue,
    mentions,
  ) => {
    const mentionsMarkup = mentions.reduce(
      (acc, cur) =>
        acc.replace(`{@${cur.id}||${cur.display}@}`, `{@${cur.id}@}`),
      newValue,
    )

    this.setState({
      mentionsInputValue: newValue,
      mentionsMarkup,
    })
  }

  getComments() {
    let htmlComments, htmlInsert, resolveButton, deleteButton
    const nl2br = function (str, is_xhtml) {
      var breakTag =
        is_xhtml || typeof is_xhtml === 'undefined' ? '<br />' : '<br>'
      return (str + '').replace(
        /([^>\r\n]?)(\r\n|\n\r|\r|\n)/g,
        '$1' + breakTag + '$2',
      )
    }

    const findUser = (id) => {
      if (this.state.teamUsers) {
        return this.state.teamUsers.find((item) => {
          return item.uid === id
        })
      }
      return undefined
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
          const formattedDate = new Date(
            comment.timestamp ? comment.timestamp * 1000 : comment.create_date,
          )
            .toString()
            .split('(')[0]
            .trim()

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
                  {formattedDate}
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
        !isUndefined(comments.length - 1) &&
        !(
          parseInt(comments[comments.length - 1].message_type) ===
          this.types.resolve
        )
      ) {
        resolveButton = (
          <a
            className="ui button mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn pull-right"
            onClick={() => this.resolveThread()}
          >
            Resolve
          </a>
        )
        const isAuthorOfLastComment =
          comments[comments.length - 1].email === config.userMail
        deleteButton = isAuthorOfLastComment ? (
          <a
            className="ui button mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn mbc-comment-delete-btn pull-right"
            onClick={this.deleteComment}
          >
            Delete
          </a>
        ) : (
          ''
        )
      }
      if (thread_wrap.length > 0) {
        commentsHtml.push(
          <div
            key={'thread-' + 900}
            className={'mbc-thread-wrap mbc-clearfix ' + threadClass}
            data-count={count}
          >
            {thread_wrap}
            {resolveButton}
            {deleteButton}
          </div>,
        )
      }

      htmlComments = commentsHtml
    }

    const userMentionData =
      this.state.teamUsers?.map((user) => ({
        id: user.uid,
        display: ` ${user.first_name} ${user.last_name} `, // eslint-disable-line
      })) ?? []

    // workaround - textarea fit to content
    if (this.commentInput) {
      setTimeout(() => {
        if (this.commentInput)
          this.commentInput.style.height = `${this.commentInput.parentNode.clientHeight}px`
      }, 200)
    }

    let loggedUser = !!this.state.user
    // Se utente anonimo aggiungere mbc-comment-anonymous-label a mbc-comment-username
    htmlInsert = (
      <div
        className="mbc-thread-wrap mbc-post-comment-wrap mbc-clearfix mbc-first-input"
        ref={(container) => (this.container = container)}
      >
        <div className="mbc-post-comment">
          <span className="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate mbc-comment-anonymous-label">
            {this.state.user ? this.state.user.full_name : 'Anonymous'}
          </span>
          {!loggedUser ? (
            <a
              className="mbc-comment-link-btn mbc-login-link"
              onClick={() => {
                APP.openLoginModal()
              }}
            >
              Login to receive comments
            </a>
          ) : null}
          <MentionsInput
            inputRef={(input) => (this.commentInput = input)}
            value={this.state.mentionsInputValue}
            onKeyDown={(e) => this.onKeyDown(e)}
            onChange={this.handleChangeMentionsInputValue}
            placeholder="Write a comment..."
            className="mbc-comment-input mbc-comment-textarea"
            suggestionsPortalHost={document.body}
          >
            <Mention
              type="user"
              trigger="@"
              data={userMentionData}
              className="tagging-item-textarea"
              markup="{@__id__||__display__@}"
              displayTransform={function (id, display) {
                return display || id
              }}
              onAdd={() => this.saveDraft()}
              onRemove={() => null}
              isLoading={false}
              appendSpaceOnAdd={false}
            />
          </MentionsInput>
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
    if (e.key === 'Enter' && !e.shiftKey && !this.state.showTagging) {
      e.preventDefault()
      this.sendComment()
    } else {
      this.saveDraft()
    }
  }

  componentDidUpdate() {
    // const comments = CommentsStore.getCommentsBySegment(this.context.segment.sid);
    this.scrollToBottom()
  }

  componentDidMount() {
    const draftText = CommentsStore.getDraftComment(this.context.segment.sid)
    if (draftText) {
      this.setState({mentionsInputValue: draftText})
    }

    this.updateComments(this.context.segment.sid)
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
