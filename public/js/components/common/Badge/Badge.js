import React from 'react'
import PropTypes from 'prop-types'

export const BADGE_TYPE = {
  BLACK: 'black',
  PRIMARY: 'primary',
  GREEN: 'green',
  YELLOW: 'yellow',
  BLUE: 'blue',
  RED: 'red',
}
export const BADGE_MODE = {
  DEFAULT: 'default',
  FULL: 'full',
  OUTLINE: 'outline',
}

export const Badge = ({
  children,
  type = BADGE_TYPE.BLACK,
  mode = BADGE_MODE.DEFAULT,
  tooltip,
  className = '',
}) => {
  return (
    <span
      className={`badge-container badge-${type} badge-${mode} ${className}`}
      title={tooltip}
    >
      {children}
    </span>
  )
}

Badge.propTypes = {
  children: PropTypes.node,
  type: PropTypes.oneOf([...Object.values(BADGE_TYPE)]),
  mode: PropTypes.oneOf([...Object.values(BADGE_MODE)]),
  tooltip: PropTypes.string,
  className: PropTypes.string,
}
