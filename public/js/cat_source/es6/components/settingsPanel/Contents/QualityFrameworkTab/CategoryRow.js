import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {QualityFrameworkTabContext} from './QualityFrameworkTab'

export const CategoryRow = ({category}) => {
  const {templates, currentTemplate} = useContext(QualityFrameworkTabContext)

  const {label} = category

  const [line1, line2] = label.split('(')

  const checkIsNotSaved = () => {
    if (!templates?.some(({isTemporary}) => isTemporary)) return false

    const originalCurrentTemplate = templates?.find(
      ({id, isTemporary}) => id === currentTemplate.id && !isTemporary,
    )

    return !originalCurrentTemplate.categories.some(
      ({id}) => id === category.id,
    )
  }

  const isNotSaved = checkIsNotSaved()

  return (
    <div className={`row${isNotSaved ? ' row-not-saved' : ''}`}>
      <span>{line1}</span>
      <div className="details">{line2 && `(${line2}`}</div>
    </div>
  )
}

CategoryRow.propTypes = {
  category: PropTypes.object,
}
