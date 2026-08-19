import React, {useContext, useState, useEffect, useRef} from 'react'
import {isUndefined} from 'lodash'

import CommentsStore from '../../stores/CommentsStore'
import SegmentActions from '../../actions/SegmentActions'
import CommentsConstants from '../../constants/CommentsConstants'
import {Shortcuts} from '../../utils/shortcuts'
import {SegmentContext} from './SegmentContext'
import SegmentUtils from '../../utils/segmentUtils'
import CommentsIcon from '../../../img/icons/CommentsIcon'

const SegmentsCommentsIcon = () => {
  const context = useContext(SegmentContext)
  const contextRef = useRef(context)
  contextRef.current = context // always holds the CURRENT context, read live inside updateComments,
  // mirroring `this.context` never being a stale closure

  const [comments, setComments] = useState(null)

  // Created once via useRef so its identity never changes across renders — required so that
  // CommentsStore.addListener/removeListener (matched by reference) stay in sync, exactly like the
  // class's single this.updateComments.bind(this) in the constructor.
  const updateCommentsRef = useRef((sid) => {
    const {segment} = contextRef.current
    if (isUndefined(sid) || sid === segment.sid) {
      const updatedComments = CommentsStore.getCommentsCountBySegment(
        segment.original_sid,
      )
      setComments(updatedComments)
    }
  })
  const updateComments = updateCommentsRef.current

  useEffect(() => {
    updateComments(contextRef.current.segment.sid)
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []) // intentionally empty — must run exactly once on mount/unmount, matching
  // componentDidMount/componentWillUnmount timing exactly; do not add deps here

  const {segment} = context

  const openComments = (event) => {
    event.stopPropagation()
    SegmentActions.openSegmentComment(segment.sid)
    if (!SegmentUtils.isReadonlySegment(segment))
      SegmentActions.openSegment(segment.sid)
  }

  if ((!segment.splitted || segment.sid.split('-')[1] === '1') && comments) {
    let html
    let rootClasses = ['comment-icon-btn', 'txt']
    if (comments.total === 0 || (comments.total > 0 && comments.active === 0)) {
      html = <div className="badge-icon badge-blue">+</div>
    } else if (comments.active > 0) {
      rootClasses.push('has-object')
      html = <div className="badge-icon badge-blue ">{comments.active}</div>
    }

    return (
      <div
        className={rootClasses.join(' ')}
        title={
          'Add comment (' +
          Shortcuts.cattol.events.openComments.keystrokes[
            Shortcuts.shortCutsKeyType
          ].toUpperCase() +
          ')'
        }
        onClick={(e) => openComments(e)}
      >
        <div className="comment-icon">
          <CommentsIcon />
          {html}
        </div>
      </div>
    )
  }
  return null
}

export default SegmentsCommentsIcon
