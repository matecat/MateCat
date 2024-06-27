import React from 'react'
import PropTypes from 'prop-types'
import useStyles from 'substyle'

/*
    Component dependency of library react-mentions
*/

const defaultStyle = {
  fontWeight: 'inherit',
}

const Mention = ({display, style, className, classNames}) => {
  const styles = useStyles(defaultStyle, {style, className, classNames})
  return <strong {...styles}>{display}</strong>
}

Mention.propTypes = {
  onAdd: PropTypes.func,
  onRemove: PropTypes.func,

  renderSuggestion: PropTypes.func,

  trigger: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.instanceOf(RegExp),
  ]),
  markup: PropTypes.string,
  displayTransform: PropTypes.func,
  /**
   * If set to `true` spaces will not interrupt matching suggestions
   */
  allowSpaceInQuery: PropTypes.bool,

  isLoading: PropTypes.bool,
}

export default Mention
