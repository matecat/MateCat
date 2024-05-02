import React, {useContext, useRef} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {CategoryRow} from './CategoryRow'
import {SeveritiyRow} from './SeveritiyRow'
import {SeverityColumn} from './SeverityColumn'
import {AddCategory} from './AddCategory'
import {AddSeverity} from './AddSeverity'

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
                {categories[0]?.severities.map(({label}, index) => (
                  <SeverityColumn
                    key={index}
                    {...{
                      label,
                      index,
                      ...(wasAddedSeverity &&
                        index === categories[0].severities.length - 1 && {
                          shouldScrollIntoView: true,
                        }),
                    }}
                  />
                ))}
              </div>
            </div>
            {categories.map(({severities}, index) => (
              <div key={index} className="row">
                {severities.map((severity, index) => (
                  <SeveritiyRow key={index} {...{severity}} />
                ))}
              </div>
            ))}
          </div>
        </div>
        <AddSeverity />
      </div>
      <AddCategory />
    </div>
  )
}
