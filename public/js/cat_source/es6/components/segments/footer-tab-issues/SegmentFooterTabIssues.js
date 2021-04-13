import React from 'react'
import SegmentConstants from '../../../constants/SegmentConstants'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentFooterTabIssuesListItem from './SegmentFooterTabIssuesListItem'
import TextUtils from '../../../utils/textUtils'

class SegmentFooterTabIssues extends React.Component {
  constructor(props) {
    super(props)

    let categories = JSON.parse(config.lqa_nested_categories).categories

    this.state = {
      categorySelected: null,
      categoriesIssue: categories,
      segment: this.props.segment,
      translation: this.props.segment.translation,
      oldTranslation: this.props.segment.translation,
      isChangedTextarea: false,
      issues: [],
    }
  }

  componentDidMount() {
    $(this.selectIssueCategory).dropdown()
    $(this.selectIssueSeverity).dropdown()

    SegmentStore.addListener(
      SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES,
      this.segmentOpened.bind(this),
    )
    // SegmentStore.addListener( SegmentConstants.TRANSLATION_EDITED, this.trackChanges.bind( this ) );
  }

  componentDidUpdate() {
    $(this.selectIssueSeverity).dropdown()
    if (this.state.categorySelected) {
      $(this.selectIssueCategoryWrapper)
        .find('.ui.dropdown')
        .removeClass('disabled')
    } else {
      $(this.selectIssueCategoryWrapper)
        .find('.ui.dropdown')
        .addClass('disabled')
    }
  }

  componentWillUnmount() {
    SegmentStore.removeListener(
      SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES,
      this.segmentOpened,
    )
    // SegmentStore.removeListener( SegmentConstants.TRANSLATION_EDITED, this.trackChanges );
  }

  // trackChanges(sid, editareaText) {
  //     let text = TextUtils.htmlEncode(TagUtils.prepareTextToSend(editareaText));
  //     if (this.state.segment.sid === sid && this.state.oldTranslation !== text) {
  //         UI.setDisabledOfButtonApproved(this.props.sid, true);
  //         this.setState({
  //             translation: text,
  //             isChangedTextarea: true,
  //         });
  //     } else {
  //         UI.setDisabledOfButtonApproved(this.props.sid);
  //         this.setState({
  //             isChangedTextarea: false,
  //         });
  //     }
  // }

  segmentOpened(sid, segment) {
    let issues = []
    segment.versions.forEach(function (version) {
      if (!_.isEmpty(version.issues)) {
        issues = issues.concat(version.issues)
      }
    })
    this.setState({
      segment: segment,
      issues: issues,
    })
    let self = this
    setTimeout(function () {
      // SegmentActions.setTabIndex(self.state.segment.sid, 'issues', issues.length);
    })
  }

  allowHTML(string) {
    return {__html: string}
  }

  sendIssue(category, severity) {
    let data = []
    let deferred = $.Deferred()
    let self = this,
      oldTranslation = this.state.oldTranslation

    let issue = {
      id_category: category.id,
      severity: severity,
      version: this.props.segment.version_number,
      start_node: 0,
      start_offset: 0,
      send_node: 0,
      end_offset: 0,
    }

    if (this.state.isChangedTextarea) {
      let segment = this.props.segment
      segment.translation = this.state.translation
      segment.status = 'approved'
      API.SEGMENT.setTranslation(segment)
        .done(function (response) {
          issue.version = response.translation.version
          oldTranslation = response.translation.translation
          deferred.resolve()
        })
        .fail(/*self.handleFail.bind(self)*/)
    } else {
      deferred.resolve()
    }

    data.push(issue)

    deferred.then(function () {
      SegmentActions.removeClassToSegment(self.props.sid, 'modified')
      UI.currentSegment.data('modified', false)
      SegmentActions.submitIssue(self.props.sid, data, [])
        .done((response) => {
          self.setState({
            isChangedTextarea: false,
            oldTranslation: oldTranslation,
            categorySelected: null,
          })
          $(self.selectIssueCategory).dropdown('restore defaults')
          $(self.selectIssueSeverity).dropdown('set selected', -1)
          UI.setDisabledOfButtonApproved(self.props.sid)
        })
        .fail(/* self.handleFail.bind(self)*/)
    })
  }

