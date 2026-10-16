import React from 'react'
import PropTypes from 'prop-types'
import styles from './DisabledHint.module.scss'

/**
 * Explains why the control inside is disabled. The hint sits on a wrapper:
 * Firefox shows no title on a disabled button, which gets no mouse events.
 */
export const DisabledHint = ({hint, active, children}) =>
  active ? (
    <span className={styles.hint} title={hint}>
      {children}
    </span>
  ) : (
    children
  )

DisabledHint.propTypes = {
  hint: PropTypes.string.isRequired,
  // Only while true is the control wrapped
  active: PropTypes.bool,
  children: PropTypes.node,
}
