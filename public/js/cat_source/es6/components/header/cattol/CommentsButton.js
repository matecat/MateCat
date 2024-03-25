import React, {useEffect, useState} from 'react'
import CommentsActions from '../../../actions/CommentsActions'
import {getComments} from '../../../api/getComments'
import CommentsStore from '../../../stores/CommentsStore'
import CommentsConstants from '../../../constants/CommentsConstants'
import CatToolActions from '../../../actions/CatToolActions'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import SegmentActions from '../../../actions/SegmentActions'
import {getTeamUsers} from '../../../api/getTeamUsers'

const commentsTypes = {sticky: 3, resolve: 2, comment: 1}
export const CommentsButton = ({}) => {
  const [teamUsers, setTeamUsers] = useState([])
  const [comments, setComments] = useState([])
  const [counterOpenComments, setCounterOpenComments] = useState(0)
  const [counterResolvedComments, setCounterResolvedComments] = useState(0)
  const [showComments, setShowComments] = useState(false)
  const loadCommentData = () => {
    getComments({}).then((resp) => {
      setComments(resp.data.entries.comments)
      CommentsActions.storeComments(resp.data.entries.comments, resp.data.user)
    })
  }
  const toggleComments = () => {
    CatToolActions.closeSubHeader()
    setShowComments(!showComments)
  }
  const updateComments = () => {
    setComments(CommentsStore.db.history)
  }
  const parseCommentHtml = (text) => {
    const regExp = /{@([0-9]+|team)@}/gm
    if (regExp.test(text)) {
      text = text.replace(regExp, (match, id) => {
        id = id === 'team' ? id : parseInt(id)
        const user = teamUsers
          ? teamUsers.find((user) => user.uid === id)
          : undefined
        if (user) {
          return (
            '<span class="tagging-item">' +
            user.first_name +
            ' ' +
            user.last_name +
            '</span>'
          )
        }
        return match
      })
    }
    return text
  }
  const openSegmentComment = (sid) => {
    SegmentActions.scrollToSegment(sid, SegmentActions.openSegmentComment)
    setShowComments(false)
  }
  const renderComments = () => {
    return CommentsStore.db.history.map((comment) => {
      let message = comment.message
        .replace(/\/r/g, <br />)
        .replace(/\/n/g, <br />)
      message = parseCommentHtml(message)
      return (
        <div
          key={'comment' + comment.timestamp}
          className={`mbc-thread-wrap mbc-thread-wrap-active mbc-clearfix ${
            comment.thread_id == null
              ? 'mbc-thread-wrap-active'
              : 'mbc-thread-wrap-resolved'
          }`}
        >
          <div className="mbc-show-comment mbc-clearfix">
            <span className="mbc-nth-comment mbc-nth-comment-label">
              Segment{' '}
              <span className="mbc-comment-segment-number">
                {comment.id_segment}
              </span>
            </span>
            <span className="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate">
              {comment.full_name}
            </span>
            <span className="mbc-comment-label mbc-comment-email-label mbc-truncate">
              {comment.email ? comment.email : ''}
            </span>
            <div className="mbc-comment-info-wrap mbc-clearfix">
              <span className="mbc-comment-info mbc-comment-time pull-left"></span>
            </div>
            <p
              className="mbc-comment-body"
              dangerouslySetInnerHTML={{__html: message}}
            />
            <div className="mbc-clearfix mbc-view-comment-wrap">
              <a
                href={'javascript:;'}
                className="mbc-comment-link-btn mbc-view-link mbc-show-comment-btn"
                onClick={() => openSegmentComment(comment.id_segment)}
              >
                View thread
              </a>
            </div>
          </div>
        </div>
      )
    })
  }
  useEffect(() => {
    if (config.id_team) {
      getTeamUsers({teamId: config.id_team}).then((data) => {
        CommentsActions.updateTeamUsers(data)
      })
    }
    const close = () => setShowComments(false)
    const openMenu = () => setShowComments(true)
    const setUsers = (users) => setTeamUsers(users)
    loadCommentData()
    CommentsStore.addListener(CommentsConstants.DELETE_COMMENT, updateComments)
    CommentsStore.addListener(CommentsConstants.ADD_COMMENT, updateComments)
    CommentsStore.addListener(CommentsConstants.OPEN_MENU, openMenu)
    CommentsStore.addListener(CommentsConstants.SET_TEAM_USERS, setUsers)
    CatToolStore.addListener(CattolConstants.CLOSE_SUBHEADER, close)
    return () => {
      CommentsStore.removeListener(
        CommentsConstants.DELETE_COMMENT,
        updateComments,
      )
      CommentsStore.removeListener(
        CommentsConstants.ADD_COMMENT,
        updateComments,
      )
      CatToolStore.removeListener(CattolConstants.CLOSE_SUBHEADER, close)
      CatToolStore.removeListener(CattolConstants.OPEN_MENU, openMenu)
      CommentsStore.removeListener(CommentsConstants.SET_TEAM_USERS, setUsers)
    }
  }, [])

  useEffect(() => {
    setCounterOpenComments(CommentsStore.db.getOpenedThreadCount())
    setCounterResolvedComments(CommentsStore.db.getResolvedThreadCount())
  }, [comments])
  return (
    <>
      {config.comments_enabled && (
        <div
          id="mbc-history"
          title="View comments"
          className={
            counterOpenComments + counterResolvedComments === 0
              ? 'mbc-history-balloon-icon-has-no-comments'
              : 'mbc-history-balloon-icon-has-comment'
          }
        >
          <div
            onClick={() =>
              counterOpenComments + counterResolvedComments > 0
                ? toggleComments()
                : null
            }
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="3 3 36 36">
              <path
                fill="#fff"
                fillRule="evenodd"
                stroke="none"
                strokeWidth="1"
                d="M33.125 13.977c-1.25-1.537-2.948-2.75-5.093-3.641C25.886 9.446 23.542 9 21 9c-2.541 0-4.885.445-7.031 1.336-2.146.89-3.844 2.104-5.094 3.64C7.625 15.514 7 17.188 7 19c0 1.562.471 3.026 1.414 4.39.943 1.366 2.232 2.512 3.867 3.439-.114.416-.25.812-.406 1.187-.156.375-.297.683-.422.922-.125.24-.294.505-.508.797a8.15 8.15 0 01-.484.617 249.06 249.06 0 00-1.023 1.133 1.1 1.1 0 00-.126.141l-.109.132-.094.141c-.052.078-.075.127-.07.148a.415.415 0 01-.031.156c-.026.084-.024.146.007.188v.016c.042.177.125.32.25.43a.626.626 0 00.422.163h.079a11.782 11.782 0 001.78-.344c2.73-.697 5.126-1.958 7.189-3.781.78.083 1.536.125 2.265.125 2.542 0 4.886-.445 7.032-1.336 2.145-.891 3.843-2.104 5.093-3.64C34.375 22.486 35 20.811 35 19c0-1.812-.624-3.487-1.875-5.023z"
              ></path>
            </svg>
            <div className="mbc-badge-container">
              {counterResolvedComments > 0 && (
                <span className="mbc-badge-resolved">
                  {counterResolvedComments}
                </span>
              )}
              {counterOpenComments > 0 && (
                <span className="mbc-badge">{counterOpenComments}</span>
              )}
            </div>
          </div>
          {showComments ? (
            <div className="mbc-history-balloon-outer">
              <div className="mbc-triangle mbc-triangle-top" />
              {counterOpenComments + counterResolvedComments === 0 ? (
                <div className="mbc-history-balloon mbc-history-balloon-has-no-comments">
                  <div className="mbc-thread-wrap">
                    <div className="mbc-show-comment">
                      <span className="mbc-comment-label">No comments</span>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="mbc-history-balloon mbc-history-balloon-has-comment">
                  {renderComments()}
                </div>
              )}
            </div>
          ) : null}
        </div>
      )}
    </>
  )
}
