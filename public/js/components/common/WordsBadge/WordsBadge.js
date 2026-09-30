import React from 'react'
import PropTypes from 'prop-types'
import {EmailsBadge, SPECIALS_SEPARATORS} from '../EmailsBadge/EmailsBadge'

export const WordsBadge = ({
  name,
  onChange,
  value = [],
  placeholder,
  disabled,
  error,
}) => {
  return (
    <EmailsBadge
      {...{
        name,
        onChange,
        value,
        validateChip: /./,
        // none of these formats allows whitespace inside a name, so the space bar
        // closes a pill just like the comma does
        separators: [',', ' ', SPECIALS_SEPARATORS.EnterKey],
        placeholder,
        disabled,
        error,
      }}
    />
  )
}

WordsBadge.propTypes = {
  name: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  value: PropTypes.arrayOf(PropTypes.string),
  placeholder: PropTypes.string,
  error: PropTypes.object,
}
