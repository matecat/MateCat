import React from 'react'
import SegmentActions from '../../actions/SegmentActions'

import Shortcuts from '../../utils/shortcuts'
import SegmentUtils from '../../utils/segmentUtils'

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
    $(this.button).addClass('open')
    SegmentActions.openIssuesPanel({sid: this.props.sid}, true)
  }

  render() {
    const issuesCount = this.getIssueCount()
    let openClass = this.props.open ? 'open-issues' : ''
    let plus = config.isReview ? (
      <span className="revise-button-counter">+</span>
    ) : null
    if (issuesCount > 0) {
      return (
        <div title="Add Issues" onClick={this.handleClick.bind(this)}>
          <a
            ref={(button) => (this.button = button)}
            className={'revise-button has-object ' + openClass}
          >
            <span className="icon-error_outline" />
            <span className="revise-button-counter">{issuesCount}</span>
          </a>
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
          title={
            'Show Issues (' +
            Shortcuts.cattol.events.openIssuesPanel.keystrokes[
              Shortcuts.shortCutsKeyType
            ].toUpperCase() +
            ')'
          }
          onClick={this.handleClick.bind(this)}
        >
          <a
            ref={(button) => (this.button = button)}
            className={'revise-button ' + openClass}
          >
            <span className="icon-error_outline" />
            {plus}
          </a>
        </div>
      )
    } else {
      return ''
    }
  }
}

export default ReviewExtendedTranslationIssuesSideButton
