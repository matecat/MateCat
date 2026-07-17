import React from 'react'
import PropTypes from 'prop-types'

export const FilenameLabel = ({children, maxWidth, cssClassName = ''}) => {
  const [name, extension] =
    children.lastIndexOf('.') > 1
      ? [
          children.substr(0, children.lastIndexOf('.')),
          children.substring(children.lastIndexOf('.')),
        ]
      : [children, '']

  const className = `${cssClassName ? cssClassName + ' ' : ''}`

  return (
    <span className="filename-label" data-testid="filename-label">
      <span className={`${className}name`} style={maxWidth && {maxWidth}}>
        {name}
      </span>
      {extension && (
        <span className={`${className}extension`}>{extension}</span>
      )}
    </span>
  )
}

FilenameLabel.propTypes = {
  children: PropTypes.string.isRequired,
  maxWidth: PropTypes.number,
  cssClassName: PropTypes.string,
}
