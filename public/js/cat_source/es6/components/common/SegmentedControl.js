import React from 'react'
import PropTypes from 'prop-types'

export const SegmentedControl = ({
  name,
  label,
  options,
  selectedId,
  onChange,
  className,
  compact = false,
  disabled = false,
}) => {
  const selectedIndex = options.findIndex((option) => option.id === selectedId)
  const optionWidth = 100 / options.length

  const handleChange = (event) => {
    onChange(event.target.value)
  }

  const renderOption = (option, index) => {
    return (
      <div key={index} style={{width: `${optionWidth}%`}}>
        <input
          type="radio"
          name={name}
          value={option.id}
          id={option.id}
          checked={selectedId === option.id}
          onChange={handleChange}
          data-testid={`radio-option-${option.id}`}
          disabled={disabled}
        />
        <label className="segmented-control__label" htmlFor={option.id}>
          {option.icon && option.icon}
          {option.name}
        </label>
      </div>
    )
  }

  return (
    <div className={`segmented-control__wrapper ${className}`}>
      {label && <label htmlFor={name}>{label}</label>}
      <div
        className={`segmented-control ${
          compact ? 'segmented-control--compact' : ''
        } ${options.length === 1 ? 'segmented-control--single' : ''}`}
      >
        <div
          className="segmented-control__cursor"
          style={{
            width: `${optionWidth}%`,
            transform: `translateX(${100 * selectedIndex}%)`,
          }}
        ></div>
        {options.map(renderOption)}
      </div>
    </div>
  )
}

SegmentedControl.propTypes = {
  name: PropTypes.string.isRequired,
  label: PropTypes.string,
  options: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      name: PropTypes.node,
    }),
  ),
  selectedId: PropTypes.string,
  onChange: PropTypes.func.isRequired,
  className: PropTypes.string,
  compact: PropTypes.bool,
  disabled: PropTypes.bool,
}
