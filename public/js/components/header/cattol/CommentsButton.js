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
import {Popover, POPOVER_ALIGN} from '../../common/Popover/Popover'
import CommentsIcon from '../../../../img/icons/CommentsIcon'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'

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
    if (CommentsStore?.db?.history.length > 0) {
      return CommentsStore?.db?.history?.map((comment) => {
        let message = comment.message
          .replace(/\/r/g, <br />)
          .replace(/\/n/g, <br />)
        message = parseCommentHtml(message)
        return (
          <div
            key={'comment' + comment.timestamp}
            className={`popover-comments-item ${comment.thread_id == null ? 'active' : 'resolved'}`}
          >
            <div className={'popover-comments-item-header'}>
              <span className={'popover-comments-item-name'}>
                Segment {comment.id_segment}
              </span>
              <span>{comment.full_name}</span>
            </div>
            <div className={'popover-comments-item-text'}>
              <p dangerouslySetInnerHTML={{__html: message}} />
            </div>
            <div>
              <Button
                mode={BUTTON_MODE.OUTLINE}
                type={
                  comment.thread_id == null
                    ? BUTTON_TYPE.PRIMARY
                    : BUTTON_TYPE.SUCCESS
                }
                size={BUTTON_SIZE.SMALL}
                onClick={() => openSegmentComment(comment.id_segment)}
              >
                View thread
              </Button>
            </div>
          </div>
        )
      })
    } else {
      return <div>No comments</div>
    }
  }
  useEffect(() => {
    if (config.id_team) {
      getTeamUsers({teamId: config.id_team, teamName: config.team_name}).then(
        (data) => {
          CommentsActions.updateTeamUsers(data)
        },
      )
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
        <Popover
          align={POPOVER_ALIGN.CENTER}
          className={'comments-popover'}
          closeOnClickInside={true}
          toggleButtonProps={{
            children: (
              <>
                <CommentsIcon size={24} />
                {counterResolvedComments > 0 && (
                  <div
                    className={`button-badge button-badge-success ${counterOpenComments > 0 ? 'button-badge-left' : ''}`}
                  >
                    {counterResolvedComments}
                  </div>
                )}
                {counterOpenComments > 0 && (
                  <div className="button-badge button-badge-info">
                    {counterOpenComments}
                  </div>
                )}
              </>
            ),
            size: BUTTON_SIZE.ICON_STANDARD,
            mode: BUTTON_MODE.GHOST,
            type: BUTTON_TYPE.ICON,
            disabled: counterOpenComments + counterResolvedComments === 0,
          }}
          disabled={counterOpenComments + counterResolvedComments === 0}
          forceOpenMenu={showComments}
        >
          <div className="popover-comments-container">{renderComments()}</div>
        </Popover>
      )}
    </>
  )
}
