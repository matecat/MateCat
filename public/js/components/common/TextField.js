import PropTypes from 'prop-types'
import React, {useEffect, useRef} from 'react'

const spanStyle = {
  color: 'red',
  fontSize: '14px',
}

const TextField = ({
  showError,
  errorText,
  text,
  onFieldChanged,
  type,
  placeholder,
  name,
  classes,
  tabindex,
  onKeyPress,
}) => {
  const inputRef = useRef(null)

  useEffect(() => {
    if (text) {
      var event = new Event('input', {bubbles: true})
      inputRef.current.dispatchEvent(event)

      if (onFieldChanged) onFieldChanged({target: {value: text}})
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  var errorHtml = ''
  var inputType = 'text'

  if (type) {
    inputType = type
  }

  if (showError && errorText != '') {
    errorHtml = (
      <div className="validation-error">
        <div style={spanStyle} className="text">
          {errorText}
        </div>
      </div>
    )
  }

  return (
    <div
      style={{
        position: 'relative',
        marginBottom: '17px',
      }}
    >
      <input
        type={inputType}
        placeholder={placeholder}
        defaultValue={text}
        name={name}
        onChange={onFieldChanged}
        className={classes}
        tabIndex={tabindex}
        onKeyPress={onKeyPress}
        ref={inputRef}
      />
      {errorHtml}
    </div>
  )
}

TextField.propTypes = {
  showError: PropTypes.bool.isRequired,
  onFieldChanged: PropTypes.func.isRequired,
}

export default TextField
