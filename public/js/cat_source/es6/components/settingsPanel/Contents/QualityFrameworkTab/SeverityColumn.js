import React from 'react'
import PropTypes from 'prop-types'

export const SeverityColumn = ({label}) => {
  return <div className="cell">{label}</div>
}

SeverityColumn.propTypes = {
  label: PropTypes.string,
}
