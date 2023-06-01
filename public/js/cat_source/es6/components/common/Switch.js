import React from 'react'
import PropTypes from 'prop-types'

const Switch = ({
  name,
  active = false,
  disabled = false,
  onChange = () => {},
  className = '',
  testId,
}) => {
  const handleChange = (e) => {
    onChange(e.target.checked)
  }

  return (
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
  )
}

Switch.propTypes = {
  name: PropTypes.string,
  active: PropTypes.bool,
  disabled: PropTypes.bool,
  onChange: PropTypes.func,
  className: PropTypes.string,
  testId: PropTypes.string,
}

export default Switch
