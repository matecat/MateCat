import React, {useRef, useEffect, useId} from 'react'

import PropTypes from 'prop-types'
import styles from './Checkbox.module.scss'

const CheckmarkIcon = ({size = 14}) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width={size}
    height={size}
    fill="none"
    viewBox="0 0 14 14"
  >
    <path
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      d="M11.667 3.5 5.25 9.917 2.334 7"
    />
  </svg>
)

const MinusIcon = ({size = 14}) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width={size}
    height={size}
    fill="none"
    viewBox="0 0 14 14"
  >
    <path
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      d="M2.917 7h8.167"
    />
  </svg>
)

export const CHECKBOX_SIZE = {
  SMALL: 'small',
  MEDIUM: 'medium',
}

export const CHECKBOX_STATE = {
  CHECKED: 'checked',
  UNCHECKED: 'unchecked',
  INDETERMINATE: 'indeterminate',
}

export const Checkbox = ({
  label,
  size = CHECKBOX_SIZE.MEDIUM,
  value = CHECKBOX_STATE.UNCHECKED,
  onChange,
  disabled = false,
  error,
  className = '',
  testId,
  id,
  ...props
}) => {
  const checkboxRef = useRef(null)
  const shiftKeyPressed = useRef(false)
  const generatedId = useId()
  const inputId = id ?? generatedId

  const handleLabelClick = (e) => {
    e.stopPropagation()
    shiftKeyPressed.current = e.shiftKey
    checkboxRef.current.click()
  }

  const handleCheckboxClick = (e) => {
    e.stopPropagation()
  }

  const handleChange = (e) => {
    const isChecked = !shiftKeyPressed.current ? e.target.checked : true
    onChange(isChecked, shiftKeyPressed.current)
  }

  // `indeterminate` has no HTML attribute/JSX prop equivalent — it can only be
  // set imperatively on the DOM node. `checked` is a real controlled prop below
  // so React's own input-restoration handles it (e.g. reverting a click when the
  // caller rejects the change and the value prop doesn't actually change).
  useEffect(() => {
    if (checkboxRef.current) {
      checkboxRef.current.indeterminate = value === CHECKBOX_STATE.INDETERMINATE
    }
  }, [value])

  return (
    <>
      <div className={styles.container}>
        <div
          className={[
            styles['input-checkbox'],
            styles[size],
            disabled && styles.isDisabled,
            value !== CHECKBOX_STATE.UNCHECKED && styles.checked,
            className,
          ]
            .filter(Boolean)
            .join(' ')}
          onClick={handleLabelClick}
        >
          <input
            ref={checkboxRef}
            id={inputId}
            type="checkbox"
            checked={value === CHECKBOX_STATE.CHECKED}
            onChange={handleChange}
            onClick={handleCheckboxClick}
            disabled={disabled}
            data-testid={testId}
            {...props}
          />
          {value === CHECKBOX_STATE.CHECKED ? (
            <CheckmarkIcon size={size === CHECKBOX_SIZE.MEDIUM ? 14 : 10} />
          ) : (
            value === CHECKBOX_STATE.INDETERMINATE && (
              <MinusIcon size={size === CHECKBOX_SIZE.MEDIUM ? 14 : 10} />
            )
          )}
        </div>
        {label && <label htmlFor={inputId}>{label}</label>}
        {error && <span className={styles.errorMessage}>{error}</span>}
      </div>
    </>
  )
}

Checkbox.propTypes = {
  label: PropTypes.node,
  size: PropTypes.string,
  value: PropTypes.oneOf([...Object.values(CHECKBOX_STATE)]),
  onChange: PropTypes.func.isRequired,
  disabled: PropTypes.bool,
  error: PropTypes.string,
  className: PropTypes.string,
  testId: PropTypes.string,
}
