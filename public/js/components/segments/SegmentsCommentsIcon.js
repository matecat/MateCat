import React from 'react'
import {isUndefined} from 'lodash'

import CommentsStore from '../../stores/CommentsStore'
import SegmentActions from '../../actions/SegmentActions'
import CommentsConstants from '../../constants/CommentsConstants'
import {Shortcuts} from '../../utils/shortcuts'
import {SegmentContext} from './SegmentContext'
import SegmentUtils from '../../utils/segmentUtils'
import CommentsIcon from '../../../img/icons/CommentsIcon'

class SegmentsCommentsIcon extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    this.state = {
      comments: null,
    }
    this.types = {sticky: 3, resolve: 2, comment: 1}

    this.updateComments = this.updateComments.bind(this)
  }

  updateComments(sid) {
    if (isUndefined(sid) || sid === this.context.segment.sid) {
      const comments = CommentsStore.getCommentsCountBySegment(
        this.context.segment.original_sid,
      )
      this.setState({
        comments: comments,
      })
    }
  }

  openComments(event) {
    event.stopPropagation()
    SegmentActions.openSegmentComment(this.context.segment.sid)
    if (!SegmentUtils.isReadonlySegment(this.context.segment))
      SegmentActions.openSegment(this.context.segment.sid)
  }

  componentDidMount() {
    this.updateComments(this.context.segment.sid)
    CommentsStore.addListener(
      CommentsConstants.ADD_COMMENT,
      this.updateComments,
    )
    CommentsStore.addListener(
      CommentsConstants.STORE_COMMENTS,
      this.updateComments,
    )
  }

  componentWillUnmount() {
    CommentsStore.removeListener(
      CommentsConstants.ADD_COMMENT,
      this.updateComments,
    )
    CommentsStore.removeListener(
      CommentsConstants.STORE_COMMENTS,
      this.updateComments,
    )
  }

  render() {
    //if is not splitted or is the first of the splitted group
    if (
      (!this.context.segment.splitted ||
        this.context.segment.sid.split('-')[1] === '1') &&
      this.state.comments
    ) {
      let html
      let rootClasses = ['mbc-comment-icon-button', 'txt']
      if (
        this.state.comments.total === 0 ||
        (this.state.comments.total > 0 && this.state.comments.active === 0)
      ) {
        html = <div className="badge-icon badge-blue">+</div>
      } else if (this.state.comments.active > 0) {
        rootClasses.push('has-object')
        html = (
          <div className="badge-icon badge-blue ">
            {this.state.comments.active}
          </div>
        )
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
          onClick={(e) => this.openComments(e)}
        >
          <div className="mbc-comment-icon">
            <CommentsIcon />
            {html}
          </div>
        </div>
      )
    } else {
      return null
    }
  }
}

export default SegmentsCommentsIcon
