import React from 'react'
import PropTypes from 'prop-types'
import styles from './Pending.module.scss'

export const PENDING_ALIGN = {
  START: 'start',
  CENTER: 'center',
  END: 'end',
}

/**
 * Shows `children` once the value is known; until then a static grey bar of
 * about the same footprint, announced as "Not available yet".
 */
export const Pending = ({children, known, align = PENDING_ALIGN.START}) => {
  if (known) return children

  return (
    <span
      data-testid="pending"
      className={`${styles.pending} ${styles[align] ?? ''}`.trim()}
    >
      <span className={styles.bar} aria-hidden="true" />
      <span className={styles.hidden}>Not available yet</span>
    </span>
  )
}

Pending.propTypes = {
  children: PropTypes.node,
  known: PropTypes.bool.isRequired,
  align: PropTypes.oneOf(Object.values(PENDING_ALIGN)),
}
