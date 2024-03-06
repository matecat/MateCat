import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const SeverityColumn = ({label}) => {
  const {templates, currentTemplate} = useContext(QualityFrameworkTabContext)

  const checkIsNotSaved = () => {
    if (!templates?.some(({isTemporary}) => isTemporary)) return false

    const originalCurrentTemplate = templates?.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    return !originalCurrentTemplate.categories.some(({severities}) =>
      severities.some((severity) => severity.label === label),
    )
  }

  const isNotSaved = checkIsNotSaved()

  return (
    <div className={`column${isNotSaved ? ' column-not-saved' : ''}`}>
      {label}
    </div>
  )
}

SeverityColumn.propTypes = {
  label: PropTypes.string,
}
