import React from 'react'
import PropTypes from 'prop-types'
import IconClose from '../../../../img/icons/IconClose'
import styles from './EmailsBadge.module.scss'

export const TAG_STATUS = {
  DEFAULT: 'default',
  SELECTED: 'selected',
  INVALID: 'invalid',
}

export const Tag = ({
  children,
  onRemove = () => {},
  status = TAG_STATUS.DEFAULT,
  removable = true,
}) => {
  const handleCLick = (e) => {
    e.stopPropagation()

    onRemove()
  }

  return (
    <div className={[styles['email-badge-tag'], styles[status]].filter(Boolean).join(' ')}>
      {children}
      {removable && (
        <div
          className={styles['email-badge-tag-button-close']}
          onClick={handleCLick}
          data-testid="email-badge-tag-remove"
        >
          <IconClose size={8} />
        </div>
      )}
    </div>
  )
}

Tag.propTypes = {
  children: PropTypes.node,
  onRemove: PropTypes.func.isRequired,
  status: PropTypes.oneOf([...Object.values(TAG_STATUS)]),
  removable: PropTypes.bool,
}
