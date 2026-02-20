import React, {useMemo, useCallback} from 'react'
import {isUndefined} from 'lodash'
import {isEqual} from 'lodash/lang'

function QualitySummaryTable(props) {
  // Memoize lqaNestedCategories and derived values
  const lqaNestedCategories = useMemo(
    () => props.qualitySummary.get('categories').sortBy((cat) => cat.get('id')),
    [props.qualitySummary],
  )

  // Analyze quality model and memoize results
  const {severities, thereAreSubCategories, categoriesGroups} = useMemo(() => {
    let severities = []
    let severitiesFounded = []
    let categoriesGroups = []
    let thereAreSubCategories = false
    lqaNestedCategories.forEach((cat) => {
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
  }, [lqaNestedCategories])

  // Methods
  const getIssuesForCategory = useCallback(
    (categoryId) => {
      if (props.qualitySummary.size > 0) {
        return props.qualitySummary
          .get('revise_issues')
          .find((item, key) => parseInt(key) === parseInt(categoryId))
      }
    },
    [props.qualitySummary],
  )

  const getIssuesForCategoryWithSubcategory = useCallback(
    (category, sevLabel) => {
      let total = 0
      if (props.qualitySummary.size > 0) {
        if (category.subcategories.length > 0) {
          category.subcategories.forEach((sub) => {
            if (
              !isUndefined(
                props.qualitySummary.get('revise_issues').get(sub.id),
              ) &&
              props.qualitySummary
                .get('revise_issues')
                .get(sub.id)
                .get('founds')
                .get(sevLabel)
            ) {
              total += props.qualitySummary
                .get('revise_issues')
                .get(sub.id)
                .get('founds')
                .get(sevLabel)
            }
          })
        } else {
          if (props.qualitySummary.get('revise_issues').get(category.id)) {
            total = props.qualitySummary
              .get('revise_issues')
              .get(category.id)
              .get('founds')
              .get(sevLabel)
          }
        }
      }
      return total
    },
    [props.qualitySummary],
  )

  const getCategorySeverities = useCallback(
    (categoryId) => {
      const cat = lqaNestedCategories.find(
        (cat) => parseInt(categoryId) === parseInt(cat.get('id')),
      )
      return cat.get('severities')
        ? cat.get('severities')
        : cat.get('subcategories').get(0).get('severities')
    },
    [lqaNestedCategories],
  )

  const getHeader = useCallback(() => {
    let html = []
    severities.forEach((sev, index) => {
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
        {severities.map((sev, i) => {
          if (i > 0) {
            return <div className="qr-title qr-severity" key={sev.label + i} />
          }
        })}
        <div className="qr-title qr-total-severity">Error Points</div>
      </div>
    )
  }, [severities])

  const getBody = useCallback(() => {
    let html = []
    categoriesGroups.forEach((group, i) => {
      let groupHtml = []
      const sevGroup = group[0].get('severities')
      groupHtml.push(
        <div
          className="qr-element qr-issue-name severity_weight"
          key={'group' - i}
        />,
      )
      //Some group can have not all severities
      severities.forEach((sev) => {
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
        const totalIssues = getIssuesForCategory(cat.get('id'))
        let catTotalWeightValue = 0
        severities.forEach((currentSev, i) => {
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
    severities.forEach((sev) => {
      let totalSev = 0
      categoriesGroups.forEach((cat, i) => {
        const catJs = cat[0].toJS()
        const totalIssues = getIssuesForCategory(catJs.id)?.toJS()
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
    let totalScore = props.qualitySummary.get('total_issues_weight')

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
  }, [categoriesGroups, severities, getIssuesForCategory, props.qualitySummary])

  const getBodyWithSubcategories = useCallback(() => {
    let html = []
    lqaNestedCategories.forEach((cat, index) => {
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
      severities.forEach((currentSev, i) => {
        let catSeverities = getCategorySeverities(cat.get('id'))
        let severityFound = catSeverities.filter((sev) => {
          return sev.get('label') === currentSev.label
        })
        let totalIssues = getIssuesForCategoryWithSubcategory(
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
  }, [
    lqaNestedCategories,
    severities,
    getCategorySeverities,
    getIssuesForCategoryWithSubcategory,
  ])

  // Render logic
  let htmlBody, kudos
  let htmlHead = getHeader()
  if (thereAreSubCategories) {
    htmlBody = getBodyWithSubcategories()
  } else {
    htmlBody = getBody()
  }
  if (categoriesGroups.find((cat) => cat[0].get('label') === 'Kudos')) {
    kudos = (
      <div className="qr-kudos">
        <div className="qr-kudos-title">Kudos</div>
        <div className="qr-kudos-value">
          {props.qualitySummary
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

export default QualitySummaryTable
