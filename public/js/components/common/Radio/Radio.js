import React, {useRef} from 'react'

import PropTypes from 'prop-types'

import styles from './Radio.module.scss'

export const RADIO_SIZE = {
  SMALL: 'small',
  MEDIUM: 'medium',
}

export const Radio = ({
  name,
  size = RADIO_SIZE.MEDIUM,
  label,
  checked = false,
  disabled = false,
  onChange = () => {},
  testId,
  ...props
}) => {
  const inputRef = useRef()

  const handleChange = () => {
    onChange(!checked)
  }

  return (
    <div className={styles.container}>
      <input
        ref={inputRef}
        className={styles[size]}
        type="radio"
        id={name}
        name={name}
        checked={checked}
        disabled={disabled}
        aria-checked={checked}
        onChange={handleChange}
        data-testid={testId}
        {...props}
      />
      {label && <label htmlFor={name}>{label}</label>}
    </div>
  )
}

Radio.propTypes = {
  name: PropTypes.string.isRequired,
  label: PropTypes.node,
  checked: PropTypes.bool,
  disabled: PropTypes.bool,
  onChange: PropTypes.func.isRequired,
}
