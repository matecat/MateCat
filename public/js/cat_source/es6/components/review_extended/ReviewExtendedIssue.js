import moment from 'moment'
import React from 'react'
import {isUndefined} from 'lodash'
import SegmentActions from '../../actions/SegmentActions'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import CommonUtils from '../../utils/commonUtils'
import CatToolActions from '../../actions/CatToolActions'
import classnames from 'classnames'

class ReviewExtendedIssue extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      commentView: false,
      sendDisabled: true,
      visible:
        isUndefined(this.props.issue.visible) || this.props.issue.visible,
    }
    this.issueCategories = JSON.parse(config.lqa_nested_categories).categories
  }

  getCategory() {
    const id_category = this.props.issue.id_category
    return this.issueCategories.find((cat) => parseInt(cat.id) == id_category)
  }
  getSeverity() {
    const id_category = this.props.issue.id_category
    const {severity} = this.props.issue
    return this.issueCategories
      .find((cat) => parseInt(cat.id) == id_category)
      .severities.find((sev) => sev.label === severity)
  }

  deleteIssue(event) {
    event.preventDefault()
    event.stopPropagation()
    this.props.changeVisibility(this.props.issue.id, false)
    this.setState({
      visible: false,
    })
    let self = this
    CatToolActions.removeAllNotifications()
    let notification = {
      title: 'Issue deleted',
      text:
        'The issue has been deleted. <a class="undo-issue-deleted undo-issue-deleted-' +
        self.props.issue.id +
        '">Undo</a>',
      type: 'warning',
      position: 'bl',
      allowHtml: true,
      timer: 10000,
      closeCallback: function () {
        if (!self.state.visible) {
          SegmentActions.deleteIssue(self.props.issue, self.props.sid)
        }
      },
    }
    CatToolActions.addNotification(notification)
    window.onbeforeunload = function () {
      SegmentActions.deleteIssue(self.props.issue, self.props.sid)
    }
    setTimeout(function () {
      let $button = $('.undo-issue-deleted-' + self.props.issue.id)
      $button.off('click')
      $button.on('click', function () {
        self.setState({
          visible: true,
        })
        self.props.changeVisibility(self.props.issue.id, true)
        CatToolActions.removeAllNotifications()
        notification = {
          title: 'Issue deleted',
          text: 'The issue has been restored.',
          type: 'warning',
          position: 'bl',
          timer: 5000,
        }
        CatToolActions.addNotification(notification)
        window.onbeforeunload = null
      })
    }, 500)
  }
  confirmDeletedIssue(sid, data) {
    let issue_id = data
    if (
      sid === this.props.issue.id_segment &&
      issue_id === this.props.issue.id
    ) {
      $(this.el).transition('fade left')
    }
  }

  openCommentsAfterCreation(sid, id) {
    if (
      sid === this.props.sid &&
      id === this.props.issue.id &&
      this.props.issue.target_text
    ) {
      this.setState({
        commentView: true,
      })
    } else {
      this.setState({
        commentView: false,
      })
    }
  }
  setCommentView(event) {
    event.preventDefault()
    event.stopPropagation()

    if (!this.state.commentView) {
      setTimeout(() => {
        const input = this.el && $(this.el).find('.re-comment-input')
        input && input.length && input[0].focus()
      }, 100)
    }
    this.setState({
      commentView: !this.state.commentView,
    })
  }

  handleCommentChange(event) {
    var text = event.target.value,
      disabled = true

    if (text.length > 0) {
      disabled = false
    }
    this.setState({
      comment_text: text,
      sendDisabled: disabled,
    })
  }

  addComment(e) {
    e.preventDefault()
    let self = this
    if (!this.state.comment_text || this.state.comment_text.length === 0) {
      return
    }

    var data = {
      message: this.state.comment_text,
      source_page: config.isReview ? config.revisionNumber + 1 : 1, // TODO: move this to UI property
    }

    this.setState({sendDisabled: true})

    SegmentActions.submitIssueComment(this.props.sid, this.props.issue.id, data)
      .then(function () {
        self.setState({
          comment_text: '',
        })
      })
      .catch(() => this.handleFail())
  }

  handleFail() {
    CommonUtils.genericErrorAlertMessage()
    this.setState({sendDisabled: false})
  }
  generateHtmlCommentLines() {
    let array = []
    let comments = this.props.issue.comments,
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
    if (array.length > 0) {
      array = array.reverse()
    }
    return array
  }
  componentDidMount() {
    SegmentStore.addListener(
      SegmentConstants.ISSUE_DELETED,
      this.confirmDeletedIssue.bind(this),
    )
    SegmentStore.addListener(
      SegmentConstants.OPEN_ISSUE_COMMENT,
      this.openCommentsAfterCreation.bind(this),
    )
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.ISSUE_DELETED,
      this.confirmDeletedIssue,
    )
    SegmentStore.removeListener(
      SegmentConstants.OPEN_ISSUE_COMMENT,
      this.openCommentsAfterCreation,
    )
  }

  render() {
    if (this.state.visible) {
      const category = this.getCategory()
      const severity = this.getSeverity()
      // let formatted_date = moment(this.props.issue.created_at).format('lll');

      let commentViewButtonClass = this.state.commentView ? 're-active' : ''
      commentViewButtonClass =
        this.props.issue.comments.length > 0 || this.props.issue.target_text
          ? commentViewButtonClass + ' re-message'
          : commentViewButtonClass
      let iconCommentClass =
        this.props.issue.comments.length > 0 || this.props.issue.target_text
          ? 'icon-uniE96B icon'
          : 'icon-uniE96E icon'
      //START comments html section
      let htmlCommentLines = this.generateHtmlCommentLines()

      let renderHtmlCommentLines = ''
      if (htmlCommentLines.length > 0 || this.props.issue.target_text) {
        renderHtmlCommentLines = (
          <div className="re-comment-list">
            {this.props.issue.target_text ? (
              <div className="re-highlighted">
                <span className="re-selected-text">
                  <b>Selected text:</b>
                </span>
                {this.props.issue.target_text}
              </div>
            ) : null}
            {htmlCommentLines}
          </div>
        )
      }

      let containerClass = classnames({
        're-item': true,
        'issue-comments-open': this.state.commentView,
      })

      let commentSection = (
        <div className="comments-view shadow-1">
          {renderHtmlCommentLines}
          <div className="re-add-comment">
            <form className="ui form" onSubmit={this.addComment.bind(this)}>
              <div className="field">
                <input
                  className="re-comment-input"
                  autoComplete="off"
                  value={this.state.comment_text}
                  type="text"
                  name="first-name"
                  placeholder="Add a comment + press Enter"
                  onChange={this.handleCommentChange.bind(this)}
                />
              </div>
            </form>
          </div>
        </div>
      )
      //END comments html section

      return (
        <div className={containerClass} ref={(node) => (this.el = node)}>
          <div className="re-item-box re-issue shadow-1">
            <div className="issue-head pad-right-10">
              <span className="re-category-issue-head" title={category.label}>
                {category.label}
              </span>
              <b>
                <span title={severity.label}>
                  [
                  {severity.code
                    ? severity.code
                    : severity.label.substring(0, 3)}
                  ]
                </span>
              </b>
            </div>
            <div className="issue-activity-icon">
              {this.props.actions && (
                <div className="icon-buttons">
                  <button
                    className={
                      'ui icon basic tiny button issue-note ' +
                      commentViewButtonClass
                    }
                    onClick={this.setCommentView.bind(this)}
                    title="Comments"
                  >
                    <i className={iconCommentClass} />
                  </button>
                  {this.props.isReview &&
                  this.props.issue.revision_number <=
                    this.props.currentReview ? (
                    <button
                      className="ui icon basic tiny button issue-delete"
                      onClick={this.deleteIssue.bind(this)}
                      title="Delete issue card"
                    >
                      <i className="icon-trash-o icon" />
                    </button>
                  ) : null}
                </div>
              )}
            </div>
          </div>

          {this.state.commentView ? commentSection : null}
        </div>
      )
    } else {
      return null
    }
  }
}

export default ReviewExtendedIssue
