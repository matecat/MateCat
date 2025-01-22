import React, {useContext} from 'react'
import {Button, BUTTON_SIZE} from '../../../common/Button/Button'
import PropTypes from 'prop-types'
import IconAdd from '../../../icons/IconAdd'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const AddSeverityCell = ({severity}) => {
  const {modifyingCurrentTemplate} = useContext(QualityFrameworkTabContext)

  const addSeverity = () => {
    modifyingCurrentTemplate((prevTemplate) => {
      const {categories} = prevTemplate

      const newSeverity = {
        ...severity,
        penalty: 0,
      }

      return {
        ...prevTemplate,
        categories: categories.map((category) => ({
          ...category,
          ...(category.id === severity.id_category && {
            severities: category.severities.map((severity) =>
              severity.id === newSeverity.id ? newSeverity : severity,
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
  severity: PropTypes.object.isRequired,
}
