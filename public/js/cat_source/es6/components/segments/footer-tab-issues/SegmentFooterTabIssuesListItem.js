import React from 'react'
import CommonUtils from '../../../utils/commonUtils'

class SegmentFooterTabIssuesListItem extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      categories: JSON.parse(config.lqa_flat_categories),
      commentView: false,
      sendDisabled: true,
      comment_text: '',
    }
  }

  allowHTML(string) {
    return {__html: string}
  }

  deleteIssue(event) {
    event.preventDefault()
    event.stopPropagation()
    SegmentActions.deleteIssue(this.props.issue, this.props.sid)
  }

  findCategory(id) {
    return this.state.get('categories').find((category) => {
      return id == category.id
    })
  }

  getIssueHeader() {
    let category = this.findCategory(this.props.issue.id_category)
    if (category.id_parent) {
      let parentCategory = this.findCategory(category.id_parent)
      return (
        <div className="issue-head">
          <div>
            <div className="type_issue_name">{parentCategory.label}</div>
            <div className="sub_type_issue_name">{category.label}:</div>
            <div className="severity_issue_name">
              {this.props.issue.severity}
            </div>
          </div>
        </div>
      )
    } else {
      return (
        <div className="issue-head">
          <div>
            <div className="sub_type_issue_name">{category.label}: </div>
            <div className="severity_issue_name">
              {this.props.issue.severity}
            </div>
          </div>
        </div>
      )
    }
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
      rebutted: true,
      message: this.state.comment_text,
      source_page: config.isReview ? 2 : 1, // TODO: move this to UI property
    }

    this.setState({sendDisabled: true})

    SegmentActions.submitComment(
      this.props.issue.id_segment,
      this.props.issue.id,
      data,
    )
      .done(function () {
        self.setState({
          comment_text: '',
        })
      })
      .fail((response) => self.handleFail(response.responseJSON))
  }

  handleFail(response) {
    if (response.errors && response.errors[0].code === 2000) {
      UI.processErrors(response.errors, 'createIssue')
    } else {
      CommonUtils.genericErrorAlertMessage()
    }
    this.setState({sendDisabled: false})
  }

  setCommentView(event) {
    event.preventDefault()
    event.stopPropagation()
    let self = this
    if (!this.state.commentView) {
      setTimeout(function () {
        $(self.el).find('.re-comment-input')[0].focus()
      }, 100)
    }
    this.setState({
      commentView: !this.state.commentView,
    })
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
      }
    }
    if (array.length > 0) {
      array = array.reverse()
    }
    return array
  }

  render() {
    let formatted_date = moment(this.props.issue.created_at).format('lll')

    let commentViewButtonClass = this.state.commentView ? 're-active' : ''
    commentViewButtonClass =
      this.props.issue.comments.length > 0
        ? commentViewButtonClass + ' re-message'
        : commentViewButtonClass
    let iconCommentClass =
      this.props.issue.comments.length > 0
        ? 'icon-uniE96B icon'
        : 'icon-uniE96E icon'
    let htmlCommentLines = this.generateHtmlCommentLines()
    let renderHtmlCommentLines = ''
    if (htmlCommentLines.length > 0) {
      renderHtmlCommentLines = (
        <div className="re-comment-list">{htmlCommentLines}</div>
      )
    }
    let commentSection = (
      <div className="comments-view">
        <div className="re-add-comment">
          <form className="ui form" onSubmit={this.addComment.bind(this)}>
            <div className="field">
              <input
                className="re-comment-input"
                value={this.state.comment_text}
                type="text"
                name="first-name"
                placeholder="Add a comment + press Enter"
                onChange={this.handleCommentChange.bind(this)}
              />
            </div>
          </form>
        </div>
        {renderHtmlCommentLines}
      </div>
    )
    return (
      <div className="issue-item" ref={(node) => (this.el = node)}>
        <div className="issue">
          {this.getIssueHeader()}
          <div className="issue-activity-icon">
            <button
              className={commentViewButtonClass}
              onClick={this.setCommentView.bind(this)}
              title="Comments"
            >
              <i className={iconCommentClass} />
            </button>
            <button>
              <i
                className="icon-trash-o icon"
                onClick={this.deleteIssue.bind(this)}
              />
            </button>
          </div>
        </div>
        {this.state.commentView ? commentSection : null}
      </div>
    )
  }
}

export default SegmentFooterTabIssuesListItem
