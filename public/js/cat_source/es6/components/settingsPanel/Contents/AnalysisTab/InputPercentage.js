import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'

export const InputPercentage = ({value = '', setFn, className}) => {
  const inputRef = useRef()
  const [inputValue, setInputValue] = useState(value + '%')
  const onPercentInput = (e) => {
    let int
    if (e.target.value.indexOf('%') > -1) {
      int = e.target.value.split('%')[0]
    } else {
      int = e.target.value
    }
    int = parseInt(int)
    int = isNaN(int) ? '' : int
    if (int > 100) {
      int = 100
    }
    setInputValue(int)
  }
  const onBlur = () => {
    let int = inputValue
    int = int === '' ? 0 : int
    setInputValue(int + '%')
    setFn(int)
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
      onInput={(e) => onPercentInput(e)}
      onBlur={onBlur}
      onFocus={() => selectAll()}
    />
  )
}

InputPercentage.propTypes = {
  value: PropTypes.number,
  setFn: PropTypes.func,
  classname: PropTypes.string,
}
