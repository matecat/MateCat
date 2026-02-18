import React, {useState, useRef, useEffect, useCallback, useMemo} from 'react'
import PropTypes from 'prop-types'
import {debounce} from 'lodash/function'

const styleInput = {
  fontFamily: 'calibri, Arial, Helvetica, sans-serif',
  fontSize: '16px',
  padding: '10px 20px',
  borderRadius: '40px',
  outlineColor: '#e5e9f1',
  border: 'unset',
  boxShadow: '0px 0px 0px 1px rgba(34, 36, 38, 0.25) inset',
  display: 'flex',
  maxWidth: '150px',
}

const styleIcon = {
  visibility: 'visible',
  right: '7px',
  cursor: 'pointer',
}

const InputField = (props) => {
  const {
    value: propValue,
    onFieldChanged,
    text,
    type: propType,
    placeholder,
    name,
    classes,
    tabindex,
    onKeyPress,
    showCancel,
  } = props

  const [value, setValue] = useState(propValue ? propValue : '')
  const inputRef = useRef(null)

  // Debounced callback
  const debouncedOnChange = useMemo(
    () =>
      debounce(() => {
        onFieldChanged(value)
      }, 500),
    [onFieldChanged, value],
  )

  // Handle input change
  const handleChange = useCallback(
    (event) => {
      setValue(event.target.value)
      debouncedOnChange()
    },
    [debouncedOnChange],
  )

  // Reset input
  const resetInput = useCallback(() => {
    setValue('')
    onFieldChanged('')
  }, [onFieldChanged])

  // componentDidMount logic
  useEffect(() => {
    if (text && inputRef.current) {
      const event = new Event('input', {bubbles: true})
      inputRef.current.dispatchEvent(event)
    }
    // Cleanup debounce on unmount
    return () => {
      debouncedOnChange.cancel && debouncedOnChange.cancel()
    }
  }, [text, debouncedOnChange])

  const type = propType ? propType : 'text'

  return (
    <div className={'qr-filter-idSegment'}>
      <input
        data-testid="input"
        style={styleInput}
        type={type}
        placeholder={placeholder}
        value={value}
        name={name}
        onChange={handleChange}
        className={classes}
        tabIndex={tabindex}
        onKeyPress={onKeyPress}
        ref={inputRef}
      />
      {showCancel && value.length > 0 ? (
        <div
          data-testid="reset-button"
          className="ui cancel label"
          style={styleIcon}
          onClick={resetInput}
        >
          <i className="icon-cancel3" />
        </div>
      ) : null}
    </div>
  )
}

InputField.propTypes = {
  onFieldChanged: PropTypes.func.isRequired,
}

export default InputField
