import React, {useContext} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {CategoryRow} from './CategoryRow'
import {SeveritiyRow} from './SeveritiyRow'
import {SeverityColumn} from './SeverityColumn'
import {AddCategory} from './AddCategory'
import {AddSeverity} from './AddSeverity'

export const CategoriesSeveritiesTable = () => {
  const {currentTemplate} = useContext(QualityFrameworkTabContext)

  const {categories = []} = currentTemplate ?? {}

  console.log(currentTemplate)
  return (
    <div className="quality-framework-categories-severities">
      <div className="quality-framework-categories-table">
        <div className="scroll-area">
          <div className="categories">
            <span className="header">Categories</span>
            {categories.map((category, index) => (
              <CategoryRow key={index} {...{category, index}} />
            ))}
          </div>
          <div className="severities">
            <div className="header">
              <span>Severities</span>
              <div className="row row-columns">
                {categories[0]?.severities.map(({label}, index) => (
                  <SeverityColumn key={index} {...{label, index}} />
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
