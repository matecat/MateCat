import React from 'react'
import PropTypes from 'prop-types'
import Close from '../../../../img/icons/Close'
import IconClose from '../../icons/IconClose'
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
}) => {
  const handleCLick = (e) => {
    e.stopPropagation()

    onRemove()
  }

  return (
    <div className={[styles['email-badge-tag'], styles[status]].filter(Boolean).join(' ')}>
      {children}
      <div className={styles['email-badge-tag-button-close']} onClick={handleCLick}>
        <IconClose size={8} />
      </div>
    </div>
  )
}

Tag.propTypes = {
  children: PropTypes.node,
  onRemove: PropTypes.func.isRequired,
  status: PropTypes.oneOf([...Object.values(TAG_STATUS)]),
}
