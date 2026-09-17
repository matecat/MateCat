import React, {useContext, useEffect, useMemo, useRef, useState} from 'react'
import {isUndefined} from 'lodash'
import {debounce} from 'lodash/function'
import CommentsStore from '../../stores/CommentsStore'
import CommentsActions from '../../actions/CommentsActions'
import CommentsConstants from '../../constants/CommentsConstants'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import {MentionsInput} from 'react-mentions'
import Mention from '../common/Mention'
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

const MESSAGE_TYPE = {resolve: 2, comment: 1}

const nl2br = (str) =>
  (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br />$2')

const SegmentCommentsContainer = () => {
  const {segment, userInfo} = useContext(SegmentContext)
  const {sid, original_sid: originalSid, splitted, openComments} = segment

  const localStorageKey = 'anonymous-comments' + userInfo?.user.uid

  const [comments, setComments] = useState(() =>
    CommentsStore.getCommentsBySegment(originalSid),
  )
  const [teamUsers, setTeamUsers] = useState(() => CommentsStore.getTeamUsers())
  const [sendCommentError, setSendCommentError] = useState(false)
  const [mentionsInputValue, setMentionsInputValue] = useState('')
  const [mentionsMarkup, setMentionsMarkup] = useState('')
  const [anonymousComments, setAnonymousComments] = useState(
    () => commonUtils.getFromStorage(localStorageKey) === 'true',
  )

  const commentInputRef = useRef(null)
  const wrapRef = useRef(null)

  // saveDraft is debounced, so its body runs long after the render that built
  // it; it has to read the draft through a ref rather than close over it.
  const latestRef = useRef({})
  latestRef.current = {originalSid, mentionsInputValue}

  const saveDraft = useMemo(
    () =>
      debounce(() => {
        CommentsActions.saveDraftComment(
          latestRef.current.originalSid,
          latestRef.current.mentionsInputValue,
        )
      }, 500),
    [],
  )

  useEffect(() => {
    const updateComments = (updatedSid) => {
      if (
        isUndefined(updatedSid) ||
        parseInt(updatedSid) === parseInt(originalSid)
      ) {
        setComments(CommentsStore.getCommentsBySegment(originalSid))
      }
    }
    const setFocusOnInput = () => commentInputRef.current.focus()

    updateComments(sid)
    CommentsStore.addListener(CommentsConstants.ADD_COMMENT, updateComments)
    CommentsStore.addListener(CommentsConstants.DELETE_COMMENT, updateComments)
    CommentsStore.addListener(CommentsConstants.STORE_COMMENTS, updateComments)
    CommentsStore.addListener(CommentsConstants.SET_FOCUS, setFocusOnInput)
    CommentsStore.addListener(CommentsConstants.SET_TEAM_USERS, setTeamUsers)

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
        setTeamUsers,
      )
    }
  }, [sid, originalSid])

  useEffect(() => {
    const draftText = CommentsStore.getDraftComment(sid)
    if (draftText) setMentionsInputValue(draftText)
    commentInputRef.current.focus()
    // Mount only, matching the class's componentDidMount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // componentDidUpdate in the class: after every render, including the first.
  useEffect(() => {
    const wrap = wrapRef.current
    if (!wrap) return
    const maxScrollTop = wrap.scrollHeight - wrap.clientHeight
    wrap.scrollTop = maxScrollTop > 0 ? maxScrollTop : 0
  })

  // Workaround - textarea fit to content. The class started this timer from
  // inside render and never cleared it.
  useEffect(() => {
    if (!commentInputRef.current) return
    const timer = setTimeout(() => {
      const input = commentInputRef.current
      if (input) input.style.height = `${input.parentNode.clientHeight}px`
    }, 200)
    return () => clearTimeout(timer)
  })

  const closeComments = (e) => {
    e.preventDefault()
    e.stopPropagation()
    SegmentActions.closeSegmentComment(sid)
  }

  const sendComment = () => {
    if (!(mentionsMarkup?.length > 0)) return
    // catch must come last: chained after then, it would swallow the failure
    // and clear the error in the same chain that set it.
    CommentsActions.sendComment(mentionsMarkup, anonymousComments, originalSid)
      .then(() => {
        setSendCommentError(false)
        setTimeout(() => {
          if (commentInputRef.current) setMentionsInputValue('')
        })
      })
      .catch(() => setSendCommentError(true))
  }

  const deleteComment = () =>
    CommentsActions.deleteComment(comments[comments.length - 1].id, originalSid)

  const resolveThread = () =>
    CommentsActions.resolveThread(originalSid, anonymousComments)

  const handleChangeMentionsInputValue = (
    event,
    newValue,
    newPlainTextValue,
    mentions,
  ) => {
    setMentionsInputValue(newValue)
    setMentionsMarkup(
      mentions.reduce(
        (acc, cur) =>
          acc.replace(`{@${cur.id}||${cur.display}@}`, `{@${cur.id}@}`),
        newValue,
      ),
    )
  }

  const onKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      if (mentionsInputValue) sendComment()
    } else {
      saveDraft()
    }
  }

  // Show the balloon on a whole segment, or only on the first piece of a split
  // one; and only once the segment has been opened for commenting.
  const isFirstOfSplitGroup = !splitted || sid.split('-')[1] === '1'
  if (!isFirstOfSplitGroup || !comments || !openComments || !userInfo)
    return null

  const findUser = (id) => teamUsers?.find((item) => item.uid === id)

  const parseCommentHtml = (text) => {
    const regExp = /{@([0-9]+|team)@}/gm
    if (!regExp.test(text)) return text
    return text.replace(regExp, (match, rawId) => {
      const id = rawId === 'team' ? rawId : parseInt(rawId)
      const user = findUser(id)
      if (!user) return match
      return `<span contenteditable="false" class="tagging-item" data-id="${id}">${user.first_name} ${user.last_name}</span>`
    })
  }

  let htmlComments
  if (comments.length > 0) {
    let threadWrap = [],
      threadId = 0,
      count = 0,
      threadClass
    const commentsHtml = []
    let deleteButton, resolveButton

    comments.forEach((comment, i) => {
      if (comment.thread_id !== threadId) {
        // start a new thread
        if (threadWrap.length > 0) {
          commentsHtml.push(
            <div
              key={'thread-' + i}
              className={'comment-thread comment-clearfix ' + threadClass}
              data-count={count}
            >
              {threadWrap}
            </div>,
          )
          count = 0
        }
        threadWrap = []
      }
      if (Number(comment.message_type) === MESSAGE_TYPE.comment) count++

      if (Number(comment.message_type) === MESSAGE_TYPE.resolve) {
        threadClass = 'comment-thread-resolved'
        threadWrap.push(
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
        const text = parseCommentHtml(nl2br(comment.message))
        const formattedDate = new Date(
          comment.timestamp ? comment.timestamp * 1000 : comment.create_date,
        )
          .toString()
          .split('(')[0]
          .trim()
        const isAuthorOfLastComment =
          comments[comments.length - 1].id === comment.id &&
          comment.uid === userInfo?.user.uid &&
          comment.source_page == config.revisionNumber + 1
        deleteButton = isAuthorOfLastComment ? (
          <Button
            type={BUTTON_TYPE.DEFAULT}
            mode={BUTTON_MODE.GHOST}
            size={BUTTON_SIZE.ICON_XSMALL}
            onClick={deleteComment}
          >
            <Trash />
          </Button>
        ) : (
          ''
        )
        threadWrap.push(
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

      threadId = comment.thread_id
    })

    // Thread is not resolved
    if (
      !isUndefined(comments.length - 1) &&
      !(
        parseInt(comments[comments.length - 1].message_type) ===
        MESSAGE_TYPE.resolve
      )
    ) {
      resolveButton = (
        <Button
          type={BUTTON_TYPE.DEFAULT}
          mode={BUTTON_MODE.OUTLINE}
          size={BUTTON_SIZE.SMALL}
          onClick={resolveThread}
        >
          <Check /> Resolve
        </Button>
      )
    }
    if (threadWrap.length > 0) {
      commentsHtml.push(
        <div
          key={'thread-' + 900}
          className={'comment-thread comment-clearfix ' + threadClass}
          data-count={count}
        >
          {threadWrap}
          <div className={'comment-thread-footer'}>{resolveButton}</div>
        </div>,
      )
    }

    htmlComments = commentsHtml
  }

  const userMentionData =
    teamUsers?.map((user) => ({
      id: user.uid,
      display: `　${user.first_name} ${user.last_name}　`, // eslint-disable-line
    })) ?? []

  const authorLabel = !anonymousComments
    ? userInfo.user.first_name + ' ' + userInfo.user.last_name
    : config.isReview
      ? config.revisionNumber === 2
        ? '2nd pass revisor'
        : 'Revisor'
      : 'Translator'

  return (
    <div className="comment-balloon-outer">
      <div className="comment-balloon-inner">
        <div className="comment-triangle comment-open-view comment-re-messages" />
        <Button
          type={BUTTON_TYPE.ICON}
          size={BUTTON_SIZE.ICON_XSMALL}
          className="comment-close-btn"
          onClick={closeComments}
        >
          <IconClose />
        </Button>
        <div className="comments-wrap" ref={wrapRef}>
          {htmlComments}
        </div>
        <div className="comment-thread comment-post-wrap comment-clearfix comment-first-input">
          <div className="comment-post">
            <span className="comment-label comment-username comment-username-label comment-truncate comment-anonymous-label">
              {authorLabel}
            </span>
            <MentionsInput
              inputRef={commentInputRef}
              value={mentionsInputValue}
              onKeyDown={onKeyDown}
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
                onAdd={() => saveDraft()}
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
                onClick={sendComment}
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
      </div>
    </div>
  )
}

export default SegmentCommentsContainer
