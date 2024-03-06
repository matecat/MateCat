import React from 'react'
import PropTypes from 'prop-types'

export const SeverityColumn = ({label}) => {
  return <div className="column">{label}</div>
}

SeverityColumn.propTypes = {
  label: PropTypes.string,
}
