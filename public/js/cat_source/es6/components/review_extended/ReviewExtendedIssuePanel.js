import React from 'react'
import ReviewExtendedCategorySelector from './ReviewExtendedCategorySelector'
import CommonUtils from '../../utils/commonUtils'
import SegmentActions from '../../actions/SegmentActions'
import {setTranslation} from '../../api/setTranslation'
import {orderBy} from 'lodash'
import {SegmentContext} from '../segments/SegmentContext'
import {
  JOB_WORD_CONT_TYPE,
  REVISE_STEP_NUMBER,
  SEGMENTS_STATUS,
} from '../../constants/Constants'
import SegmentUtils from '../../utils/segmentUtils'

class ReviewExtendedIssuePanel extends React.Component {
  static contextType = SegmentContext

  constructor(props) {
    super(props)
    this.issueCategoriesFlat = JSON.parse(config.lqa_flat_categories)
    this.state = {
      submitDisabled: false,
      categorySelectedIndex: 0,
      categorySelectedId: this.issueCategoriesFlat[0].id,
      enableArrows: false,
      severityIndex: 0,
    }
    this.issueCategories = orderBy(
      JSON.parse(config.lqa_nested_categories).categories,
      ['id'],
    )

    this.handleShortcutsKeyDown = this.handleShortcutsKeyDown.bind(this)
    this.handleShortcutsKeyUp = this.handleShortcutsKeyUp.bind(this)
  }
  sendIssue(category, severity) {
    if (this.state.submitDisabled) return
    this.props.setCreationIssueLoader(true)
    this.setState({
      submitDisabled: true,
    })

    const {selection} = this.props

    const issue = {
      id_category: category.id,
      severity: severity,
      version: this.props.segmentVersion,
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
      SegmentActions.submitIssue(this.context.segment.sid, issue)
        .then((data) => {
          this.setState({
            submitDisabled: false,
          })
          this.props.submitIssueCallback()
          this.props.setCreationIssueLoader(false)
          setTimeout(() => {
            SegmentActions.issueAdded(this.context.segment.sid, data.issue.id)
          })
        })
        .catch(this.handleFail.bind(this))
    }

    const segment = this.context.segment
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
        .catch(this.handleFail.bind(this))
    } else {
      deferredSubmit()
    }
  }

  handleFail({errors}) {
    if (errors && errors[0].code === -2000) {
      UI.processErrors(errors, 'createIssue')
    } else {
      CommonUtils.genericErrorAlertMessage()
    }
    this.props.setCreationIssueLoader(false)
    this.props.handleFail()
    this.setState({submitDone: false, submitDisabled: false})
  }

  thereAreSubcategories() {
    return (
      (this.issueCategories[0].subcategories &&
        this.issueCategories[0].subcategories.length > 0) ||
      (this.issueCategories[1].subcategories &&
        this.issueCategories[1].subcategories.length > 0)
    )
  }

  getCategoriesHtml() {
    let categoryComponents = []
    this.issueCategories.forEach(
      function (category, i) {
        let selectedValue = ''

        categoryComponents.push(
          <ReviewExtendedCategorySelector
            key={'category-selector-' + i}
            sendIssue={this.sendIssue.bind(this)}
            selectedValue={selectedValue}
            nested={false}
            category={category}
            sid={this.context.segment.sid}
            active={
              this.state.enableArrows &&
              parseInt(this.state.categorySelectedId) === parseInt(category.id)
            }
            severityActiveIndex={
              this.state.enableArrows &&
              parseInt(this.state.categorySelectedId) === parseInt(category.id)
                ? this.state.severityIndex
                : null
            }
          />,
        )
      }.bind(this),
    )

    return (
      <div>
        <div className="re-item-head pad-left-10">Type of issue</div>
        {categoryComponents}
      </div>
    )
  }

  getSubCategoriesHtml() {
    let categoryComponents = []
    this.issueCategories.forEach(
      function (category, i) {
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
                sendIssue={this.sendIssue.bind(this)}
                nested={true}
                category={category}
                sid={this.context.segment.sid}
                active={
                  this.state.enableArrows &&
                  parseInt(this.state.categorySelectedId) ===
                    parseInt(category.id)
                }
                severityActiveIndex={
                  this.state.enableArrows &&
                  parseInt(this.state.categorySelectedId) ===
                    parseInt(category.id)
                    ? this.state.severityIndex
                    : null
                }
              />,
            )
          })
        } else {
          subcategoriesComponents.push(
            <ReviewExtendedCategorySelector
              key={'default'}
              selectedValue={selectedValue}
              sendIssue={this.sendIssue.bind(this)}
              nested={true}
              category={category}
              sid={this.context.segment.sid}
              active={
                this.state.enableArrows &&
                parseInt(this.state.categorySelectedId) ===
                  parseInt(category.id)
              }
              severityActiveIndex={
                this.state.enableArrows &&
                parseInt(this.state.categorySelectedId) ===
                  parseInt(category.id)
                  ? this.state.severityIndex
                  : null
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
      }.bind(this),
    )

    return categoryComponents
  }
  getNextCategoryIndex(direction) {
    let idx = this.state.categorySelectedIndex
    let length = this.issueCategoriesFlat.length
    switch (direction) {
      case 'next':
        return (idx + 1) % length
      case 'prev':
        return (idx === 0 && length - 1) || idx - 1
      default:
        return idx
    }
  }
  getNextSeverityIndex(direction) {
    let idx = this.state.severityIndex
    let length =
      this.issueCategoriesFlat[this.state.categorySelectedIndex].severities
        .length
    switch (direction) {
      case 'next':
        return (idx + 1) % length
      case 'prev':
        return (idx === 0 && length - 1) || idx - 1
      default:
        return idx
    }
  }
  handleShortcutsKeyDown(e) {
    if (e.ctrlKey && e.altKey && !this.state.enableArrows) {
      this.setState({
        enableArrows: true,
      })
    }
    if (this.state.enableArrows && e.code === 'ArrowDown') {
      let index = this.getNextCategoryIndex('next')
      this.setState({
        categorySelectedIndex: index,
        categorySelectedId: this.issueCategoriesFlat[index].id,
        severityIndex: 0,
      })
    } else if (this.state.enableArrows && e.code === 'ArrowUp') {
      let index = this.getNextCategoryIndex('prev')
      this.setState({
        categorySelectedIndex: index,
        categorySelectedId: this.issueCategoriesFlat[index].id,
        severityIndex: 0,
      })
    } else if (this.state.enableArrows && e.code === 'ArrowLeft') {
      let index = this.getNextSeverityIndex('prev')
      this.setState({
        severityIndex: index,
      })
    } else if (this.state.enableArrows && e.code === 'ArrowRight') {
      let index = this.getNextSeverityIndex('next')
      this.setState({
        severityIndex: index,
      })
    } else if (this.state.enableArrows && e.code === 'Enter') {
      this.sendIssue(
        this.issueCategoriesFlat[this.state.categorySelectedIndex],
        this.issueCategoriesFlat[this.state.categorySelectedIndex].severities[
          this.state.severityIndex
        ].label,
      )
      setTimeout(() => SegmentActions.setFocusOnEditArea(), 1000)
    }
  }

  handleShortcutsKeyUp(e) {
    if ((!e.ctrlKey || !e.altKey) && this.state.enableArrows) {
      this.setState({
        enableArrows: false,
        categorySelectedIndex: 0,
        categorySelectedId: this.issueCategoriesFlat[0].id,
        severityIndex: 0,
      })
    }
  }

  componentDidMount() {
    document.addEventListener('keydown', this.handleShortcutsKeyDown)
    document.addEventListener('keyup', this.handleShortcutsKeyUp)
  }

  componentWillUnmount() {
    document.removeEventListener('keyup', this.handleShortcutsKeyUp)
    document.removeEventListener('keydown', this.handleShortcutsKeyDown)
  }

  render() {
    let html = []

    if (this.thereAreSubcategories()) {
      html = this.getSubCategoriesHtml()
    } else {
      html = this.getCategoriesHtml()
    }

    return (
      <div className="re-issues-box re-to-create">
        {/*<h4 className="re-issues-box-title">Error list</h4>*/}
        {/*<div className="mbc-triangle mbc-triangle-topleft"></div>*/}
        <div
          className="re-list errors"
          id={'re-category-list-' + this.context.segment.sid}
          ref={(node) => (this.listElm = node)}
        >
          {html}
        </div>
      </div>
    )
  }
}

ReviewExtendedIssuePanel.defaultProps = {
  handleFail: function () {},
}

export default ReviewExtendedIssuePanel
