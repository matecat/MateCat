import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import ReviewExtendedCategorySelector from './ReviewExtendedCategorySelector'
import CommonUtils from '../../utils/commonUtils'
import SegmentActions from '../../actions/SegmentActions'
import {setTranslation} from '../../api/setTranslation'
import {orderBy} from 'lodash'
import {SegmentContext} from '../segments/SegmentContext'
import {REVISE_STEP_NUMBER, SEGMENTS_STATUS} from '../../constants/Constants'
import SegmentUtils from '../../utils/segmentUtils'
import CatToolActions from '../../actions/CatToolActions'
import {editSegmentIssue} from '../../api/editSegmentIssue/editSegmentIssue'
import {sendSegmentVersionIssue} from '../../api/sendSegmentVersionIssue'

const getNextCategoryIndex = (direction, currentIndex, length) => {
  switch (direction) {
    case 'next':
      return (currentIndex + 1) % length
    case 'prev':
      return (currentIndex === 0 && length - 1) || currentIndex - 1
    default:
      return currentIndex
  }
}

const getNextSeverityIndex = (direction, currentIndex, length) => {
  switch (direction) {
    case 'next':
      return (currentIndex + 1) % length
    case 'prev':
      return (currentIndex === 0 && length - 1) || currentIndex - 1
    default:
      return currentIndex
  }
}

