import React, {useRef, useState, useLayoutEffect, useCallback} from 'react'
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
  autoWidth = false,
}) => {
  const optionRefs = useRef([])
  const [cursorStyle, setCursorStyle] = useState(null)

  const selectedIndex = options.findIndex((option) => option.id === selectedId)
  const optionWidth = 100 / options.length

  const updateCursor = useCallback(() => {
    const el = optionRefs.current[selectedIndex]
    if (el) {
      setCursorStyle({
        width: `${el.offsetWidth}px`,
        transform: `translateX(${el.offsetLeft}px)`,
      })
    }
  }, [selectedIndex])

  useLayoutEffect(() => {
    if (autoWidth) {
      updateCursor()
    }
  }, [autoWidth, updateCursor])

  const handleChange = (event) => {
    onChange(event.target.value)
  }

  const renderOption = (option, index) => {
    return (
      <div
        key={option.id}
        ref={autoWidth ? (el) => (optionRefs.current[index] = el) : undefined}
        className="segmented-control__option"
        style={!autoWidth ? {width: `${optionWidth}%`} : undefined}
      >
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

  const defaultCursorStyle = {
    width: `${optionWidth}%`,
    transform: `translateX(${100 * selectedIndex}%)`,
  }

  return (
    <div className={`segmented-control__wrapper ${className}`}>
      {label && <label htmlFor={name}>{label}</label>}
      <div
        className={`segmented-control ${
          compact ? 'segmented-control--compact' : ''
        } ${options.length === 1 ? 'segmented-control--single' : ''} ${
          autoWidth ? 'segmented-control--auto-width' : ''
        }`}
      >
        <div
          className="segmented-control__cursor"
          style={autoWidth ? cursorStyle : defaultCursorStyle}
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
  autoWidth: PropTypes.bool,
}
