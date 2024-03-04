import React from 'react'
import PropTypes from 'prop-types'

export const SeveritiyRow = ({severity}) => {
  const {penalty} = severity

  return <div className="cell">{penalty}</div>
}

SeveritiyRow.propTypes = {
  severity: PropTypes.object,
}
