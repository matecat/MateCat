import React from 'react'
import PropTypes from 'prop-types'
import {EmailsBadge} from '../EmailsBadge/EmailsBadge'

export const WordsBadge = ({
  name,
  onChange,
  value = [],
  placeholder,
  error,
}) => {
  return (
    <EmailsBadge
      {...{name, onChange, value, validatePattern: /./, placeholder, error}}
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
