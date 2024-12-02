import React, {useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {Button} from '../Button/Button'
import {debounce} from 'lodash'

export const NumericStepper = ({
  value,
  onChange,
  minimumValue,
  maximumValue,
  name,
  label,
  disabled,
  stepValue = 1,
}) => {
  const [isInFocus, setIsInFocus] = useState(false)

  const ref = useRef()

  const increase = () => {
    const newValue = value + stepValue
    onChange(newValue <= maximumValue ? newValue : maximumValue)
  }
  const decrease = () => {
    const newValue = value - stepValue
    onChange(newValue >= minimumValue ? newValue : minimumValue)
  }
  const onChageInput = ({currentTarget: {value}}) => {
    if (/^\d+$/g.test(value)) {
      const newValue = parseInt(value)
      onChange(
        newValue > maximumValue
          ? maximumValue
          : newValue < minimumValue
            ? minimumValue
            : newValue,
      )
    }
  }

  const debounceSelectAll = debounce((event) => event.target.select(), 100)

  const labelInput = isInFocus ? value : !isInFocus && label ? label : value

  return (
    <div className="numeric-stepper-component">
      <input
        ref={ref}
        type="string"
        name={name}
        value={labelInput}
        disabled={disabled}
        onChange={onChageInput}
        onFocus={(event) => {
          debounceSelectAll(event)
          setIsInFocus(true)
        }}
        onBlur={() => setIsInFocus(false)}
      />
      <div className="container-controls">
        <Button onClick={decrease}>up</Button>
        <Button onClick={increase}>down</Button>
      </div>
    </div>
  )
}

NumericStepper.propTypes = {
  value: PropTypes.number.isRequired,
  onChange: PropTypes.func.isRequired,
  minimumValue: PropTypes.number.isRequired,
  maximumValue: PropTypes.number.isRequired,
  name: PropTypes.string,
  label: PropTypes.string,
  disabled: PropTypes.bool,
  stepValue: PropTypes.number,
}
