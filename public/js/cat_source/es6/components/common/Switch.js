import React from 'react'
import PropTypes from 'prop-types'

const Switch = ({
  name,
  active = false,
  disabled = false,
  onChange = () => {},
  onClick = () => {},
  className = '',
  testId,
  activeText = 'Active',
  disabledText = 'Unavailable',
  inactiveText = 'Inactive',
}) => {
  const handleChange = (e) => {
    onChange(e.target.checked)
  }

  return (
    <div className="switch-container-outer" onClick={onClick}>
      <label className={`switch-container ${className}`}>
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
      {disabled && disabledText ? (
        <span className="switch-container-disabled">{disabledText}</span>
      ) : active && activeText ? (
        <span className="switch-container-active">{activeText}</span>
      ) : !active && inactiveText ? (
        <span className="switch-container-inactive">{inactiveText}</span>
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
