import React, {useContext, useRef} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {CategoryRow} from './CategoryRow'
import {SeveritiyRow} from './SeveritiyRow'
import {SeverityColumn} from './SeverityColumn'
import {AddCategory} from './AddCategory'
import {AddSeverity} from './AddSeverity'

export const CategoriesSeveritiesTable = () => {
  const {currentTemplate} = useContext(QualityFrameworkTabContext)

  const {categories = []} = currentTemplate ?? {}

  const prevCategories = useRef()
  const prevSeverities = useRef()

  const wasAddedCategory =
    Array.isArray(prevCategories.current) &&
    categories.length > prevCategories.current.length

  const wasAddedSeverity =
    Array.isArray(prevSeverities.current) &&
    categories[0].severities.length > prevSeverities.current.length

  prevCategories.current = categories
  prevSeverities.current = categories[0].severities

  console.log(currentTemplate)
  return (
    <div className="quality-framework-categories-severities">
      <h2>Lorem ipsum</h2>
      <p>
        Lorem ipsum dolor sit amet consectetur. Vestibulum mauris gravida
        volutpat libero vulputate faucibus ultrices convallis. Non sagittis in
        condimentum lectus dapibus. Vestibulum volutpat tempus sed sed odio
        eleifend porta malesuada.
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
