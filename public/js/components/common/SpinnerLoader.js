import PropTypes from 'prop-types'
import React from 'react'

export const SPINNER_LOADER_SIZE = {
  SMALL: 'small',
  MEDIUM: 'medium',
  LARGE: 'large',
}

export const SpinnerLoader = ({label, size = SPINNER_LOADER_SIZE.LARGE}) => {
  return (
    <div className={`spinner-loader spinner-loader-size-${size}`}>
      <span>{label ?? 'Loading'}</span>
    </div>
  )
}

SpinnerLoader.propTypes = {
  label: PropTypes.string,
  size: PropTypes.oneOf(Object.values(SPINNER_LOADER_SIZE)),
}
