import React, {createRef} from 'react'
import PropTypes from 'prop-types'
import moment from 'moment'
import {isUndefined} from 'lodash'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import CommentsSquareIconFilled from '../../../img/icons/CommentsSquareIconFilled'
import Tooltip, {TOOLTIP_POSITION} from '../common/Tooltip'

const generateCommentLines = (issue) => {
  const lines = []
  if (issue.get('comments')) {
    const comments = issue.get('comments').toJS()
    for (const n in comments) {
      const comment = comments[n]
      const date = moment(comment.create_date).format('lll')
      const roleMap = {1: 'Translator', 2: 'Reviewer', 3: 'Reviewer'}
      const classMap = {1: 're-translator', 2: 're-revisor', 3: 're-revisor2'}
      const role = roleMap[comment.source_page]
      const cls = classMap[comment.source_page]
      if (role) {
        lines.push(
          <p key={comment.id} className="re-comment">
            <span className={cls}>{role} </span>
            <span className="re-comment-date">
              <i>({date}): </i>
            </span>
            {comment.comment}
          </p>,
        )
      }
    }
  }
  if (issue.get('target_text')) {
    lines.push(
      <p key={issue.get('issue_id')} className="re-comment">
        <span className="re-selected-text">
          <b>Selected text: </b>
        </span>
        {issue.get('target_text')}
      </p>,
    )
  }
  return lines.length > 0 ? lines.reverse() : lines
}

const SegmentQRIssue = ({issue, index}) => {
  const hasComments =
    (!isUndefined(issue.get('comments')) && issue.get('comments').size > 0) ||
    issue.get('target_text')

  return (
    <div className="qr-issue human critical" key={'qr-issue' + index}>
      <div className="qr-error" key={'error-qr' + index}>
        {issue.get('issue_category')}:{' '}
      </div>
      <div className="qr-severity" key={'severity-qr' + index}>
        <b key={'sev' + index}>[{issue.get('issue_severity')}]</b>
      </div>
      {hasComments && (
        <Tooltip
          position={TOOLTIP_POSITION.BOTTOM}
          isInteractiveContent
          content={<div>{generateCommentLines(issue)}</div>}
        >
          <Button
            ref={createRef()}
            size={BUTTON_SIZE.ICON_XSMALL}
            title="Comments"
            mode={BUTTON_MODE.OUTLINE}
          >
            <CommentsSquareIconFilled size={18} />
          </Button>
        </Tooltip>
      )}
    </div>
  )
}

SegmentQRIssue.propTypes = {
  issue: PropTypes.object.isRequired,
  index: PropTypes.number.isRequired,
}

export default SegmentQRIssue
