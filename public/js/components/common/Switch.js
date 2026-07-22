import React from 'react'
import PropTypes from 'prop-types'
import styles from './Switch.module.scss'

const Switch = ({
  name,
  active = false,
  disabled = false,
  onChange = () => {},
  onClick = () => {},
  className = '',
  testId,
  showText = true,
  activeText = 'Active',
  disabledText = 'Unavailable',
  inactiveText = 'Inactive',
}) => {
  const handleChange = (e) => {
    onChange(e.target.checked)
  }

  return (
    <div className={styles['switch-container-outer']} onClick={onClick}>
      <label className={[styles['switch-container'], className].filter(Boolean).join(' ')}>
        <input
          type="checkbox"
          name={name}
          onChange={handleChange}
          checked={active}
          aria-checked={active}
          disabled={disabled}
          data-testid={testId}
        />

        <span></span>
      </label>
      {showText ? (
        disabled && disabledText ? (
          <span className={styles['switch-container-disabled']}>{disabledText}</span>
        ) : active && activeText ? (
          <span className={styles['switch-container-active']}>{activeText}</span>
        ) : !active && inactiveText ? (
          <span className={styles['switch-container-inactive']}>{inactiveText}</span>
        ) : null
      ) : null}
    </div>
  )
}

Switch.propTypes = {
  name: PropTypes.string,
  active: PropTypes.bool,
  disabled: PropTypes.bool,
  onChange: PropTypes.func,
  onClick: PropTypes.func,
  className: PropTypes.string,
  testId: PropTypes.string,
  activeText: PropTypes.string,
  disabledText: PropTypes.string,
  inactiveText: PropTypes.string,
}

export default Switch
