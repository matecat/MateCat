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
    if (
      config.isReview &&
      !(
        SegmentUtils.isIceSegment(this.props.segment) &&
        !this.props.segment.unlocked
      )
    ) {
      return (
        <div
          className={`revise-button ${issuesCount === 0 && 'no-object'}`}
          title={
            issuesCount > 0
              ? `Show Issues ( ${Shortcuts.cattol.events.openIssuesPanel.keystrokes[
                  Shortcuts.shortCutsKeyType
                ].toUpperCase()}     )`
              : 'Add Issues'
          }
          onClick={this.handleClick.bind(this)}
        >
          <ReviseIssuesIcon />
          <div className="badge-icon badge-red ">
            {issuesCount > 0 ? issuesCount : '+'}
          </div>
        </div>
      )
    } else {
      return ''
    }
  }
}

export default ReviewExtendedTranslationIssuesSideButton
