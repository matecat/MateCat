import React from 'react'
import _ from 'lodash'

class QualitySummaryTable extends React.Component {
  constructor(props) {
    super(props)
    this.lqaNestedCategories = this.props.qualitySummary
      .get('categories')
      .sortBy((cat) => cat.get('id'))
    const {severities, thereAreSubCategories, categoriesGroups} =
      this.analyzeQualityModel()
    this.thereAreSubCategories = thereAreSubCategories
    this.severities = severities
    this.categoriesGroups = categoriesGroups
  }
  analyzeQualityModel() {
    let severities = []
    let severitiesFounded = []
    let categoriesGroups = []
    let thereAreSubCategories = false
    this.lqaNestedCategories.forEach((cat) => {
      if (cat.get('subcategories').size === 0) {
        let currentSeverities = cat.get('severities')
        let groupFound = false
        categoriesGroups.map((group) => {
          if (_.isEqual(group[0].get('severities'), currentSeverities)) {
            group.push(cat)
            groupFound = true
          }
        })
        if (!groupFound) {
          categoriesGroups.push([cat])
        }
        currentSeverities.forEach((sev) => {
          if (severitiesFounded.indexOf(sev.get('label')) === -1) {
            severities.unshift(sev.toJS())
            severitiesFounded.push(sev.get('label'))
          }
        })
      } else {
        thereAreSubCategories = true
        cat.get('subcategories').forEach((subCat) => {
          subCat.get('severities').forEach((sev) => {
            if (severitiesFounded.indexOf(sev.get('label')) === -1) {
              severities.unshift(sev.toJS())
              severitiesFounded.push(sev.get('label'))
            }
          })
        })
      }
    })
    severities = _.orderBy(severities, ['dqf_id'])
    return {
      severities,
      thereAreSubCategories,
      categoriesGroups,
    }
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
    const cat = this.lqaNestedCategories.find(
      (cat) => parseInt(categoryId) === parseInt(cat.get('id')),
    )
    return cat.get('severities')
      ? cat.get('severities')
      : cat.get('subcategories').get(0).get('severities')
  }
  getHeader() {
    let html = []
    this.severities.forEach((sev, index) => {
      let item = (
        <div className="qr-title qr-severity" key={sev.label + index}>
          <div className="qr-info">{sev.label}</div>
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
          <div className="qr-info qr-info-total">
            <b>{totalScore}</b>
          </div>
        </div>
      </div>
    )
  }
  getBody() {
    let html = []
    this.categoriesGroups.forEach((group, i) => {
      let groupHtml = []
      const sevGroup = group[0].get('severities')
      groupHtml.push(
        <div
          className="qr-element qr-issue-name severity_weight"
          key={'group' - i}
        />,
      )
      //Some group can have not all severities
      this.severities.forEach((sev) => {
        let severityFind = sevGroup.find(
          (currSev) => currSev.get('label') === sev.label,
        )
        if (severityFind) {
          groupHtml.push(
            <div
              className={`qr-element severity severity_weight`}
              key={'sev-weight-' + sev.label + i}
            >
              Weight: {severityFind.get('penalty')}
            </div>,
          )
        } else {
          groupHtml.push(
            <div
              className={`qr-element severity severity_weight`}
              key={'sev-weight' + sev.label + i}
            />,
          )
        }
      })
      const lineSeverityGroup = (
        <div
          className="qr-body-list severity_weight-line"
          key={'group-line-' + i}
        >
          {groupHtml}
          <div className="qr-element total-severity severity_weight" />
        </div>
      )
      html.push(lineSeverityGroup)
      group.forEach((cat, index) => {
        let catHtml = []
        catHtml.push(
          <div className="qr-element qr-issue-name" key={cat.get('label')}>
            {cat.get('label')}
          </div>,
        )
        const totalIssues = this.getIssuesForCategory(cat.get('id'))
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
            const issues = totalIssues.get('founds').get(currentSev.label)
            const total = issues * severityFound.get(0).get('penalty')
            catTotalWeightValue = catTotalWeightValue + total
            catHtml.push(
              <div
                className="qr-element severity"
                key={'sev-total-issues-' + i}
              >
                <span>{issues}</span>
              </div>,
            )
          } else {
            catHtml.push(
              <div className={`qr-element severity`} key={'sev-' + i} />,
            )
          }
        })
        let catTotalWeightHtml = (
          <div className="qr-element total-severity" key={'total-' + index}>
            <span>{catTotalWeightValue}</span>
          </div>
        )
        if (cat.get('label') === 'Kudos') {
          let issues = 0
          if (totalIssues && totalIssues.size > 0) {
            cat.get('severities').forEach((sev) => {
              issues += totalIssues.get('founds').get(sev.get('label'))
            })
          }
          catTotalWeightHtml = (
            <div
              className="qr-element total-severity kudos-total"
              key={'total-' + index}
            >
              <div className={'kudos-total-label'}>Total Kudos Points</div>
              <div className={'kudos-total-number'}>{issues}</div>
            </div>
          )
        }
        const line = (
          <div
            className={
              'qr-body-list ' + (index === 0 ? 'qr-body-list-first' : '')
            }
            key={cat.get('label') + index}
          >
            {catHtml}
            {catTotalWeightHtml}
          </div>
        )
        html.push(line)
      })
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
            <div
              className="qr-element severity"
              key={currentSev.label + cat.get('id')}
            />,
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
        <div className="qr-body-list" key={cat.get('label') + index}>
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
