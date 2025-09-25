import React from 'react'
import SegmentActions from '../../actions/SegmentActions'
import $ from 'jquery'
import {Shortcuts} from '../../utils/shortcuts'
import SegmentUtils from '../../utils/segmentUtils'
import ReviseIssuesIcon from '../../../img/icons/ReviseIssuesIcon'

class ReviewExtendedTranslationIssuesSideButton extends React.Component {
  getIssueCount() {
    let issue_count = 0
    if (this.props.segment.versions && this.props.segment.versions.length > 0) {
      this.props.segment.versions.forEach((version) => {
        issue_count = issue_count + version.issues.length
      })
      return issue_count
    } else {
      return 0
    }
  }

  handleClick(e) {
    e.preventDefault()
    e.stopPropagation()
    SegmentActions.openIssuesPanel({sid: this.props.sid}, true)
  }

  render() {
    const issuesCount = this.getIssueCount()
    let openClass = this.props.open ? 'open-issues' : ''
    if (issuesCount > 0) {
      return (
        <div
          className="revise-button has-object"
          title="Add Issues"
          onClick={this.handleClick.bind(this)}
        >
          <ReviseIssuesIcon />
          <div className="badge-icon badge-red ">{issuesCount}</div>
        </div>
      )
    } else if (
      config.isReview &&
      !(
        SegmentUtils.isIceSegment(this.props.segment) &&
        !this.props.segment.unlocked
      )
    ) {
      return (
        <div
          className="revise-button"
          title={
            'Show Issues (' +
            Shortcuts.cattol.events.openIssuesPanel.keystrokes[
              Shortcuts.shortCutsKeyType
            ].toUpperCase() +
            ')'
          }
          onClick={this.handleClick.bind(this)}
        >
          <ReviseIssuesIcon />
          <div className="badge-icon badge-red ">+</div>
        </div>
      )
    } else {
      return ''
    }
  }
}

export default ReviewExtendedTranslationIssuesSideButton
