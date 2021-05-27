import React from 'react'
import _ from 'lodash'

export class QualitySummaryTableOldRevise extends React.Component {
  constructor(props) {
    super(props)
    this.lqaNestedCategories = this.props.qualitySummary.get('categories')
    this.getTotalSeverities()
    this.htmlBody = this.getBody()
    this.htmlHead = this.getHeader()
  }
  getTotalSeverities() {
    this.severities = []
    this.severitiesNames = []
    this.lqaNestedCategories.forEach((cat) => {
      if (cat.get('subcategories').size === 0) {
        cat.get('severities').forEach((sev) => {
          if (this.severitiesNames.indexOf(sev.get('label')) === -1) {
            this.severities.push(sev)
            this.severitiesNames.push(sev.get('label'))
          }
        })
      } else {
        cat.get('subcategories').forEach((subCat) => {
          subCat.get('severities').forEach((sev) => {
            if (this.severitiesNames.indexOf(sev.get('label')) === -1) {
              this.severities.push(sev)
              this.severitiesNames.push(sev.get('label'))
            }
          })
        })
      }
    })
  }
  getIssuesForCategory(categoryId) {
    if (this.props.qualitySummary.size > 0) {
      return this.props.qualitySummary
        .get('revise_issues')
        .find((item, key) => {
          return key === categoryId
        })
    }
  }
  getHeader() {
    let html = []
    this.severities.forEach((sev, index) => {
      let item = (
        <div className="qr-title qr-severity" key={sev.get('label') + index}>
          <div className="qr-info">{sev.get('label')}</div>
          <div className="qr-label">
            Weight: <b>{sev.get('penalty')}</b>
          </div>
        </div>
      )
      html.push(item)
    })
    let qualityVote = this.props.qualitySummary.get('quality_overall')
    let passedClass =
      qualityVote === 'poor' || qualityVote === 'fail'
        ? 'job-not-passed'
        : 'job-passed'
    return (
      <div className="qr-head">
        <div className="qr-title qr-issue">Issues</div>
        {html}

        <div className="qr-title qr-total-severity qr-old">
          <div className="qr-info">Total Weight</div>
        </div>

        <div className="qr-title qr-total-severity qr-old">
          <div className="qr-info">Tolerated Issues</div>
        </div>

        <div className={'qr-title qr-total-severity qr-old ' + passedClass}>
          <div className="qr-info">
            {this.props.qualitySummary.get('quality_overall')}
          </div>
          <div className="qr-label">Total Score</div>
        </div>
      </div>
    )
  }
  getBody() {
    let html = []
    this.totalWeight = 0
    this.lqaNestedCategories.forEach((cat, index) => {
      let catHtml = []
      let totalIssues = this.getIssuesForCategory(cat.get('id'))
      let catTotalWeightValue = 0,
        toleratedIssuesValue = 0,
        voteValue = ''
      this.severities.forEach((currentSev, i) => {
        let severityFound = cat.get('severities').filter((sev) => {
          return sev.get('label') === currentSev.get('label')
        })
        if (
          severityFound.size > 0 &&
          !_.isUndefined(totalIssues) &&
          totalIssues.get('founds').get(currentSev.get('label'))
        ) {
          catTotalWeightValue =
            catTotalWeightValue +
            totalIssues.get('founds').get(currentSev.get('label')) *
              severityFound.get(0).get('penalty')
          toleratedIssuesValue = totalIssues.get('allowed')
          voteValue = totalIssues.get('vote')
          catHtml.push(
            <div className="qr-element severity" key={`severity-${i}`}>
              {totalIssues.get('founds').get(currentSev.get('label'))}
            </div>,
          )
        } else {
          catHtml.push(
            <div className="qr-element severity" key="severity-empty" />,
          )
        }
      })
      let catTotalWeightHtml = (
        <div className="qr-element total-severity qr-old">
          {catTotalWeightValue}
        </div>
      )
      let toleratedIssuesHtml = (
        <div className="qr-element total-severity qr-old">
          {toleratedIssuesValue}
        </div>
      )
      let voteClass =
        voteValue.toLowerCase() === 'poor' || voteValue.toLowerCase() === 'fail'
          ? 'job-not-passed'
          : 'job-passed'
      let voteHtml = (
        <div className={'qr-element total-severity qr-old ' + voteClass}>
          {voteValue}
        </div>
      )
      let line = (
        <div
          className="qr-body-list"
          key={cat.get('id') + index + cat.get('label')}
        >
          <div className="qr-element qr-issue-name">{cat.get('label')}</div>
          {catHtml}
          {catTotalWeightHtml}
          {toleratedIssuesHtml}
          {voteHtml}
        </div>
      )
      html.push(line)
      this.totalWeight = this.totalWeight + catTotalWeightValue
    })
    return <div className="qr-body">{html}</div>
  }
  render() {
    return (
      <div className="qr-quality">
        {this.htmlHead}
        {this.htmlBody}
      </div>
    )
  }
}
