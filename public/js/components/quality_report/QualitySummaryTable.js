import React, {useMemo, useCallback} from 'react'

function QualitySummaryTable({qualitySummary}) {
  const lqaNestedCategories = useMemo(
    () => qualitySummary.get('categories').sortBy((cat) => cat.get('id')),
    [qualitySummary],
  )

  const {severities, thereAreSubCategories, categoriesGroups} = useMemo(() => {
    const severities = []
    const seenLabels = new Set()
    const categoriesGroups = []
    let thereAreSubCategories = false

    const collectSeverity = (sev) => {
      const label = sev.get('label')
      if (!seenLabels.has(label)) {
        severities.unshift(sev.toJS())
        seenLabels.add(label)
      }
    }

    lqaNestedCategories.forEach((cat) => {
      if (cat.get('subcategories').size === 0) {
        const currentSeverities = cat.get('severities')
        const existingGroup = categoriesGroups.find((group) =>
          group[0].get('severities').equals(currentSeverities),
        )
        if (existingGroup) {
          existingGroup.push(cat)
        } else {
          categoriesGroups.push([cat])
        }
        currentSeverities.forEach(collectSeverity)
      } else {
        thereAreSubCategories = true
        cat.get('subcategories').forEach((subCat) => {
          subCat.get('severities').forEach(collectSeverity)
        })
      }
    })

    const isSortDefined = severities.every(({sort}) => typeof sort === 'number')

    return {
      severities: isSortDefined
        ? severities.sort((a, b) => a.sort - b.sort)
        : severities,
      thereAreSubCategories,
      categoriesGroups,
    }
  }, [lqaNestedCategories])

  const getIssuesForCategory = useCallback(
    (categoryId) => {
      if (qualitySummary.size > 0) {
        return qualitySummary
          .get('revise_issues')
          .find((_item, key) => parseInt(key) === parseInt(categoryId))
      }
    },
    [qualitySummary],
  )

  const getIssuesForCategoryWithSubcategory = useCallback(
    (category, sevLabel) => {
      if (qualitySummary.size === 0) return 0
      const reviseIssues = qualitySummary.get('revise_issues')

      if (category.subcategories.length > 0) {
        return category.subcategories.reduce((total, sub) => {
          const issue = reviseIssues.get(sub.id)
          const found = issue?.get('founds')?.get(sevLabel)
          return total + (found || 0)
        }, 0)
      }

      const issue = reviseIssues.get(category.id)
      return issue ? issue.get('founds').get(sevLabel) || 0 : 0
    },
    [qualitySummary],
  )

  const getCategorySeverities = useCallback(
    (categoryId) => {
      const cat = lqaNestedCategories.find(
        (cat) => parseInt(categoryId) === parseInt(cat.get('id')),
      )
      const severities = cat.get('severities')
      return severities && severities.size > 0
        ? severities
        : cat.get('subcategories').get(0).get('severities')
    },
    [lqaNestedCategories],
  )

  const renderHeader = useCallback(
    () => (
      <div className="qr-head">
        <div className="qr-title qr-issue">Categories</div>
        <div className="qr-title qr-severity">Severities</div>
        {severities.map(
          (sev, i) =>
            i > 0 && (
              <div className="qr-title qr-severity" key={sev.label + i} />
            ),
        )}
        <div className="qr-title qr-total-severity">Error Points</div>
      </div>
    ),
    [severities],
  )

  const renderBody = useCallback(() => {
    const groupRows = categoriesGroups.flatMap((group, groupIndex) => {
      const sevGroup = group[0].get('severities')

      const severityWeightRow = (
        <div
          className="qr-body-list severity_weight-line"
          key={`group-line-${groupIndex}`}
        >
          <div
            className="qr-element qr-issue-name severity_weight"
            key={`group-${groupIndex}`}
          />
          {/* Some groups may not have all severities */}
          {severities.map((sev) => {
            const severityMatch = sevGroup.find(
              (currSev) => currSev.get('label') === sev.label,
            )
            return severityMatch ? (
              <div
                className="qr-element severity severity_weight"
                key={`sev-weight-${sev.label}${groupIndex}`}
              >
                {sev.label}{' '}
                <span>(x{parseFloat(severityMatch.get('penalty'))})</span>
              </div>
            ) : (
              <div
                className="qr-element severity severity_weight"
                key={`sev-weight${sev.label}${groupIndex}`}
              />
            )
          })}
          <div className="qr-element total-severity severity_weight" />
        </div>
      )

      const categoryRows = group
        .filter((cat) => cat.get('label') !== 'Kudos')
        .map((cat, catIndex) => {
          const totalIssues = getIssuesForCategory(cat.get('id'))
          let catTotalWeightValue = 0

          const severityCells = severities.map((currentSev, sevIndex) => {
            const severityFound = cat
              .get('severities')
              .filter((sev) => sev.get('label') === currentSev.label)

            if (
              severityFound.size > 0 &&
              totalIssues !== undefined &&
              totalIssues.get('founds').get(currentSev.label)
            ) {
              const issues = totalIssues.get('founds').get(currentSev.label)
              catTotalWeightValue += parseFloat(
                (issues * severityFound.get(0).get('penalty')).toFixed(2),
              )
              return (
                <div
                  className="qr-element severity"
                  key={`sev-total-issues-${sevIndex}`}
                >
                  <span>{issues}</span>
                </div>
              )
            }
            return (
              <div className="qr-element severity" key={`sev-${sevIndex}`} />
            )
          })

          return (
            <div
              className={`qr-body-list${catIndex === 0 ? ' qr-body-list-first' : ''}`}
              key={cat.get('label') + catIndex}
            >
              <div className="qr-element qr-issue-name" key={cat.get('label')}>
                {cat.get('label')}
              </div>
              {severityCells}
              <div
                className="qr-element total-severity"
                key={`total-${catIndex}`}
              >
                <span>{catTotalWeightValue}</span>
              </div>
            </div>
          )
        })

      return [severityWeightRow, ...categoryRows]
    })

    const severitiesTotal = severities.map((sev) => {
      const totalSev = categoriesGroups.reduce((acc, group) => {
        const catJs = group[0].toJS()
        const totalIssues = getIssuesForCategory(catJs.id)?.toJS()
        if (totalIssues?.founds[sev.label]) {
          const severityDef = catJs.severities.find(
            (s) => s.label === sev.label,
          )
          return acc + totalIssues.founds[sev.label] * severityDef.penalty
        }
        return acc
      }, 0)
      return {label: sev.label, total: totalSev}
    })

    const totalScore = qualitySummary.get('total_issues_weight')

    return (
      <div className="qr-body">
        {groupRows}
        <div className="qr-body-list qr-total-line">
          <div className="qr-element qr-issue-name">Total</div>
          {severitiesTotal.map((sev, i) => (
            <div className="qr-element severity" key={`total-sev-${i}`}>
              {sev.total}
            </div>
          ))}
          <div className="qr-element total-severity total-score">
            {totalScore}
          </div>
        </div>
      </div>
    )
  }, [categoriesGroups, severities, getIssuesForCategory, qualitySummary])

  const renderBodyWithSubcategories = useCallback(() => {
    const rows = lqaNestedCategories.map((cat, index) => {
      let catTotalWeightValue = 0

      const severityCells = severities.map((currentSev) => {
        const catSeverities = getCategorySeverities(cat.get('id'))
        const severityFound = catSeverities.filter(
          (sev) => sev.get('label') === currentSev.label,
        )
        const totalIssues = getIssuesForCategoryWithSubcategory(
          cat.toJS(),
          currentSev.label,
        )

        if (severityFound.size > 0 && totalIssues > 0) {
          catTotalWeightValue += parseFloat(
            (totalIssues * severityFound.get(0).get('penalty')).toFixed(2),
          )
          return (
            <div
              className="qr-element severity"
              key={currentSev.label + cat.get('id')}
            >
              {totalIssues}
            </div>
          )
        }
        return (
          <div
            className="qr-element severity"
            key={currentSev.label + cat.get('id')}
          />
        )
      })

      return (
        <div className="qr-body-list" key={cat.get('label') + index}>
          <div
            className="qr-element qr-issue-name"
            key={cat.get('label') + index}
          >
            {cat.get('label')}
          </div>
          {severityCells}
          <div
            className="qr-element total-severity"
            key={`totalW${cat.get('id')}`}
          >
            {catTotalWeightValue}
          </div>
        </div>
      )
    })

    return <div className="qr-body">{rows}</div>
  }, [
    lqaNestedCategories,
    severities,
    getCategorySeverities,
    getIssuesForCategoryWithSubcategory,
  ])

  const hasKudos = categoriesGroups.some(
    (group) => group[0].get('label') === 'Kudos',
  )

  const kudosCount = hasKudos
    ? qualitySummary
        .get('revise_issues')
        .find((item) => item.get('name') === 'Kudos')
        ?.get('founds')
        ?.get('Neutral') || 0
    : 0

  return (
    <>
      <div className="qr-quality">
        {renderHeader()}
        {thereAreSubCategories ? renderBodyWithSubcategories() : renderBody()}
      </div>
      {hasKudos && (
        <div className="qr-kudos">
          <div className="qr-kudos-title">Kudos</div>
          <div className="qr-kudos-value">{kudosCount}</div>
        </div>
      )}
    </>
  )
}

export default QualitySummaryTable
