import React from 'react'
import PropTypes from 'prop-types'

export const CategoryRow = ({category}) => {
  const {label} = category

  const [line1, line2] = label.split('(')

  return (
    <div className="row">
      <span>{line1}</span>
      <div className="details">{line2 && `(${line2}`}</div>
    </div>
  )
}

CategoryRow.propTypes = {
  category: PropTypes.object,
}
