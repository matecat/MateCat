import PropTypes from 'prop-types'
import React from 'react'

export const SPINNER_LOADER_SIZE = {
  SMALL: 'small',
  MEDIUM: 'medium',
  LARGE: 'large',
}

export const SpinnerLoader = ({
  label,
  size = SPINNER_LOADER_SIZE.LARGE,
  className,
}) => {
  return (
    <div
      className={`spinner-loader spinner-loader-size-${size} ${className ? className : ''}`}
    >
      <span>{label ?? 'Loading'}</span>
    </div>
  )
}

SpinnerLoader.propTypes = {
  label: PropTypes.string,
  size: PropTypes.oneOf(Object.values(SPINNER_LOADER_SIZE)),
  className: PropTypes.string,
}
