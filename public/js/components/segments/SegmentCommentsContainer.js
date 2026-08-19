/**
 * React Component for the warnings.

 */
import React, {
  forwardRef,
  useContext,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react'
import {isUndefined} from 'lodash'
import {debounce} from 'lodash/function'
import CommentsStore from '../../stores/CommentsStore'
import CommentsActions from '../../actions/CommentsActions'
import CommentsConstants from '../../constants/CommentsConstants'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import {MentionsInput} from 'react-mentions'
import Mention from '../common/Mention'
import UserStore from '../../stores/UserStore'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import Trash from '../../../img/icons/Trash'
import Check from '../../../img/icons/Check'
import commonUtils from '../../utils/commonUtils'
import IconClose from '../../../img/icons/IconClose'

const COMMENT_TYPES = {sticky: 3, resolve: 2, comment: 1}

const SegmentCommentsContainer = forwardRef((props, ref) => {
  const context = useContext(SegmentContext)

  // Computed ONCE, matching the constructor's `this.localStorageKey` which is never
  // recomputed even if context changes later.
  const [localStorageKey] = useState(
    () => 'anonymous-comments' + context.userInfo.user.uid,
  )

  const [comments, setComments] = useState(() =>
    CommentsStore.getCommentsBySegment(context.segment.original_sid),
  )
  const [user, setUser] = useState(() => UserStore.getUser())
  const [teamUsers, setTeamUsersState] = useState(() =>
    CommentsStore.getTeamUsers(),
  )
  const [sendCommentError, setSendCommentError] = useState(false)
  const [showTagging] = useState(false)
  const [mentionsInputValue, setMentionsInputValue] = useState('')
  // Not part of the constructor's initial state object — only ever set via
  // handleChangeMentionsInputValue's setState, so it starts undefined.
  const [mentionsMarkup, setMentionsMarkup] = useState()
  const [anonymousComments, setAnonymousComments] = useState(
    () => commonUtils.getFromStorage(localStorageKey) === 'true',
  )

  const commentInputRef = useRef(null)
  const wrapRef = useRef(null)

  // Always holds the CURRENT context/state read live inside stable callbacks, mirroring
  // `this.context`/`this.state` never being a stale closure.
  const liveRef = useRef({segment: context.segment, mentionsInputValue})
  liveRef.current = {segment: context.segment, mentionsInputValue}

  // Created once via useRef so its identity never changes across renders — required so that
  // CommentsStore.addListener/removeListener (matched by reference) stay in sync, exactly like
  // the class's single this.updateComments.bind(this) in the constructor.
  const updateCommentsRef = useRef((sid) => {
    const {segment} = liveRef.current
    if (
      isUndefined(sid) ||
      parseInt(sid) === parseInt(segment.original_sid)
    ) {
      setComments(CommentsStore.getCommentsBySegment(segment.original_sid))
      setUser(CommentsStore.getUser())
    }
  })

  // Seeded once so the debounce() call itself only ever executes one time, preserving the
  // single persistent timer (recreating it every render would silently break debouncing).
  const saveDraftRef = useRef(
    debounce(() => {
      const {segment, mentionsInputValue} = liveRef.current
      CommentsActions.saveDraftComment(
        segment.original_sid,
        mentionsInputValue,
      )
    }, 500),
  )

  const setFocusOnInputRef = useRef(() => {
    commentInputRef.current.focus()
  })

  const setTeamUsersRef = useRef((users) => {
    setTeamUsersState(users)
  })

  // Bridges the imperative ref API back onto a stable object so that internal calls made
  // through it (e.g. onKeyDown -> sendComment) are visible to jest.spyOn, exactly like a class
  // instance's `this.sendComment()` performing a property lookup at call time.
  const instanceRef = useRef({})

  const closeComments = (e) => {
    e.preventDefault()
    e.stopPropagation()
    SegmentActions.closeSegmentComment(context.segment.sid)
  }

  const sendComment = () => {
    if (mentionsMarkup?.length > 0) {
      CommentsActions.sendComment(
        mentionsMarkup,
        anonymousComments,
        context.segment.original_sid,
      )
        .catch(() => {
          setSendCommentError(true)
        })
        .then(() => {
          setSendCommentError(false)
          setTimeout(() => {
            if (commentInputRef.current) {
              setMentionsInputValue('')
            }
          })
        })
    }
  }

  const deleteComment = () => {
    const lastCommentId = comments[comments.length - 1].id
    CommentsActions.deleteComment(lastCommentId, context.segment.original_sid)
  }

  const resolveThread = () => {
    CommentsActions.resolveThread(
      context.segment.original_sid,
      anonymousComments,
    )
  }

  const handleChangeMentionsInputValue = (
    event,
    newValue,
    newPlainTextValue,
    mentions,
  ) => {
    const newMentionsMarkup = mentions.reduce(
      (acc, cur) =>
        acc.replace(`{@${cur.id}||${cur.display}@}`, `{@${cur.id}@}`),
      newValue,
    )

    setMentionsInputValue(newValue)
    setMentionsMarkup(newMentionsMarkup)
  }

  const onKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !showTagging) {
      e.preventDefault()
      if (mentionsInputValue) instanceRef.current.sendComment()
    } else {
      saveDraftRef.current()
    }
  }

  const getComments = () => {
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
      if (teamUsers) {
        return teamUsers.find((item) => {
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
    if (comments.length > 0) {
      let thread_wrap = [],
        thread_id = 0,
        count = 0,
        commentsHtml = [],
        threadClass
      let commentsCopy = comments.slice()
      commentsCopy.forEach((comment, i) => {
        if (comment.thread_id !== thread_id) {
          // start a new thread
          if (thread_wrap.length > 0) {
            commentsHtml.push(
              <div
                key={'thread-' + i}
                className={'comment-thread comment-clearfix ' + threadClass}
                data-count={count}
              >
                {thread_wrap}
              </div>,
            )
            count = 0
          }
          thread_wrap = []
        }
        if (Number(comment.message_type) === COMMENT_TYPES.comment) {
          count++
        }
        if (Number(comment.message_type) === COMMENT_TYPES.resolve) {
          threadClass = 'comment-thread-resolved'
          thread_wrap.push(
            <div className="comment-resolved" key={'comment-' + i}>
              <span className="comment-resolved-label">
                {comment.is_anonymous === 0 && (
                  <span className="comment-username comment-resolvedby">
                    {comment.full_name}
                  </span>
                )}
                <span className="">
                  {' '}
                  {comment.is_anonymous === 0 ? 'm' : 'M'}arked as resolved
                </span>
              </span>
            </div>,
          )
        } else {
          threadClass = 'comment-thread-active'
          let text = nl2br(comment.message)
          text = parseCommentHtml(text)
          const formattedDate = new Date(
            comment.timestamp ? comment.timestamp * 1000 : comment.create_date,
          )
            .toString()
            .split('(')[0]
            .trim()
          const isAuthorOfLastComment =
            commentsCopy[commentsCopy.length - 1].id === comment.id &&
            comment.uid === context.userInfo?.user.uid &&
            comment.source_page == config.revisionNumber + 1
          deleteButton = isAuthorOfLastComment ? (
            <Button
              type={BUTTON_TYPE.DEFAULT}
              mode={BUTTON_MODE.GHOST}
              size={BUTTON_SIZE.ICON_XSMALL}
              onClick={deleteComment}
            >
              <Trash size={20} />
            </Button>
          ) : (
            ''
          )
          thread_wrap.push(
            <div className="comment-item comment-clearfix" key={'comment-' + i}>
              <div className="bc-show-comment-top">
                {comment.is_anonymous === 1 ? (
                  <div className="comment-label comment-username comment-username-label comment-truncate">
                    {comment.full_name}
                  </div>
                ) : (
                  <div className="comment-label comment-username comment-username-label comment-truncate">
                    {comment.full_name}
                    <span>
                      {' '}
                      {comment.source_page === 1
                        ? '(translator)'
                        : comment.source_page === 2
                          ? '(revisor)'
                          : '(2nd pass revisor)'}
                    </span>
                  </div>
                )}
                {deleteButton}
              </div>
              <div className="comment-info-wrap comment-clearfix">
                <span className="comment-info comment-time pull-left">
                  {formattedDate}
                </span>
              </div>
              <p
                className="comment-body"
                dangerouslySetInnerHTML={{__html: text}}
              />
            </div>,
          )
        }

        thread_id = comment.thread_id
      })
      // Thread is not resolved
      if (
        !isUndefined(commentsCopy.length - 1) &&
        !(
          parseInt(commentsCopy[commentsCopy.length - 1].message_type) ===
          COMMENT_TYPES.resolve
        )
      ) {
        resolveButton = (
          <Button
            type={BUTTON_TYPE.DEFAULT}
            mode={BUTTON_MODE.OUTLINE}
            size={BUTTON_SIZE.SMALL}
            onClick={() => resolveThread()}
          >
            <Check size={16} /> Resolve
          </Button>
        )
      }
      if (thread_wrap.length > 0) {
        commentsHtml.push(
          <div
            key={'thread-' + 900}
            className={'comment-thread comment-clearfix ' + threadClass}
            data-count={count}
          >
            {thread_wrap}
            <div className={'comment-thread-footer'}>{resolveButton}</div>
          </div>,
        )
      }

      htmlComments = commentsHtml
    }

    const userMentionData =
      teamUsers?.map((user) => ({
        id: user.uid,
        display: ` ${user.first_name} ${user.last_name} `, // eslint-disable-line
      })) ?? []

    // workaround - textarea fit to content
    if (commentInputRef.current) {
      setTimeout(() => {
        if (commentInputRef.current)
          commentInputRef.current.style.height = `${commentInputRef.current.parentNode.clientHeight}px`
      }, 200)
    }

    htmlInsert = (
      <div className="comment-thread comment-post-wrap comment-clearfix comment-first-input">
        <div className="comment-post">
          <span className="comment-label comment-username comment-username-label comment-truncate comment-anonymous-label">
            {!anonymousComments
              ? context.userInfo.user.first_name +
                ' ' +
                context.userInfo.user.last_name
              : config.isReview
                ? config.revisionNumber === 2
                  ? '2nd pass revisor'
                  : 'Revisor'
                : 'Translator'}
          </span>
          <MentionsInput
            inputRef={(input) => (commentInputRef.current = input)}
            value={mentionsInputValue}
            onKeyDown={(e) => onKeyDown(e)}
            onChange={handleChangeMentionsInputValue}
            placeholder="Write a comment..."
            className="comment-input comment-textarea"
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
              onAdd={() => saveDraftRef.current()}
              onRemove={() => null}
              isLoading={false}
              appendSpaceOnAdd={false}
            />
          </MentionsInput>
          <div className="comment-bottom">
            <div>
              <Checkbox
                onChange={(value) => {
                  setAnonymousComments(value)
                  commonUtils.addInStorage(localStorageKey, value)
                }}
                label={'Post your comment anonymously'}
                value={
                  anonymousComments
                    ? CHECKBOX_STATE.CHECKED
                    : CHECKBOX_STATE.UNCHECKED
                }
              />
            </div>
            <Button
              type={BUTTON_TYPE.PRIMARY}
              size={BUTTON_SIZE.STANDARD}
              onClick={() => instanceRef.current.sendComment()}
              disabled={!mentionsInputValue}
            >
              Comment
            </Button>
          </div>
          {sendCommentError ? (
            <div className="comment-ajax-wrap">
              <span className="comment-warnings">
                Oops, something went wrong. Please try again later.
              </span>
            </div>
          ) : null}

          <div></div>
        </div>
      </div>
    )

    return (
      <div className="comment-balloon-outer">
        <div className="comment-balloon-inner">
          <div className="comment-triangle comment-open-view comment-re-messages" />
          <Button
            type={BUTTON_TYPE.ICON}
            size={BUTTON_SIZE.ICON_XSMALL}
            className="comment-close-btn"
            onClick={(e) => closeComments(e)}
          >
            <IconClose size={10} />
          </Button>
          <div className="comments-wrap" ref={(wrap) => (wrapRef.current = wrap)}>
            {htmlComments}
          </div>
          {htmlInsert}
        </div>
      </div>
    )
  }

  const scrollToBottom = () => {
    if (wrapRef.current) {
      const scrollHeight = wrapRef.current.scrollHeight
      const height = wrapRef.current.clientHeight
      const maxScrollTop = scrollHeight - height
      wrapRef.current.scrollTop = maxScrollTop > 0 ? maxScrollTop : 0
    }
  }

  // Updated every render so both the mount effect and the update effect can call
  // scrollToBottomRef.current() without exhaustive-deps friction — scrollToBottom itself is
  // recreated every render (matching this migration's convention for non-listener handlers).
  const scrollToBottomRef = useRef()
  scrollToBottomRef.current = scrollToBottom

  const isFirstRenderRef = useRef(true)

  instanceRef.current.state = {
    comments,
    user,
    teamUsers,
    sendCommentError,
    showTagging,
    mentionsInputValue,
    anonymousComments,
    mentionsMarkup,
  }
  instanceRef.current.setState = (partial) => {
    if ('comments' in partial) setComments(partial.comments)
    if ('user' in partial) setUser(partial.user)
    if ('teamUsers' in partial) setTeamUsersState(partial.teamUsers)
    if ('sendCommentError' in partial)
      setSendCommentError(partial.sendCommentError)
    if ('mentionsInputValue' in partial)
      setMentionsInputValue(partial.mentionsInputValue)
    if ('anonymousComments' in partial)
      setAnonymousComments(partial.anonymousComments)
    if ('mentionsMarkup' in partial) setMentionsMarkup(partial.mentionsMarkup)
  }
  instanceRef.current.sendComment = sendComment
  instanceRef.current.handleChangeMentionsInputValue =
    handleChangeMentionsInputValue
  instanceRef.current.onKeyDown = onKeyDown
  instanceRef.current.updateComments = updateCommentsRef.current
  instanceRef.current.setTeamUsers = setTeamUsersRef.current

  useImperativeHandle(ref, () => instanceRef.current)

  useEffect(() => {
    const draftText = CommentsStore.getDraftComment(context.segment.sid)
    if (draftText) {
      setMentionsInputValue(draftText)
    }

    const updateComments = updateCommentsRef.current
    const setFocusOnInput = setFocusOnInputRef.current
    const handleSetTeamUsers = setTeamUsersRef.current

    updateComments(context.segment.sid)
    CommentsStore.addListener(CommentsConstants.ADD_COMMENT, updateComments)
    CommentsStore.addListener(
      CommentsConstants.DELETE_COMMENT,
      updateComments,
    )
    CommentsStore.addListener(
      CommentsConstants.STORE_COMMENTS,
      updateComments,
    )
    CommentsStore.addListener(CommentsConstants.SET_FOCUS, setFocusOnInput)
    CommentsStore.addListener(
      CommentsConstants.SET_TEAM_USERS,
      handleSetTeamUsers,
    )
    scrollToBottomRef.current()
    commentInputRef.current.focus()

    return () => {
      CommentsStore.removeListener(
        CommentsConstants.ADD_COMMENT,
        updateComments,
      )
      CommentsStore.removeListener(
        CommentsConstants.DELETE_COMMENT,
        updateComments,
      )
      CommentsStore.removeListener(
        CommentsConstants.STORE_COMMENTS,
        updateComments,
      )
      CommentsStore.removeListener(CommentsConstants.SET_FOCUS, setFocusOnInput)
      CommentsStore.removeListener(
        CommentsConstants.SET_TEAM_USERS,
        handleSetTeamUsers,
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (isFirstRenderRef.current) {
      isFirstRenderRef.current = false
      return
    }
    scrollToBottomRef.current()
  })

  //if is not splitted or is the first of the splitted group
  if (
    (!context.segment.splitted ||
      context.segment.sid.split('-')[1] === '1') &&
    comments
  ) {
    if (context.segment.openComments && context.userInfo) {
      return getComments()
    }
  } else {
    return null
  }
})

SegmentCommentsContainer.displayName = 'SegmentCommentsContainer'

export default SegmentCommentsContainer
