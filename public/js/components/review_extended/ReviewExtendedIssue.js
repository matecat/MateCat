import {isUndefined} from 'lodash'
import React, {useContext, useEffect, useRef, useState} from 'react'
import $ from 'jquery'
import CatToolActions from '../../actions/CatToolActions'
import SegmentActions from '../../actions/SegmentActions'
import CommonUtils from '../../utils/commonUtils'
import moment from 'moment'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import classNames from 'classnames'
import IconEdit from '../icons/IconEdit'
import ReviewExtendedIssuePanel from './ReviewExtendedIssuePanel'
import Trash from '../../../img/icons/Trash'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import CommentsSquareIconFilled from '../../../img/icons/CommentsSquareIconFilled'
import CommentsSquareIcon from '../../../img/icons/CommentsSquareIcon'

export const ReviewExtendedIssue = ({
  sid,
  issue,
  changeVisibility,
  actions,
  isReview,
  currentReview,
  issueEditing,
  setIssueEditing,
  selectionObj,
  versionNumber,
}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const isUserAuthorizedToEditIssue =
    config.ownerIsMe ||
    (!config.ownerIsMe &&
      userInfo.teams.some(({id}) => id === config.id_team)) ||
    userInfo.user.uid === issue.uid

  const [commentView, setCommentView] = useState(false)
  const [visible, setVisible] = useState(
    isUndefined(issue.visible) || issue.visible,
  )
  const [commentText, setCommentText] = useState('')

  const containerRef = useRef()

  const issueCategories = config.lqa_nested_categories.categories

  const getCategory = () => {
    const id_category = issue.id_category
    return issueCategories.find((cat) => parseInt(cat.id) == id_category)
  }

  const getSeverity = () => {
    const id_category = issue.id_category
    const {severity} = issue
    return issueCategories
      .find((cat) => parseInt(cat.id) == id_category)
      .severities.find((sev) => sev.label === severity)
  }

  const editIssue = () => {
    setIssueEditing(
      !issueEditing || (issueEditing && issueEditing.id !== issue.id)
        ? issue
        : undefined,
    )
    setCommentView(false)
  }

  const deleteIssue = (event) => {
    event.preventDefault()
    event.stopPropagation()
    changeVisibility(issue.id, false)

    setVisible(false)

    if (issue.id === issueEditing?.id) setIssueEditing(undefined)

    CatToolActions.removeAllNotifications()
    const notification = {
      title: 'Issue deleted',
      text:
        'The selected issue has been deleted. <a class="undo-issue-deleted undo-issue-deleted-' +
        issue.id +
        '">Undo</a>',
      type: 'warning',
      position: 'bl',
      allowHtml: true,
      timer: 10000,
      closeCallback: function () {
        SegmentActions.deleteIssue(issue, sid)
      },
    }
    CatToolActions.addNotification(notification)
    window.onbeforeunload = function () {
      SegmentActions.deleteIssue(issue, sid)
    }
    setTimeout(function () {
      const $button = $('.undo-issue-deleted-' + issue.id)
      $button.off('click')
      $button.on('click', function () {
        setVisible(true)
        changeVisibility(issue.id, true)
        CatToolActions.removeAllNotifications()
        const notification = {
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

  const setCommentViewCallback = (event) => {
    event.preventDefault()
    event.stopPropagation()

    if (!commentView) {
      setTimeout(() => {
        const input =
          containerRef.current &&
          $(containerRef.current).find('.re-comment-input')
        input && input.length && input[0].focus()
      }, 100)
    }
    setCommentView((prevState) => !prevState)
    setIssueEditing(undefined)
  }

  const handleCommentChange = (event) => {
    const text = event.target.value

    setCommentText(text)
  }

  const addComment = (e) => {
    e.preventDefault()
    if (!commentText || commentText.length === 0) {
      return
    }

    const data = {
      message: commentText,
      source_page: config.isReview ? config.revisionNumber + 1 : 1, // TODO: move this to UI property
    }

    SegmentActions.submitIssueComment(sid, issue.id, data)
      .then(function () {
        setCommentText('')
      })
      .catch(() => handleFail())
  }

  const handleFail = () => {
    CommonUtils.genericErrorAlertMessage()
  }

  const generateHtmlCommentLines = () => {
    let array = []
    let comments = issue.comments,
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

  useEffect(() => {
    const confirmDeletedIssue = (sid, issue_id) => {
      if (sid === issue.id_segment && issue_id === issue.id) {
        containerRef.current.style.transition =
          'transform 1s ease-in-out, opacity 1s ease-in-out'
        containerRef.current.style.transform = 'translateX(-200px)'
        containerRef.current.style.opacity = '0'
      }
    }

    const openCommentsAfterCreation = (sidCompare, id) => {
      setCommentView(sidCompare === sid && id === issue.id && issue.target_text)
    }

    SegmentStore.addListener(
      SegmentConstants.ISSUE_DELETED,
      confirmDeletedIssue,
    )
    SegmentStore.addListener(
      SegmentConstants.OPEN_ISSUE_COMMENT,
      openCommentsAfterCreation,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.ISSUE_DELETED,
        confirmDeletedIssue,
      )
      SegmentStore.removeListener(
        SegmentConstants.OPEN_ISSUE_COMMENT,
        openCommentsAfterCreation,
      )
    }
  }, [issue.id, issue.id_segment, issue.target_text, sid])

  const view = () => {
    const category = getCategory()
    const severity = getSeverity()

    //START comments html section
    let htmlCommentLines = generateHtmlCommentLines()

    let renderHtmlCommentLines = ''
    if (htmlCommentLines.length > 0 || issue.target_text) {
      renderHtmlCommentLines = (
        <div className="re-comment-list">
          {issue.target_text && (
            <div className="re-highlighted">
              <span className="re-selected-text">
                <b>Selected text:</b>
              </span>
              {issue.target_text}
            </div>
          )}
          {htmlCommentLines}
        </div>
      )
    }

    let containerClass = classNames({
      're-item': true,
      'issue-comments-open': commentView || issueEditing,
    })

    let commentSection = (
      <div className="comments-view shadow-1">
        {renderHtmlCommentLines}
        <div className="re-add-comment">
          <form className="ui form" onSubmit={addComment}>
            <div className="field">
              <input
                className="re-comment-input"
                autoComplete="off"
                value={commentText}
                type="text"
                name="first-name"
                placeholder="Add a comment + press Enter"
                onChange={handleCommentChange}
              />
            </div>
          </form>
        </div>
      </div>
    )

    return (
      <div className={containerClass} ref={containerRef}>
        <div
          className={`re-item-box re-issue shadow-1 re-item-issue-value ${issueEditing?.id === issue.id || commentView ? 'editing-highlight' : ''}`}
        >
          <div className="issue-head pad-right-10">
            <span className="re-category-issue-head" title={category.label}>
              {category.label}
            </span>
            <b>
              <span title={severity.label}>
                [
                {severity.code ? severity.code : severity.label.substring(0, 3)}
                ]
              </span>
            </b>
          </div>
          <div className="issue-activity-icon">
            {actions && (
              <div className="icon-buttons">
                <Button
                  size={BUTTON_SIZE.ICON_MEDIUM}
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={setCommentViewCallback}
                  title="Comments"
                >
                  {issue.comments.length > 0 || issue.target_text ? (
                    <CommentsSquareIconFilled size={18} />
                  ) : (
                    <CommentsSquareIcon size={18} />
                  )}
                </Button>
                {isReview &&
                  issue.revision_number <= currentReview &&
                  isUserAuthorizedToEditIssue && (
                    <>
                      <Button
                        size={BUTTON_SIZE.ICON_MEDIUM}
                        mode={BUTTON_MODE.OUTLINE}
                        onClick={editIssue}
                        title="Edit issue card"
                        active={issueEditing?.id === issue.id}
                      >
                        <IconEdit size={18} />
                      </Button>
                      <Button
                        size={BUTTON_SIZE.ICON_MEDIUM}
                        mode={BUTTON_MODE.OUTLINE}
                        onClick={deleteIssue}
                        title="Delete issue card"
                      >
                        <Trash size={18} />
                      </Button>
                    </>
                  )}
              </div>
            )}
          </div>
        </div>

        {commentView && commentSection}
        {issueEditing && issueEditing.id === issue.id && (
          <div className="issue-panel-edit-mode">
            <ReviewExtendedIssuePanel
              selection={selectionObj}
              segmentVersion={versionNumber}
              submitIssueCallback={() => false}
              setCreationIssueLoader={() => false}
              issueEditing={issueEditing}
              setIssueEditing={setIssueEditing}
            />
          </div>
        )}
      </div>
    )
  }

  return visible && view()
}
