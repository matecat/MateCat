import React from 'react'
import _ from 'lodash'

class QualitySummaryTable extends React.Component {
  constructor(props) {
    super(props)
    this.lqaNestedCategories = this.props.qualitySummary.get('categories')
    this.thereAreSubCategories = false
    this.getTotalSeverities()
  }
  getTotalSeverities() {
    this.severities = []
    this.severitiesNames = []
    this.lqaNestedCategories.forEach((cat) => {
      if (cat.get('subcategories').size === 0) {
        cat.get('severities').forEach((sev) => {
          if (this.severitiesNames.indexOf(sev.get('label')) === -1) {
            this.severities.unshift(sev.toJS())
            this.severitiesNames.push(sev.get('label'))
          }
        })
      } else {
        this.thereAreSubCategories = true
        cat.get('subcategories').forEach((subCat) => {
          subCat.get('severities').forEach((sev) => {
            if (this.severitiesNames.indexOf(sev.get('label')) === -1) {
              this.severities.unshift(sev.toJS())
              this.severitiesNames.push(sev.get('label'))
            }
          })
        })
      }
    })
    this.severities = _.orderBy(this.severities, ['dqf_id'], ['asc'])
  }
  getIssuesForCategory(categoryId) {
    if (this.props.qualitySummary.size > 0) {
      return this.props.qualitySummary
        .get('revise_issues')
        .find((item, key) => {
          return parseInt(key) === parseInt(categoryId)
        })
    }
  }
  getIssuesForCategoryWithSubcategory(category, sevLabel) {
    let total = 0
    if (this.props.qualitySummary.size > 0) {
      if (category.subcategories.length > 0) {
        category.subcategories.forEach((sub) => {
          if (
            !_.isUndefined(
              this.props.qualitySummary.get('revise_issues').get(sub.id),
            ) &&
            this.props.qualitySummary
              .get('revise_issues')
              .get(sub.id)
              .get('founds')
              .get(sevLabel)
          ) {
            total += this.props.qualitySummary
              .get('revise_issues')
              .get(sub.id)
              .get('founds')
              .get(sevLabel)
          }
        })
      } else {
        if (this.props.qualitySummary.get('revise_issues').get(category.id)) {
          total = this.props.qualitySummary
            .get('revise_issues')
            .get(category.id)
            .get('founds')
            .get(sevLabel)
        }
      }
    }
    return total
  }
  getCategorySeverities(categoryId) {
    let severities
    this.lqaNestedCategories.forEach((cat) => {
      if (parseInt(categoryId) === parseInt(cat.get('id'))) {
        severities = cat.get('severities')
          ? cat.get('severities')
          : cat.get('subcategories').get(0).get('severities')
      }
    })
    return severities
  }
  getHeader() {
    let html = []
    this.severities.forEach((sev, index) => {
      let item = (
        <div className="qr-title qr-severity" key={sev.label + index}>
          <div className="qr-info">{sev.label}</div>
          <div className="qr-label">
            Weight: <b>{sev.penalty}</b>
          </div>
        </div>
      )
      html.push(item)
    })
    let totalScore = this.props.qualitySummary.get('total_issues_weight')
    return (
      <div className="qr-head">
        <div className="qr-title qr-issue">Issues</div>
        {html}
        <div className="qr-title qr-total-severity">
          <div className="qr-info">Total error points</div>
          <div className="qr-info">
            <b>{totalScore}</b>
          </div>
        </div>
      </div>
    )
  }
  getBody() {
    let html = []
    this.lqaNestedCategories.forEach((cat, index) => {
      let catHtml = []
      catHtml.push(
        <div className="qr-element qr-issue-name" key={cat.get('label')}>
          {cat.get('label')}
        </div>,
      )
      let totalIssues = this.getIssuesForCategory(cat.get('id'))
      let catTotalWeightValue = 0
      this.severities.forEach((currentSev, i) => {
        let severityFound = cat.get('severities').filter((sev) => {
          return sev.get('label') === currentSev.label
        })
        if (
          severityFound.size > 0 &&
          !_.isUndefined(totalIssues) &&
          totalIssues.get('founds').get(currentSev.label)
        ) {
          catTotalWeightValue =
            catTotalWeightValue +
            totalIssues.get('founds').get(currentSev.label) *
              severityFound.get(0).get('penalty')
          catHtml.push(
            <div className="qr-element severity" key={'sev-' + i}>
              {totalIssues.get('founds').get(currentSev.label)}
            </div>,
          )
        } else {
          const isSeverityInsideCat = severityFound.size === 0
          catHtml.push(
            <div
              className={`qr-element severity ${
                isSeverityInsideCat ? 'empty' : ''
              }`}
              key={'sev-' + i}
            />,
          )
        }
      })
      let catTotalWeightHtml = (
        <div className="qr-element total-severity" key={'total-' + index}>
          {catTotalWeightValue}
        </div>
      )
      let line = (
        <div className="qr-body-list" key={cat.get('id') + index}>
          {catHtml}
          {catTotalWeightHtml}
        </div>
      )
      html.push(line)
    })
    return <div className="qr-body">{html}</div>
  }
  getBodyWithSubcategories() {
    let html = []
    this.lqaNestedCategories.forEach((cat, index) => {
      let catHtml = []
      catHtml.push(
        <div
          className="qr-element qr-issue-name"
          key={cat.get('label') + index}
        >
          {cat.get('label')}
        </div>,
      )
      let catTotalWeightValue = 0
      this.severities.forEach((currentSev, i) => {
        let catSeverities = this.getCategorySeverities(cat.get('id'))
        let severityFound = catSeverities.filter((sev) => {
          return sev.get('label') === currentSev.label
        })
        let totalIssues = this.getIssuesForCategoryWithSubcategory(
          cat.toJS(),
          currentSev.label,
        )
        if (severityFound.size > 0 && totalIssues > 0) {
          catTotalWeightValue =
            catTotalWeightValue +
            totalIssues * severityFound.get(0).get('penalty')
          catHtml.push(
            <div
              className="qr-element severity"
              key={currentSev.label + cat.get('id')}
            >
              {totalIssues}
            </div>,
          )
        } else {
          catHtml.push(
            <div className="qr-element severity" key={'severity' + i} />,
          )
        }
      })
      let catTotalWeightHtml = (
        <div
          className="qr-element total-severity"
          key={'totalW' + cat.get('id')}
        >
          {catTotalWeightValue}
        </div>
      )
      let line = (
        <div className="qr-body-list" key={cat.get('id') + index}>
          {catHtml}
          {catTotalWeightHtml}
        </div>
      )
      html.push(line)
    })
    return <div className="qr-body">{html}</div>
  }

  render() {
    let htmlBody
    let htmlHead = this.getHeader()
    if (this.thereAreSubCategories) {
      htmlBody = this.getBodyWithSubcategories()
    } else {
      htmlBody = this.getBody()
    }
    return (
      <div className="qr-quality shadow-2">
        {htmlHead}
        {htmlBody}
      </div>
    )
  }
}

export default QualitySummaryTable
