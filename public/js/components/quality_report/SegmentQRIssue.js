import React from 'react'
import moment from 'moment'
import {isUndefined} from 'lodash'
import {Popup} from 'semantic-ui-react'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import CommentsIconFilled from '../../../img/icons/CommentsIconFilled'

class SegmentQRIssue extends React.Component {
  generateHtmlCommentLines(issue) {
    let array = []
    if (issue.get('comments')) {
      let comments = issue.get('comments').toJS(),
        comment_date
      for (let n in comments) {
        let comment = comments[n]
        comment_date = moment(comment.create_date).format('lll')

        if (comment.source_page == 1) {
          array.push(
            <p key={comment.id} className="re-comment">
              <span className="re-translator">Translator </span>
              <span className="re-comment-date">
                <i>({comment_date}): </i>
              </span>
              {comment.comment}
            </p>,
          )
        } else if (comment.source_page == 2) {
          array.push(
            <p key={comment.id} className="re-comment">
              <span className="re-revisor">Reviewer </span>
              <span className="re-comment-date">
                <i>({comment_date}): </i>
              </span>
              {comment.comment}
            </p>,
          )
        } else if (comment.source_page == 3) {
          array.push(
            <p key={comment.id} className="re-comment">
              <span className="re-revisor2">Reviewer </span>
              <span className="re-comment-date">
                <i>({comment_date}): </i>
              </span>
              {comment.comment}
            </p>,
          )
        }
      }
    }
    if (issue.get('target_text')) {
      array.push(
        <p key={issue.get('issue_id')} className="re-comment">
          <span className="re-selected-text">
            <b>Selected text: </b>
          </span>
          {issue.get('target_text')}
        </p>,
      )
    }
    if (array.length > 0) {
      array = array.reverse()
    }
    return array
  }
  render() {
    let index = this.props.index
    let issue = this.props.issue
    return (
      <div className="qr-issue human critical" key={'qr-issue' + index}>
        <div className="qr-error" key={'error-qr' + index}>
          {issue.get('issue_category')}:{' '}
        </div>
        <div className="qr-severity" key={'severity-qr' + index}>
          <b key={'sev' + index}>[{issue.get('issue_severity')}]</b>
        </div>
        {(!isUndefined(issue.get('comments')) &&
          issue.get('comments').size > 0) ||
        issue.get('target_text') ? (
          <React.Fragment>
            <Popup
              hoverable
              position="bottom right"
              trigger={
                <Button
                  size={BUTTON_SIZE.ICON_SMALL}
                  title="Comments"
                  mode={BUTTON_MODE.OUTLINE}
                >
                  <CommentsIconFilled size={18} />
                </Button>
              }
            >
              <div
                style={{
                  top: '0px',
                  right: 'auto',
                  left: '616.766px',
                  bottom: 'auto',
                  width: '368.594px',
                }}
              >
                {this.generateHtmlCommentLines(issue)}
              </div>
            </Popup>
          </React.Fragment>
        ) : null}
      </div>
    )
  }
}

export default SegmentQRIssue
