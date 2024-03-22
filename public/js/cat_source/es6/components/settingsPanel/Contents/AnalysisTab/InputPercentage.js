import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'

export const InputPercentage = ({value = '', setFn, className, dataTestid}) => {
  const inputRef = useRef()
  const [inputValue, setInputValue] = useState(value + '%')
  const onPercentInput = () => {
    let int
    let hasPercent = false
    if (inputRef.current.value.indexOf('%') > -1) {
      int = inputRef.current.value.split('%')[0]
      hasPercent = true
    } else {
      int = inputRef.current.value
    }
    int = parseInt(int)
    int = isNaN(int) ? '' : int
    if (int > 100) {
      int = 100
    }
    setInputValue(hasPercent ? int + '%' : int)
  }
  const onBlur = () => {
    let int
    if (
      inputValue &&
      !Number.isInteger(inputValue) &&
      inputValue.indexOf('%') > -1
    ) {
      int = inputValue.split('%')[0]
    } else {
      int = inputValue
    }
    int = int === '' ? 0 : int
    setInputValue(int + '%')
    setFn(parseInt(int))
  }
  const selectAll = () => inputRef.current.select()

  useEffect(() => {
    setInputValue(value + '%')
  }, [value])
  return (
    <input
      className={'input-percentage ' + className}
      ref={inputRef}
      value={inputValue}
      onChange={(e) => onPercentInput(e)}
      onBlur={onBlur}
      onFocus={() => selectAll()}
      data-testid={dataTestid}
    />
  )
}

InputPercentage.propTypes = {
  value: PropTypes.number,
  setFn: PropTypes.func,
  classname: PropTypes.string,
}
