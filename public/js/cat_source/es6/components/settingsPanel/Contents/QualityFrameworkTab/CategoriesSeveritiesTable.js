import React, {useContext} from 'react'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'
import {CategoryRow} from './CategoryRow'
import {SeveritiyRow} from './SeveritiyRow'
import {SeverityColumn} from './SeverityColumn'
import {
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
  Button,
} from '../../../common/Button/Button'
import AddWide from '../../../../../../../img/icons/AddWide'
import IconAdd from '../../../icons/IconAdd'

const getSeverityCode = (label) => label.substring(0, 3).toUpperCase()

export const CategoriesSeveritiesTable = () => {
  const {currentTemplate, modifyingCurrentTemplate} = useContext(
    QualityFrameworkTabContext,
  )

  const {categories = []} = currentTemplate ?? {}

  const createNewSeverityColumn = () => {
    const label = 'New severity'

    let lastId = categories.slice(-1)[0].severities.slice(-1)[0].id
    const newColum = categories.reduce(
      (acc, cur) => [
        ...acc,
        {
          ...cur.severities.slice(-1)[0],
          id: ++lastId,
          label,
          code: getSeverityCode(label),
          penalty: 0,
        },
      ],
      [],
    )

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      categories: prevTemplate.categories.map((category) => ({
        ...category,
        severities: [
          ...category.severities,
          newColum.find((column) => column.id_category === category.id),
        ],
      })),
    }))
  }

  return (
    <div className="quality-framework-categories-severities">
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
        <div>
          <Button
            className="add-new-severity"
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.ICON_SMALL}
            onClick={createNewSeverityColumn}
          >
            <IconAdd size={22} />
          </Button>
        </div>
      </div>
      <Button
        className="add-new-category"
        type={BUTTON_TYPE.PRIMARY}
        size={BUTTON_SIZE.MEDIUM}
      >
        <IconAdd size={22} /> Add category
      </Button>
    </div>
  )
}
