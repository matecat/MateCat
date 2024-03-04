import React, {useContext} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {CategoryRow} from './CategoryRow'
import {SeveritiyRow} from './SeveritiyRow'
import {SeverityColumn} from './SeverityColumn'

export const CategoriesSeveritiesTable = () => {
  const {currentTemplate} = useContext(QualityFrameworkTabContext)

  const {categories = []} = currentTemplate ?? {}

  return (
    <div className="quality-framework-categories-table">
      <div className="categories">
        <span className="header">Categories</span>
        {categories.map((category, index) => (
          <CategoryRow key={index} {...{category}} />
        ))}
      </div>
      <div className="severities">
        <span className="header">Severities</span>
        <div className="row row-columns">
          {categories[0]?.severities.map(({label}, index) => (
            <SeverityColumn key={index} {...{label}} />
          ))}
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
  )
}
