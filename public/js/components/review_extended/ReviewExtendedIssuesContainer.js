import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import WrapperLoader from '../common/WrapperLoader'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentActions from '../../actions/SegmentActions'
import SegmentStore from '../../stores/SegmentStore'
import SegmentUtils from '../../utils/segmentUtils'
import {SegmentContext} from '../segments/SegmentContext'
import CatToolActions from '../../actions/CatToolActions'
import {forEach, filter, isUndefined} from 'lodash'
import {isNull} from 'lodash/lang'
import {each} from 'lodash/collection'
import {findIndex} from 'lodash/array'
import classnames from 'classnames'
import ReviewExtendedIssuesTabGroup from './ReviewExtendedIssuesTabGroup'
import {ReviewExtendedIssue} from './ReviewExtendedIssue'

const ReviewExtendedIssuesContainer = (props) => {
  const context = useContext(SegmentContext)

  const issueFlatCategoriesRef = useRef(config.lqa_flat_categories)
  const issueNestedCategoriesRef = useRef(
    config.lqa_nested_categories.categories,
  )
  const is2ndPassReviewEnabledRef = useRef(
    config.secondRevisionsCount && config.secondRevisionsCount > 0,
  )
  const reviewTypeRef = useRef(config.revisionNumber)

  const issueFlatCategories = issueFlatCategoriesRef.current
  const issueNestedCategories = issueNestedCategoriesRef.current
  const is2ndPassReviewEnabled = is2ndPassReviewEnabledRef.current
  const reviewType = reviewTypeRef.current

  // eslint-disable-next-line no-unused-vars
  const [lastIssueAdded] = useState(null)
  const [visible, setVisible] = useState(true)

  const isFirstRender = useRef(true)
  const prevIssuesLengthRef = useRef(props.issues.length)

  const latestRef = useRef({context})
  latestRef.current = {context}

  const findCategory = (id) => {
    return issueFlatCategories.find((category) => {
      return id == category.id
    })
  }

  const isSubCategory = (category) => {
    return !isNull(category.id_parent)
  }

  const parseIssues = () => {
    let issuesObj = {}
    props.issues.forEach((issue) => {
      let cat = findCategory(issue.id_category)
      let id = isSubCategory(cat) ? cat.id_parent : cat.id

      if (!issuesObj[id]) {
        issuesObj[id] = []
      }
      issuesObj[id].push(issue)
    })
    return issuesObj
  }

  const thereAreSubcategories = () => {
    return (
      issueNestedCategories[0].subcategories &&
      issueNestedCategories[0].subcategories.length > 0
    )
  }

  const changeVisibility = (id, isVisible) => {
    let issuesList = props.issues.slice()
    let index = findIndex(issuesList, function (item) {
      return item.id == id
    })
    issuesList[index].visible = isVisible

    let visibleIssues = filter(props.issues, function (item) {
      return isUndefined(item.visible) || item.visible
    })
    if (visibleIssues.length === 0) {
      setVisible(false)
    } else {
      setVisible(true)
    }
  }

  const getIssuesSortedComponentList = (list) => {
    let issuesR1 = [],
      issuesR2 = []
    let sorted_issues = list.sort(function (a, b) {
      a = new Date(a.created_at)
      b = new Date(b.created_at)
      return a > b ? -1 : a < b ? 1 : 0
    })

    forEach(sorted_issues, (item) => {
      if (item.revision_number === 2) {
        issuesR2.push(
          <ReviewExtendedIssue
            lastIssueId={lastIssueAdded}
            sid={context.segment.sid}
            isReview={props.isReview}
            currentReview={reviewType}
            issue={item}
            key={item.id}
            changeVisibility={changeVisibility}
            actions={
              !SegmentUtils.isIceSegment(context.segment) ||
              (SegmentUtils.isIceSegment(context.segment) &&
                context.segment.unlocked)
            }
            issueEditing={props.issueEditing}
            setIssueEditing={props.setIssueEditing}
            selection={props.selectionObj}
            segmentVersion={props.versionNumber}
          />,
        )
      } else {
        issuesR1.push(
          <ReviewExtendedIssue
            lastIssueId={lastIssueAdded}
            sid={context.segment.sid}
            isReview={props.isReview}
            currentReview={reviewType}
            issue={item}
            key={item.id}
            changeVisibility={changeVisibility}
            actions={
              !SegmentUtils.isIceSegment(context.segment) ||
              (SegmentUtils.isIceSegment(context.segment) &&
                context.segment.unlocked)
            }
            issueEditing={props.issueEditing}
            setIssueEditing={props.setIssueEditing}
            selection={props.selectionObj}
            segmentVersion={props.versionNumber}
          />,
        )
      }
    })

    return {r1: issuesR1, r2: issuesR2}
  }

  const getSubCategoriesHtml = () => {
    let parsedIssues = parseIssues()
    let htmlR1 = [],
      htmlR2 = []
    each(parsedIssues, (issuesList, id) => {
      let cat = findCategory(id)
      let issues = getIssuesSortedComponentList(issuesList)
      if (issues.r1.length > 0) {
        htmlR1.push(
          <div key={cat.id}>
            <div className="re-item-head pad-left-5">{cat.label}</div>
            {issues.r1}
          </div>,
        )
      }
      if (issues.r2.length > 0) {
        htmlR2.push(
          <div key={cat.id}>
            <div className="re-item-head pad-left-5">{cat.label}</div>
            {issues.r2}
          </div>,
        )
      }
    })
    if (is2ndPassReviewEnabled) {
      let r1Active =
        (reviewType === 1 && htmlR1.length > 0) ||
        (htmlR1.length > 0 && htmlR2.length === 0)
      let r2Active =
        (reviewType === 2 && htmlR2.length > 0) ||
        (htmlR2.length > 0 && htmlR1.length === 0)

      const maxHeight = props.issueEditing ? '500px' : '200px'

      const tabs = [
        {
          id: 'r1',
          label: 'R1 issues',
          disabled: htmlR1.length === 0,
          content: (
            <div
              className={classnames(
                r1Active && 'active',
                htmlR1.length === 0 && 'disabled',
              )}
              style={{
                padding: '0px',
                width: '99.5%',
                maxHeight,
                overflowY: 'auto',
                marginBottom: 'unset',
              }}
            >
              {htmlR1}
            </div>
          ),
        },
        {
          id: 'r2',
          label: 'R2 issues',
          disabled: htmlR2.length === 0,
          content: (
            <div
              className={classnames(
                r2Active && 'active',
                htmlR2.length === 0 && 'disabled',
              )}
              style={{
                padding: '0px',
                width: '99.5%',
                maxHeight,
                overflowY: 'auto',
                margingBottom: 'unset',
              }}
            >
              {htmlR2}
            </div>
          ),
        },
      ]

      return (
        <ReviewExtendedIssuesTabGroup
          {...{tabs, selectedTabId: r1Active ? 'r1' : 'r2'}}
        />
      )
    } else {
      return htmlR1
    }
  }

  const getCategoriesHtml = () => {
    let issues

    if (props.issues.length > 0) {
      issues = getIssuesSortedComponentList(props.issues)
    }
    if (is2ndPassReviewEnabled) {
      let r1Active =
        (reviewType === 1 && issues.r1.length > 0) ||
        (issues.r1.length > 0 && issues.r2.length === 0)
      let r2Active =
        (reviewType === 2 && issues.r2.length > 0) ||
        (issues.r2.length > 0 && issues.r1.length === 0)

      const maxHeight = props.issueEditing ? '500px' : '200px'

      const tabs = [
        {
          id: 'r1',
          label: 'R1 issues',
          disabled: issues.r1.length === 0,
          content: (
            <div
              className={classnames(
                r1Active && 'active',
                issues.r1.length === 0 && 'disabled',
              )}
              style={{
                padding: '0px',
                width: '99.5%',
                maxHeight,
                overflowY: 'auto',
                marginBottom: 'unset',
              }}
            >
              {issues.r1}
            </div>
          ),
        },
        {
          id: 'r2',
          label: 'R2 issues',
          disabled: issues.r2.length === 0,
          content: (
            <div
              className={classnames(
                r2Active && 'active',
                issues.r2.length === 0 && 'disabled',
              )}
              style={{
                padding: '0px',
                width: '99.5%',
                maxHeight,
                overflowY: 'auto',
                margingBottom: 'unset',
              }}
            >
              {issues.r2}
            </div>
          ),
        },
      ]

      return (
        <ReviewExtendedIssuesTabGroup
          {...{tabs, selectedTabId: r1Active ? 'r1' : 'r2'}}
        />
      )
    } else {
      return (
        <div>
          <div className="re-item-head pad-left-1">Issues</div>
          {issues.r1}
        </div>
      )
    }
  }

  const setLastIssueAdded = useCallback((sid, id) => {
    const {context} = latestRef.current
    if (sid === context.segment.sid) {
      setTimeout(() => {
        SegmentActions.openIssueComments(context.segment.sid, id)
      }, 200)
    }
  }, [])

  useEffect(() => {
    SegmentStore.addListener(SegmentConstants.ISSUE_ADDED, setLastIssueAdded)
    return () => {
      SegmentStore.removeListener(
        SegmentConstants.ISSUE_ADDED,
        setLastIssueAdded,
      )
      //Undo notification
      setTimeout(() => CatToolActions.removeAllNotifications())
    }
  }, [setLastIssueAdded])

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
    } else if (prevIssuesLengthRef.current < props.issues.length) {
      setVisible(true)
    }
    prevIssuesLengthRef.current = props.issues.length
  }, [props.issues.length])

  if (props.issues.length > 0) {
    let html
    if (thereAreSubcategories()) {
      html = getSubCategoriesHtml()
    } else {
      html = getCategoriesHtml()
    }
    let classNotVisible = !visible ? 're-issues-box-empty' : ''
    return (
      <div className={'re-issues-box re-created ' + classNotVisible}>
        {props.loader ? <WrapperLoader /> : null}
        <div
          className={classnames(
            're-list issues',
            is2ndPassReviewEnabled && 'no-scroll',
          )}
        >
          {html}
        </div>
      </div>
    )
  }
  return ''
}

export default ReviewExtendedIssuesContainer
