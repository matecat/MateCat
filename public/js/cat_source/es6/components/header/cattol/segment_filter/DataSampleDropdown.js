import React, {useCallback, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'
import ChevronDown from '../../../../../../../img/icons/ChevronDown'
import IconClose from '../../../icons/IconClose'

export const DataSampleDropdown = ({
  onChange,
  onChangeSampleSize,
  isDisabled,
  className = '',
  samplingSize,
  samplingType,
  resetFunction = () => {},
}) => {
  const [isDropdownVisible, setIsDropdownVisible] = useState(false)
  const [samplingSizeValue, setSamplingSizeValue] = useState(samplingSize)

  const wrapperRef = useRef()

  const samplingTypeMap = [
    {
      value: 'edit_distance_high_to_low',
      label: 'Edit distance (A - Z)',
    },
    {
      value: 'edit_distance_low_to_high',
      label: 'Edit distance (Z - A)',
    },
    {
      value: 'segment_length_high_to_low',
      label: 'Segment length (A - Z)',
    },
    {
      value: 'segment_length_low_to_high',
      label: 'Segment length (Z - A)',
    },
    {
      value: 'regular_intervals',
      label: 'Regular interval',
    },
  ]

  const closeDropdown = useCallback((e) => {
    if (e) e.stopPropagation()
    if (wrapperRef.current && !wrapperRef.current.contains(e?.target)) {
      window.eventHandler.removeEventListener(
        'click.dataSampleDropdown',
        closeDropdown,
      )

      setIsDropdownVisible(false)
    }
  }, [])

  const toggleDropdown = () => {
    if (isDropdownVisible) {
      window.eventHandler.removeEventListener(
        'click.dataSampleDropdown',
        closeDropdown,
      )
    } else {
      window.eventHandler.addEventListener(
        'click.dataSampleDropdown',
        closeDropdown,
      )
    }

    setIsDropdownVisible((prevState) => !prevState)
  }

  return (
    <div
      ref={wrapperRef}
      className={
        `data-sample-dropdown-container ${className} ${isDropdownVisible ? ' open' : ''}` +
        className
      }
    >
      <Button
        type={BUTTON_TYPE.BASIC}
        size={BUTTON_SIZE.MEDIUM}
        mode={BUTTON_MODE.OUTLINE}
        onClick={toggleDropdown}
        disabled={isDisabled}
        className={`trigger-button data-sample-dropdown${isDropdownVisible ? ' open' : ''}`}
      >
        {!isDisabled && samplingType ? (
          <>
            <div
              onClick={(e) => {
                e.stopPropagation()
                resetFunction()
              }}
            >
              <IconClose size={8} />
            </div>
            {samplingTypeMap.find((item) => item.value === samplingType)
              ?.label + ` - ${samplingSize}%`}
          </>
        ) : (
          'Data Sample'
        )}
        <ChevronDown />
      </Button>
      <div className={`dropdown${isDropdownVisible ? ' open' : ''}`}>
        <ul>
          <li className="sample-size-item">
            <div className="sample-size-container">
              <span>
                Sample size <b>(%)</b>
              </span>
              <input
                type="number"
                placeholder="n°"
                value={samplingSizeValue}
                onChange={(event) => {
                  let value = parseInt(event.target.value, 10)
                  if (value > 0 && value <= 100) {
                    setSamplingSizeValue(value)
                    onChangeSampleSize(value)
                  }
                }}
              />
            </div>
            <div className="divider" />
          </li>
          {samplingTypeMap.map((item) => (
            <li
              className={item.value === samplingType ? 'active' : ''}
              key={item.value}
              onClick={() => {
                onChange(item.value)
              }}
            >
              <div className="type-item">{item.label}</div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

DataSampleDropdown.propTypes = {
  onChange: PropTypes.func.isRequired,
  onChangeSampleSize: PropTypes.func.isRequired,
  isDisabled: PropTypes.bool.isRequired,
}
