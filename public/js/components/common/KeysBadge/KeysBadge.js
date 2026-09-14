import React from 'react'
import PropTypes from 'prop-types'
import {EmailsBadge, SPECIALS_SEPARATORS} from '../EmailsBadge/EmailsBadge'

/**
 * Pill input for the JSON and YAML extraction parameters.
 *
 * A JSON object key and a YAML mapping key are arbitrary strings, so " label " is a legitimate
 * key distinct from "label": the value is kept verbatim and the pill spells out the padding.
 * The space bar therefore cannot close a pill here, unlike in {@link WordsBadge}.
 */
export const KeysBadge = ({
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
        separators: [',', SPECIALS_SEPARATORS.EnterKey],
        trimChips: false,
        revealEdgeWhitespace: true,
        placeholder,
        disabled,
        error,
      }}
    />
  )
}

KeysBadge.propTypes = {
  name: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
  value: PropTypes.arrayOf(PropTypes.string),
  placeholder: PropTypes.string,
  disabled: PropTypes.bool,
  error: PropTypes.object,
}
