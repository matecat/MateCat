import React, {useContext, useRef} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {CategoryRow} from './CategoryRow'
import {SeveritiyRow} from './SeverityRow'
import {SeverityColumn} from './SeverityColumn'
import {AddCategory} from './AddCategory'
import {AddSeverity} from './AddSeverity'
import {AddSeverityCell} from './AddSeverityCell'

export const getCategoryLabelAndDescription = (category) => {
  const [line1, line2] = category.label.split('(')
  const label =
    line1.slice(-1) === ' ' ? line1.substring(0, line1.length - 1) : line1
  const description = line2 && line2.replace(/[()]/g, '')

  return {label, description}
}
export const formatCategoryDescription = (description) =>
  `${description[0] !== '(' ? '(' : ''}${description}${description[description.length - 1] !== ')' ? ')' : ''}`
export const getCodeFromLabel = (label) => label.substring(0, 3).toUpperCase()

export const CategoriesSeveritiesTable = () => {
  const {currentTemplate} = useContext(QualityFrameworkTabContext)

  const {categories = []} = currentTemplate ?? {}

  const previousState = useRef({
    currentTemplateId: undefined,
    categories: undefined,
    severities: undefined,
  })

  const wasAddedCategory =
    currentTemplate.id === previousState.current.currentTemplateId &&
    Array.isArray(previousState.current.categories) &&
    categories.length > previousState.current.categories.length

  const wasAddedSeverity =
    currentTemplate.id === previousState.current.currentTemplateId &&
    Array.isArray(previousState.current.severities) &&
    categories[0].severities.length > previousState.current.severities.length

  previousState.current.currentTemplateId = currentTemplate.id
  previousState.current.categories = categories
  previousState.current.severities = categories[0].severities

  const severitiesColumns = categories
    .reduce((acc, cur) => {
      const {severities} = cur
      const filtered = severities.filter(
        (severity) =>
          !acc.some(
            ({code, label}) =>
              severity?.code === code && severity?.label === label,
          ),
      )

      return [...acc, ...filtered]
    }, [])
    .map(({code, label, sort}) => ({code, label, sort}))
    .sort((a, b) => (a.sort > b.sort ? 1 : -1))

  return (
    <div className="quality-framework-categories-severities">
      <h2>Evaluation grid</h2>
      <p>
        Manage the categories and severities that revisors can select for the
        issues found
      </p>
      <div className="quality-framework-categories-table">
        <div className="scroll-area">
          <div className="categories">
            <span className="header">Categories</span>
            {categories.map((category, index) => (
              <CategoryRow
                key={index}
                {...{
                  category,
                  index,
                  ...(wasAddedCategory &&
                    index === categories.length - 1 && {
                      shouldScrollIntoView: true,
                    }),
                }}
              />
            ))}
          </div>
          <div className="severities">
            <div className="header">
              <span>Severities</span>
              <div className="row row-columns">
                {severitiesColumns.map(({label, code, sort}, index) => (
                  <SeverityColumn
                    key={index}
                    {...{
                      label,
                      code,
                      index,
                      sort,
                      numbersOfColumns: severitiesColumns.length,
                      ...(wasAddedSeverity &&
                        index === categories[0].severities.length - 1 && {
                          shouldScrollIntoView: true,
                        }),
                    }}
                  />
                ))}
              </div>
            </div>
            {categories.map(({id, severities}, index) => (
              <div key={index} className="row">
                {severitiesColumns.map((severityColumn) => {
                  const severity = severities.find(
                    (severity) =>
                      severity.code === severityColumn.code &&
                      severity.label === severityColumn.label,
                  )
                  return severity ? (
                    <SeveritiyRow key={severity.id} {...{severity}} />
                  ) : (
                    <AddSeverityCell
                      key={`${index}-${severityColumn.sort}`}
                      idCategory={id}
                      severityColumn={{...severityColumn}}
                    />
                  )
                })}
              </div>
            ))}
          </div>
        </div>
        <AddSeverity numbersOfColumns={severitiesColumns.length} />
      </div>
      <AddCategory />
    </div>
  )
}
