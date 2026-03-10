import React, {useRef, useEffect} from 'react'

import PropTypes from 'prop-types'

export const CHECKBOX_STATE = {
  CHECKED: 'checked',
  UNCHECKED: 'unchecked',
  INDETERMINATE: 'indeterminate',
}

export const Checkbox = ({
  label,
  value = CHECKBOX_STATE.UNCHECKED,
  onChange,
  disabled = false,
  error,
  className = '',
  testId,
}) => {
  const checkboxRef = useRef(null)
  const shiftKeyPressed = useRef(false)

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
    checkboxRef.current.checked = isChecked
    onChange(isChecked, shiftKeyPressed.current)
  }

  useEffect(() => {
    if (checkboxRef.current) {
      if (value === CHECKBOX_STATE.CHECKED) {
        checkboxRef.current.checked = true
        checkboxRef.current.indeterminate = false
      } else if (value === CHECKBOX_STATE.UNCHECKED) {
        checkboxRef.current.checked = false
        checkboxRef.current.indeterminate = false
      } else if (value === CHECKBOX_STATE.INDETERMINATE) {
        checkboxRef.current.checked = false
        checkboxRef.current.indeterminate = true
      }
    }
  }, [value])

  return (
    <>
      <div
        className={`input-checkbox ${disabled ? 'isDisabled' : ''} ${
          value !== CHECKBOX_STATE.UNCHECKED ? 'checked' : ''
        } ${className}`}
        onClick={handleLabelClick}
      >
        <input
          ref={checkboxRef}
          type="checkbox"
          onChange={handleChange}
          onClick={handleCheckboxClick}
          disabled={disabled}
          data-testid={testId}
        />
        <svg width="14" height="14" viewBox="0 0 16 16">
          {value == CHECKBOX_STATE.CHECKED ? (
            <path
              d="M14 0a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h12Zm-1.685 4.9a1 1 0 0 0-1.414-.015L6.69 9 5.099 7.445a1 1 0 0 0-1.398 1.43l2.29 2.24a1 1 0 0 0 1.399 0l4.91-4.8a1 1 0 0 0 .015-1.414Z"
              fill="currentColor"
              fillRule="evenodd"
            />
          ) : value === CHECKBOX_STATE.INDETERMINATE ? (
            <path
              d="M14 0a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h12Zm-2 7H4a1 1 0 1 0 0 2h8a1 1 0 0 0 0-2Z"
              fill="currentColor"
              fillRule="evenodd"
            />
          ) : (
            <path
              d="M14 0H2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2Zm0 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12Z"
              fill="currentColor"
              fillRule="evenodd"
            />
          )}
        </svg>
        {label && <span>{label}</span>}
      </div>
      {error && <span className={'errorMessage'}>{error}</span>}
    </>
  )
}

Checkbox.propTypes = {
  label: PropTypes.node,
  value: PropTypes.oneOf([...Object.values(CHECKBOX_STATE)]),
  onChange: PropTypes.func.isRequired,
  disabled: PropTypes.bool,
  error: PropTypes.string,
  className: PropTypes.string,
  testId: PropTypes.string,
}
