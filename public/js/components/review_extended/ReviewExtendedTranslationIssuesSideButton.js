import React from 'react'
import SegmentActions from '../../actions/SegmentActions'
import {Shortcuts} from '../../utils/shortcuts'
import SegmentUtils from '../../utils/segmentUtils'
import ReviseIssuesIcon from '../../../img/icons/ReviseIssuesIcon'

const ReviewExtendedTranslationIssuesSideButton = ({sid, segment, open}) => {
  const getIssueCount = () => {
    let issue_count = 0
    if (segment.versions && segment.versions.length > 0) {
      segment.versions.forEach((version) => {
        issue_count = issue_count + version.issues.length
      })
      return issue_count
    } else {
      return 0
    }
  }

  const handleClick = (e) => {
    e.preventDefault()
    e.stopPropagation()
    SegmentActions.openIssuesPanel({sid: sid}, true)
  }

  const issuesCount = getIssueCount()
  // The button is shown only on hover when empty, unless its panel is open.
  const hidden = issuesCount === 0 && !(open && segment.opened)
  if (
    config.isReview &&
    !(SegmentUtils.isIceSegment(segment) && !segment.unlocked)
  ) {
    return (
      <div
        className={`revise-button${hidden ? ' no-object' : ''}`}
        title={
          issuesCount > 0
            ? `Show issues ( ${Shortcuts.cattol.events.openIssuesPanel.keystrokes[
                Shortcuts.shortCutsKeyType
              ].toUpperCase()}     )`
            : 'Add issues'
        }
        onClick={handleClick}
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

export default ReviewExtendedTranslationIssuesSideButton