  issueCategories() {
    return JSON.parse(config.lqa_flat_categories).categories
  }

  categoryOptionChange(item) {
    let currentCategory = item
    this.setState({
      categorySelected: currentCategory ? currentCategory : null,
    })
  }

  severityOptionChange(e) {
    let selectedSeverity = e.target.value
    if (selectedSeverity != -1) {
      this.sendIssue(this.state.categorySelected, selectedSeverity)
    }
  }

  findCategory(id) {
    return this.state.categoriesIssue.find((category) => {
      return id == category.id
    })
  }

  getCategoryDropdown() {
    let categoryOptions = [],
      categoryOption,
      self = this
    this.state.categoriesIssue.forEach(function (category, i) {
      if (category.subcategories && category.get('subcategories').length > 0) {
        let subCategories = category.get('subcategories').map(function (item) {
          return (
            <div
              key={item.id}
              className="item"
              data-value={item.id}
              onClick={self.categoryOptionChange.bind(self, item)}
            >
              {item.label}
            </div>
          )
        })
        categoryOption = (
          <div className="item" data-value={category.id} key={i}>
            <div className="text">
              {category.label} <i className="icon-chevron-right icon" />
            </div>

            <div className="menu">{subCategories}</div>
          </div>
        )
      } else {
        categoryOption = (
          <div
            className="item"
            data-value={category.id}
            key={i}
            onClick={self.categoryOptionChange.bind(self, category)}
          >
            {category.label}
          </div>
        )
      }
      categoryOptions.push(categoryOption)
    })
    return (
      <div
        className="ui fluid dropdown category"
        ref={(input) => {
          this.selectIssueCategory = input
        }}
      >
        <div className="text ellipsis-messages">Select issue</div>
        <i className="icon-sort-down" />
        <div className="right menu sub-category">{categoryOptions}</div>
      </div>
    )
  }

  render() {
    let categoryOptions = [],
      categorySeverities = [],
      severityOption,
      issues = [],
      severitySelect,
      issue,
      self = this

    if (this.state.categorySelected) {
      this.state.categorySelected.get('severities').forEach((severity, i) => {
        severityOption = (
          <option value={severity.label} key={i}>
            {severity.label}
          </option>
        )
        categorySeverities.push(severityOption)
      })
    }
    severitySelect = (
      <select
        className="ui fluid dropdown severity"
        ref={(input) => {
          this.selectIssueSeverity = input
        }}
        onChange={(e) => this.severityOptionChange(e)}
        disabled={!this.state.categorySelected}
      >
        <option value="-1">Select severity</option>
        {categorySeverities}
      </select>
    )

    this.state.issues.forEach((e, i) => {
      issue = (
        <SegmentFooterTabIssuesListItem
          key={i}
          issue={e}
          sid={this.props.sid}
        />
      )
      issues.push(issue)
    })
    let containerClasses = classnames({
      'issues-container': true,
      'add-issue-segment': this.state.isChangedTextarea,
    })
    let categoryClass = classnames({
      'field select-category': true,
      select_type:
        _.isNull(this.state.categorySelected) ||
        this.state.categorySelected === -1,
    })
    let severityClass = classnames({
      'field select-severity': true,
      'category-selected':
        !_.isNull(this.state.categorySelected) &&
        this.state.categorySelected !== -1,
    })
    return (
      <div className={containerClasses}>
        <div className="border-box-issue">
          <div className="creation-issue-container ui form">
            <div className="ui grid">
              <div className={categoryClass}>{this.getCategoryDropdown()}</div>
              <div
                className={severityClass}
                ref={(input) => {
                  this.selectIssueCategoryWrapper = input
                }}
              >
                {severitySelect}
              </div>
            </div>
          </div>
        </div>
        <div className="border-box-issue">
          <div className="issues-list">{issues}</div>
        </div>
      </div>
    )
  }
}

export default SegmentFooterTabIssues
