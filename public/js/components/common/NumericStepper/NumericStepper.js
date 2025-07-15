import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_SIZE} from '../Button/Button'
import {debounce} from 'lodash'
import ArrowDown from '../../../../img/icons/ArrowDown'

export const NumericStepper = ({
  value,
  onChange,
  minimumValue,
  maximumValue,
  name,
  valuePlaceholder,
  disabled,
  stepValue = 1,
}) => {
  const [valueInput, setValueInput] = useState('')
  const [isInFocus, setIsInFocus] = useState(false)

  const ref = useRef()

  useEffect(() => {
    const label = isInFocus
      ? value
      : !isInFocus && valuePlaceholder
        ? valuePlaceholder
        : value

    setValueInput(label)
  }, [isInFocus, value, valuePlaceholder])

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
      setValueInput(value)

      const newValue = parseInt(value)
      onChange(newValue)
    } else if (value === '') setValueInput(value)
  }

  const handlerBlur = () => {
    const newValue = parseInt(value)
    onChange(
      newValue > maximumValue
        ? maximumValue
        : newValue < minimumValue
          ? minimumValue
          : newValue,
    )
    setIsInFocus(false)
  }

  const debounceSelectAll = debounce((event) => event.target.select(), 100)

  return (
    <div className="numeric-stepper-component">
      <input
        ref={ref}
        type="string"
        name={name}
        value={valueInput}
        disabled={disabled}
        onChange={onChageInput}
        onFocus={(event) => {
          debounceSelectAll(event)
          setIsInFocus(true)
        }}
        onBlur={handlerBlur}
        onKeyUp={({key}) => key === 'Enter' && ref.current.blur()}
      />
      <div className="container-controls">
        <Button
          size={BUTTON_SIZE.ICON_SMALL}
          disabled={disabled}
          onClick={increase}
        >
          <ArrowDown />
        </Button>
        <Button
          size={BUTTON_SIZE.ICON_SMALL}
          disabled={disabled}
          onClick={decrease}
        >
          <ArrowDown />
        </Button>
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
  valuePlaceholder: PropTypes.string,
  disabled: PropTypes.bool,
  stepValue: PropTypes.number,
}
