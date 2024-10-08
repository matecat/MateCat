import React, {useState} from 'react'

import PropTypes from 'prop-types'

import {Button, BUTTON_MODE, BUTTON_SIZE} from '../Button/Button'
import Hide from '../../icons/Hide'
import Show from '../../icons/Show'

export const INPUT_SIZE = {
  DEFAULT: 'default',
  COMPRESSED: 'compressed',
}
export const INPUT_TYPE = {
  TEXT: 'text',
  PASSWORD: 'password',
  NUMBER: 'number',
  EMAIL: 'email',
}

export const Input = React.forwardRef(
  (
    {
      label,
      name,
      value = '',
      placeholder,
      type = INPUT_TYPE.TEXT,
      size = INPUT_SIZE.DEFAULT,
      disabled = false,
      autoComplete,
      icon,
      min = 0,
      max,
      step = 1,
      error,
      onChange,
      className = '',
      ...otherProps
    },
    ref,
  ) => {
    const [isPasswordVisible, setPasswordVisibility] = useState(false)
    const togglePassword = () => setPasswordVisibility(!isPasswordVisible)

    const containerClassName = `input-component ${
      size === INPUT_SIZE.COMPRESSED ? 'isCompressed' : ''
    } ${className}`
    const wrapperClassName = `input-component-wrapper ${icon ? 'withIcon' : ''}`
    const inputClassName = `${type === INPUT_TYPE.PASSWORD ? 'isPassword' : ''} ${
      size === INPUT_SIZE.COMPRESSED ? 'isCompressed' : ''
    } ${error ? 'hasError' : ''}`

    return (
      <div className={containerClassName}>
        {label && <label htmlFor={name}>{label}</label>}
        <div className={wrapperClassName}>
          <input
            ref={ref}
            id={name}
            className={inputClassName}
            type={
              type === INPUT_TYPE.PASSWORD && isPasswordVisible ? 'text' : type
            }
            name={name}
            value={value}
            placeholder={placeholder}
            disabled={disabled}
            autoComplete={autoComplete}
            min={min}
            max={max}
            step={step}
            onChange={onChange}
            onWheel={
              type === INPUT_TYPE.NUMBER ? (e) => e.target.blur() : undefined
            }
            {...otherProps}
          />
          {icon}
          {type === INPUT_TYPE.PASSWORD && (
            <Button
              mode={BUTTON_MODE.GHOST}
              size={BUTTON_SIZE.ICON_SMALL}
              active={isPasswordVisible}
              className="input-component-togglePwdButton"
              onClick={togglePassword}
              tooltip="Show/hide password"
            >
              {isPasswordVisible ? <Hide size={16} /> : <Show size={16} />}
            </Button>
          )}
        </div>
        {error && error.message && (
          <span className="input-component-errorMessage">{error.message}</span>
        )}
      </div>
    )
  },
)

Input.displayName = 'Input'

Input.propTypes = {
  label: PropTypes.node,
  name: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  placeholder: PropTypes.string,
  type: PropTypes.oneOf([...Object.values(INPUT_TYPE)]),
  size: PropTypes.oneOf([...Object.values(INPUT_SIZE)]),
  disabled: PropTypes.bool,
  autoComplete: PropTypes.string,
  icon: PropTypes.node,
  min: PropTypes.number,
  max: PropTypes.number,
  step: PropTypes.number,
  error: PropTypes.object,
  onChange: PropTypes.func.isRequired,
  className: PropTypes.string,
}
