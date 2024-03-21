import React from 'react'
import PropTypes from 'prop-types'
import IconClose from '../../icons/IconClose'

export const TAG_STATUS = {
  DEFAULT: 'default',
  SELECTED: 'selected',
  INVALID: 'invalid',
}

export const Tag = ({
  children,
  onRemove = () => {},
  status = TAG_STATUS.DEFAULT,
}) => {
  const handleCLick = (e) => {
    e.stopPropagation()

    onRemove()
  }

  return (
    <span className={`email-badge-tag email-badge-tag-${[status]}`}>
      {children}
      <div onClick={handleCLick}>
        <IconClose />
      </div>
    </span>
  )
}

Tag.propTypes = {
  children: PropTypes.node,
  onRemove: PropTypes.func.isRequired,
  status: PropTypes.oneOf([...Object.values(TAG_STATUS)]),
}
