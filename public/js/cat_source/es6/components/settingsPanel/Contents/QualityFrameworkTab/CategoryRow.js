import React from 'react'
import PropTypes from 'prop-types'

export const CategoryRow = ({category}) => {
  const {label} = category

  return <div className="row">{label}</div>
}

CategoryRow.propTypes = {
  category: PropTypes.object,
}
