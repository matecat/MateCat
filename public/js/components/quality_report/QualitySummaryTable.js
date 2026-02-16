import React from 'react'

import {isUndefined} from 'lodash'
import {isEqual} from 'lodash/lang'

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
    console.log('severities', severities)
    console.log(
      'categoriesGroups',
      categoriesGroups.map((i) => i[0].toJS()),
    )
    console.log('Quality summary', this.props.qualitySummary.toJS())
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
          if (isEqual(group[0].get('severities'), currentSeverities)) {
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

    const isSortProperyDefined = severities.every(
      ({sort}) => typeof sort === 'number',
    )

    return {
      severities: isSortProperyDefined
        ? severities.sort((a, b) => (a.sort > b.sort ? 1 : -1))
        : severities,
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
            !isUndefined(
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
    return (
      <div className="qr-head">
        <div className="qr-title qr-issue">Categories</div>
        <div className="qr-title qr-severity">Severities</div>
        {this.severities.map((sev, i) => {
          if (i > 0) {
            return <div className="qr-title qr-severity" key={sev.label + i} />
          }
        })}
        <div className="qr-title qr-total-severity">Error Points</div>
      </div>
    )
  }
  getBody() {
    let html = []
    let kudos
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
              {sev.label}{' '}
              <span>(x{parseFloat(severityFind.get('penalty'))})</span>
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
            !isUndefined(totalIssues) &&
            totalIssues.get('founds').get(currentSev.label)
          ) {
            const issues = totalIssues.get('founds').get(currentSev.label)
            const total = parseFloat(
              (issues * severityFound.get(0).get('penalty')).toFixed(2),
            )
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
          return
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
        if (cat.get('label') === 'Kudos') {
          kudos = line
        } else {
          html.push(line)
        }
      })
    })
    let severitiesTotal = []
    this.severities.forEach((sev) => {
      let totalSev = 0
      this.categoriesGroups.forEach((cat, i) => {
        const catJs = cat[0].toJS()
        const totalIssues = this.getIssuesForCategory(catJs.id)?.toJS()
        if (totalIssues?.founds[sev.label]) {
          totalSev =
            totalSev +
            totalIssues.founds[sev.label] *
              catJs.severities.find((s) => s.label === sev.label).penalty
        }
      })
      severitiesTotal = [
        ...severitiesTotal,
        {label: sev.label, total: totalSev},
      ]
    })
    let totalScore = this.props.qualitySummary.get('total_issues_weight')

    const totalLines = (
      <div className="qr-body-list qr-total-line">
        <div className="qr-element qr-issue-name">Total</div>
        {severitiesTotal.map((sev, i) => {
          return (
            <div className="qr-element severity" key={'total-sev-' + i}>
              {sev.total}
            </div>
          )
        })}
        <div className="qr-element total-severity total-score">
          {totalScore}
        </div>
      </div>
    )
    return (
      <div className="qr-body">
        {html}
        {totalLines}
      </div>
    )
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
            parseFloat(
              (totalIssues * severityFound.get(0).get('penalty')).toFixed(2),
            )
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
    let htmlBody, kudos
    let htmlHead = this.getHeader()
    if (this.thereAreSubCategories) {
      htmlBody = this.getBodyWithSubcategories()
    } else {
      htmlBody = this.getBody()
    }
    if (this.categoriesGroups.find((cat) => cat[0].get('label') === 'Kudos')) {
      kudos = (
        <div className="qr-kudos">
          <div className="qr-kudos-title">Kudos</div>
          <div className="qr-kudos-value">
            {this.props.qualitySummary
              .get('revise_issues')
              .find((item, key) => {
                return item.get('name') === 'Kudos'
              })
              ?.get('founds')
              ?.get('Neutral') || 0}
          </div>
        </div>
      )
    }
    return (
      <>
        <div className="qr-quality">
          {htmlHead}
          {htmlBody}
        </div>
        {kudos}
      </>
    )
  }
}

export default QualitySummaryTable
