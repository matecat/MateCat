import React, {useContext} from 'react'
import {Button, BUTTON_SIZE} from '../../../common/Button/Button'
import PropTypes from 'prop-types'
import IconAdd from '../../../icons/IconAdd'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const AddSeverityCell = ({idCategory, severityColumn}) => {
  const {modifyingCurrentTemplate} = useContext(QualityFrameworkTabContext)

  const addSeverity = () => {
    modifyingCurrentTemplate((prevTemplate) => {
      const {categories} = prevTemplate

      const lastSeverityId = categories.reduce(
        (acc, cur) =>
          [...acc, ...cur.severities.map(({id}) => id)].sort((a, b) =>
            a < b ? 1 : -1,
          ),
        [],
      )[0]

      const newSeverity = {
        ...severityColumn,
        id: lastSeverityId + 1,
        id_category: idCategory,
        penalty: 0,
      }

      return {
        ...prevTemplate,
        categories: categories.map((category) => ({
          ...category,
          ...(category.id === idCategory && {
            severities: [...category.severities, newSeverity].sort((a, b) =>
              a.sort > b.sort ? 1 : -1,
            ),
          }),
        })),
      }
    })
  }

  return (
    <div className="cell quality-framework-severity-add-severity-button">
      <Button size={BUTTON_SIZE.SMALL} onClick={addSeverity}>
        <IconAdd />
        Add severity
      </Button>
    </div>
  )
}

AddSeverityCell.propTypes = {
  idCategory: PropTypes.number.isRequired,
  severityColumn: PropTypes.object.isRequired,
}
