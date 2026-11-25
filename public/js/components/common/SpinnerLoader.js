import PropTypes from 'prop-types'
import React from 'react'
import styles from './SpinnerLoader.module.scss'

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
      className={[styles['spinner-loader'], styles[`spinner-loader-size-${size}`], className].filter(Boolean).join(' ')}
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
