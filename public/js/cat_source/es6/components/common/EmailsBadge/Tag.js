import React from 'react'
import PropTypes from 'prop-types'
import Close from '../../../../../../img/icons/Close'

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
    <div className={`email-badge-tag ${[status]}`}>
      {children}
      <div className="email-badge-tag-button-close" onClick={handleCLick}>
        <Close size={18} />
      </div>
    </div>
  )
}

Tag.propTypes = {
  children: PropTypes.node,
  onRemove: PropTypes.func.isRequired,
  status: PropTypes.oneOf([...Object.values(TAG_STATUS)]),
}
