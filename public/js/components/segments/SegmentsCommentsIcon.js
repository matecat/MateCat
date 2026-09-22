import React, {useContext, useEffect, useState} from 'react'
import {isUndefined} from 'lodash'

import CommentsStore from '../../stores/CommentsStore'
import SegmentActions from '../../actions/SegmentActions'
import CommentsConstants from '../../constants/CommentsConstants'
import {Shortcuts} from '../../utils/shortcuts'
import {SegmentContext} from './SegmentContext'
import SegmentUtils from '../../utils/segmentUtils'
import CommentsIcon from '../../../img/icons/CommentsIcon'

const SegmentsCommentsIcon = () => {
  const {segment} = useContext(SegmentContext)
  const [comments, setComments] = useState(null)
  const {sid, original_sid: originalSid} = segment

  // The listener is built inside the effect so it always reads the sid the
  // effect was registered with, rather than the one from the first render.
  useEffect(() => {
    const updateComments = (updatedSid) => {
      // The store omits the sid when it reloads every comment at once.
      if (isUndefined(updatedSid) || updatedSid === sid) {
        setComments(CommentsStore.getCommentsCountBySegment(originalSid))
      }
    }

    updateComments(sid)
    CommentsStore.addListener(CommentsConstants.ADD_COMMENT, updateComments)
    CommentsStore.addListener(CommentsConstants.STORE_COMMENTS, updateComments)

    return () => {
      CommentsStore.removeListener(
        CommentsConstants.ADD_COMMENT,
        updateComments,
      )
      CommentsStore.removeListener(
        CommentsConstants.STORE_COMMENTS,
        updateComments,
      )
    }
  }, [sid, originalSid])

  const openComments = (event) => {
    event.stopPropagation()
    SegmentActions.openSegmentComment(segment.sid)
    if (!SegmentUtils.isReadonlySegment(segment))
      SegmentActions.openSegment(segment.sid)
  }

  // Show the icon on a whole segment, or only on the first piece of a split one.
  const isFirstOfSplitGroup = !segment.splitted || sid.split('-')[1] === '1'
  if (!isFirstOfSplitGroup || !comments) return null

  const rootClasses = ['comment-icon-btn', 'txt']
  let badge
  if (comments.total === 0 || (comments.total > 0 && comments.active === 0)) {
    badge = <div className="badge-icon badge-blue">+</div>
  } else if (comments.active > 0) {
    rootClasses.push('has-object')
    badge = <div className="badge-icon badge-blue ">{comments.active}</div>
  }

  const shortcut =
    Shortcuts.cattol.events.openComments.keystrokes[
      Shortcuts.shortCutsKeyType
    ].toUpperCase()

  return (
    <div
      className={rootClasses.join(' ')}
      title={`Add comment (${shortcut})`}
      onClick={openComments}
    >
      <div className="comment-icon">
        <CommentsIcon />
        {badge}
      </div>
    </div>
  )
}

export default SegmentsCommentsIcon
