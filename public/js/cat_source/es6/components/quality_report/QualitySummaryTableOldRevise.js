import React from 'react'

const getTotalSeverities = (lqaNestedCategories) => {
  const severities = []
  const severitiesNames = []

  lqaNestedCategories.forEach((cat) => {
    if (cat.get('subcategories').size === 0) {
      cat.get('severities').forEach((sev) => {
        if (severitiesNames.indexOf(sev.get('label')) === -1) {
          severities.push(sev)
          severitiesNames.push(sev.get('label'))
        }
      })
    } else {
      cat.get('subcategories').forEach((subCat) => {
        subCat.get('severities').forEach((sev) => {
          if (severitiesNames.indexOf(sev.get('label')) === -1) {
            severities.push(sev)
            severitiesNames.push(sev.get('label'))
          }
        })
      })
    }
  })

  return severities
}

const getVoteClassName = (value) =>
  ['poor', 'fail'].includes(value.toLowerCase())
    ? 'job-not-passed'
    : 'job-passed'

export const QualitySummaryTableOldRevise = ({qualitySummary}) => {
  const lqaNestedCategories = qualitySummary.get('categories')
  const severities = getTotalSeverities(lqaNestedCategories)
  const qualityVote = qualitySummary.get('quality_overall')
  const getIssuesForCategory = (categoryId) => {
    if (qualitySummary.size == 0) {
      return
    }

    return qualitySummary
      .get('revise_issues')
      .find((item, key) => key === categoryId)
  }

  return (
    <div className="qr-quality">
      <div className="qr-head">
        <div className="qr-title qr-issue">Issues</div>

        {severities.map((sev, i) => (
          <div className="qr-title qr-severity" key={sev.get('label') + i}>
            <div className="qr-info">{sev.get('label')}</div>
            <div className="qr-label">
              Weight: <b>{sev.get('penalty')}</b>
            </div>
          </div>
        ))}

        <div className="qr-title qr-total-severity qr-old">
          <div className="qr-info">Total Weight</div>
        </div>

        <div className="qr-title qr-total-severity qr-old">
          <div className="qr-info">Tolerated Issues</div>
        </div>

        <div
          className={`qr-title qr-total-severity qr-old ${getVoteClassName(
            qualityVote,
          )}`}
        >
          <div className="qr-info">{qualityVote}</div>
          <div className="qr-label">Total Score</div>
        </div>
      </div>

      <div className="qr-body">
        {lqaNestedCategories.map((cat, i) => {
          const catHtml = []
          const totalIssues = getIssuesForCategory(cat.get('id'))
          let catTotalWeightValue = 0
          let toleratedIssuesValue = 0
          let voteValue = ''

          severities.forEach((currentSev, i) => {
            const severityFound = cat
              .get('severities')
              .filter((sev) => sev.get('label') === currentSev.get('label'))

            if (
              severityFound.size > 0 &&
              totalIssues != null &&
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

          return (
            <div
              className="qr-body-list"
              key={cat.get('id') + i + cat.get('label')}
            >
              <div className="qr-element qr-issue-name">{cat.get('label')}</div>

              {catHtml}

              <div className="qr-element total-severity qr-old">
                {catTotalWeightValue}
              </div>

              <div className="qr-element total-severity qr-old">
                {toleratedIssuesValue}
              </div>

              <div
                className={`qr-element total-severity qr-old ${getVoteClassName(
                  voteValue,
                )}`}
              >
                {voteValue}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