const ReviewExtendedIssuePanel = ({
  setCreationIssueLoader,
  selection,
  segmentVersion,
  issueEditing,
  submitIssueCallback,
  setIssueEditing,
  handleFail = () => {},
}) => {
  const context = useContext(SegmentContext)

  const issueCategoriesFlatRef = useRef(config.lqa_flat_categories)
  const issueCategoriesRef = useRef(
    orderBy(config.lqa_nested_categories.categories, ['id']),
  )
  const issueCategoriesFlat = issueCategoriesFlatRef.current
  const issueCategories = issueCategoriesRef.current

  const listElmRef = useRef(null)
  const latestRef = useRef({})

  const [submitDisabled, setSubmitDisabled] = useState(false)
  // eslint-disable-next-line no-unused-vars
  const [submitDone, setSubmitDone] = useState()
  const [categorySelected, setCategorySelected] = useState({
    index: 0,
    id: issueCategoriesFlat[0].id,
  })
  const [severityIndex, setSeverityIndex] = useState(0)
  const [enableArrows, setEnableArrows] = useState(false)

  const handleSendIssueFail = (response) => {
    if (response.errors && response.errors[0].code === -2000) {
      CatToolActions.processErrors(response.errors, 'createIssue')
    } else {
      CommonUtils.genericErrorAlertMessage()
    }
    setCreationIssueLoader(false)
    handleFail()
    setSubmitDone(false)
    setSubmitDisabled(false)
  }

  const sendIssue = (category, severity) => {
    if (submitDisabled) return
    setCreationIssueLoader(true)
    setSubmitDisabled(true)

    const issue = {
      id_category: category.id,
      severity: severity,
      version: segmentVersion,
      ...(selection
        ? {
            target_text: selection.selected_string,
            start_node: selection.start_node,
            start_offset: selection.start_offset,
            send_node: selection.end_node,
            end_offset: selection.end_offset,
          }
        : {
            start_node: 0,
            start_offset: 0,
            send_node: 0,
            end_offset: 0,
          }),
    }

    const deferredSubmit = () => {
      SegmentActions.setStatus(segment.sid, segment.fid, segment.status)

      const promise = issueEditing ? editSegmentIssue : sendSegmentVersionIssue

      promise({
        idSegment: context.segment.sid,
        issueDetails: issue,
        issueId: issueEditing?.id,
      })
        .then((data) => {
          SegmentActions.getSegmentVersionsIssues(context.segment.sid)
          CatToolActions.reloadQualityReport()

          setSubmitDisabled(false)
          submitIssueCallback()
          setCreationIssueLoader(false)
          if (issueEditing) setIssueEditing(undefined)
          setTimeout(() => {
            SegmentActions.issueAdded(context.segment.sid, data.issue.id)
          })
        })
        .catch(handleSendIssueFail)
    }

    const segment = context.segment
    if (
      segment.revision_number !== config.revisionNumber ||
      ![SEGMENTS_STATUS.APPROVED, SEGMENTS_STATUS.APPROVED2].includes(
        segment.status.toUpperCase(),
      )
    ) {
      segment.status =
        config.revisionNumber === REVISE_STEP_NUMBER.REVISE1
          ? SEGMENTS_STATUS.APPROVED
          : SEGMENTS_STATUS.APPROVED2
      const requestObject = SegmentUtils.createSetTranslationRequest(segment)
      setTranslation(requestObject)
        .then((response) => {
          issue.version = response.translation.version_number
          SegmentActions.setStatus(segment.sid, segment.id_file, segment.status)
          SegmentActions.addClassToSegment(segment.sid, 'modified')
          deferredSubmit()
        })
        .catch(handleSendIssueFail)
    } else {
      deferredSubmit()
    }
  }

  const thereAreSubcategories = () =>
    (issueCategories[0]?.subcategories &&
      issueCategories[0].subcategories.length > 0) ||
    (issueCategories[1]?.subcategories &&
      issueCategories[1].subcategories.length > 0)

  const getCategoriesHtml = () => {
    let categoryComponents = []
    issueCategories.forEach((category, i) => {
      let selectedValue = ''
      categoryComponents.push(
        <ReviewExtendedCategorySelector
          key={'category-selector-' + i}
          sendIssue={sendIssue}
          selectedValue={selectedValue}
          nested={false}
          category={category}
          sid={context.segment.sid}
          active={
            (enableArrows &&
              parseInt(categorySelected.id) === parseInt(category.id)) ||
            (issueEditing &&
              parseInt(issueEditing.id_category) === parseInt(category.id))
          }
          severityActiveIndex={
            (enableArrows &&
            parseInt(categorySelected.id) === parseInt(category.id)
              ? severityIndex
              : null) ||
            (issueEditing &&
            parseInt(issueEditing.id_category) === parseInt(category.id)
              ? category.severities.findIndex(
                  ({label}) => label === issueEditing.severity,
                )
              : null)
          }
        />,
      )
    })

    return (
      <div>
        {!issueEditing && (
          <div className="re-item-head pad-left-10">New issue</div>
        )}
        {categoryComponents}
      </div>
    )
  }

  const getSubCategoriesHtml = () => {
    let categoryComponents = []
    issueCategories.forEach((category, i) => {
      let selectedValue = ''
      let subcategoriesComponents = []

      if (category.subcategories.length > 0) {
        category.subcategories.forEach((category, ii) => {
          let key = '' + i + '-' + ii
          let kk = 'category-selector-' + key
          let selectedValue = ''

          subcategoriesComponents.push(
            <ReviewExtendedCategorySelector
              key={kk}
              selectedValue={selectedValue}
              sendIssue={sendIssue}
              nested={true}
              category={category}
              sid={context.segment.sid}
              active={
                (enableArrows &&
                  parseInt(categorySelected.id) === parseInt(category.id)) ||
                (issueEditing &&
                  parseInt(issueEditing.id_category) === parseInt(category.id))
              }
              severityActiveIndex={
                (enableArrows &&
                parseInt(categorySelected.id) === parseInt(category.id)
                  ? severityIndex
                  : null) ||
                (issueEditing &&
                parseInt(issueEditing.id_category) === parseInt(category.id)
                  ? category.severities.findIndex(
                      ({label}) => label === issueEditing.severity,
                    )
                  : null)
              }
            />,
          )
        })
      } else {
        subcategoriesComponents.push(
          <ReviewExtendedCategorySelector
            key={'default'}
            selectedValue={selectedValue}
            sendIssue={sendIssue}
            nested={true}
            category={category}
            sid={context.segment.sid}
            active={
              (enableArrows &&
                parseInt(categorySelected.id) === parseInt(category.id)) ||
              (issueEditing &&
                parseInt(issueEditing.id_category) === parseInt(category.id))
            }
            severityActiveIndex={
              (enableArrows &&
              parseInt(categorySelected.id) === parseInt(category.id)
                ? severityIndex
                : null) ||
              (issueEditing &&
              parseInt(issueEditing.id_category) === parseInt(category.id)
                ? category.severities.findIndex(
                    ({label}) => label === issueEditing.severity,
                  )
                : null)
            }
          />,
        )
      }
      let html = (
        <div key={category.id}>
          <div className="re-item-head pad-left-10">{category.label}</div>
          {subcategoriesComponents}
        </div>
      )
      categoryComponents.push(html)
    })

    return categoryComponents
  }

  latestRef.current = {
    enableArrows,
    categorySelected,
    severityIndex,
    sendIssue,
  }

  const handleShortcutsKeyDown = useCallback((e) => {
    const {enableArrows, categorySelected, severityIndex, sendIssue} =
      latestRef.current
    const issueCategoriesFlat = issueCategoriesFlatRef.current

    if (e.ctrlKey && e.altKey && !enableArrows) {
      setEnableArrows(true)
    }
    if (enableArrows && e.code === 'ArrowDown') {
      const index = getNextCategoryIndex(
        'next',
        categorySelected.index,
        issueCategoriesFlat.length,
      )
      setCategorySelected({index, id: issueCategoriesFlat[index].id})
      setSeverityIndex(0)
    } else if (enableArrows && e.code === 'ArrowUp') {
      const index = getNextCategoryIndex(
        'prev',
        categorySelected.index,
        issueCategoriesFlat.length,
      )
      setCategorySelected({index, id: issueCategoriesFlat[index].id})
      setSeverityIndex(0)
    } else if (enableArrows && e.code === 'ArrowLeft') {
      const length =
        issueCategoriesFlat[categorySelected.index].severities.length
      setSeverityIndex(getNextSeverityIndex('prev', severityIndex, length))
    } else if (enableArrows && e.code === 'ArrowRight') {
      const length =
        issueCategoriesFlat[categorySelected.index].severities.length
      setSeverityIndex(getNextSeverityIndex('next', severityIndex, length))
    } else if (enableArrows && e.code === 'Enter') {
      sendIssue(
        issueCategoriesFlat[categorySelected.index],
        issueCategoriesFlat[categorySelected.index].severities[severityIndex]
          .label,
      )
      setTimeout(() => SegmentActions.setFocusOnEditArea(), 1000)
    }
  }, [])

  const handleShortcutsKeyUp = useCallback((e) => {
    const {enableArrows} = latestRef.current
    if ((!e.ctrlKey || !e.altKey) && enableArrows) {
      setEnableArrows(false)
      setCategorySelected({
        index: 0,
        id: issueCategoriesFlatRef.current[0].id,
      })
      setSeverityIndex(0)
    }
  }, [])

  useEffect(() => {
    document.addEventListener('keydown', handleShortcutsKeyDown)
    document.addEventListener('keyup', handleShortcutsKeyUp)
    return () => {
      document.removeEventListener('keyup', handleShortcutsKeyUp)
      document.removeEventListener('keydown', handleShortcutsKeyDown)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  let html = []

  if (thereAreSubcategories()) {
    html = getSubCategoriesHtml()
  } else {
    html = getCategoriesHtml()
  }

  return (
    <div className="re-issues-box re-to-create">
      {/*<h4 className="re-issues-box-title">Error list</h4>*/}
      {/*<div className="comment-triangle comment-triangle-topleft"></div>*/}
      <div
        className="re-list errors"
        id={'re-category-list-' + context.segment.sid}
        ref={listElmRef}
      >
        {html}
      </div>
    </div>
  )
}

export default ReviewExtendedIssuePanel
