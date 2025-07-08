import React, {useCallback} from 'react'
import PropTypes from 'prop-types'
import {
  EmailsBadge,
  SPECIALS_SEPARATORS,
} from '../../../../common/EmailsBadge/EmailsBadge'

const regexpNumbersAndDash = /^(\d+)\s*-\s*(\d+)$/

export const NumbersDashBadge = ({
  name,
  onChange,
  value = [],
  placeholder,
  disabled,
  error,
}) => {
  const validateUserTypingCallback = useCallback(
    (value) => /^[0-9-,]*$/.test(value),
    [],
  )

  const validateChipCallback = useCallback((value) => {
    if (regexpNumbersAndDash.test(value)) {
      const match = value.match(regexpNumbersAndDash)
      if (parseInt(match[1]) < parseInt(match[2])) return true
    } else if (/^\d+$/.test(value)) return true
    return false
  }, [])

  return (
    <EmailsBadge
      {...{
        name,
        onChange,
        value,
        validateUserTyping: validateUserTypingCallback,
        validateChip: validateChipCallback,
        separators: [',', SPECIALS_SEPARATORS.EnterKey],
        placeholder,
        disabled,
        error,
      }}
    />
  )
}

NumbersDashBadge.propTypes = {
  name: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  value: PropTypes.arrayOf(PropTypes.string),
  placeholder: PropTypes.string,
  error: PropTypes.object,
}
