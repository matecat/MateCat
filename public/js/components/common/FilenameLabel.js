import React from 'react'
import PropTypes from 'prop-types'
import styles from './FilenameLabel.module.scss'

export const FilenameLabel = ({children, maxWidth, cssClassName = ''}) => {
  const [name, extension] =
    children.lastIndexOf('.') > 1
      ? [
          children.substr(0, children.lastIndexOf('.')),
          children.substring(children.lastIndexOf('.')),
        ]
      : [children, '']


  return (
    <span className={styles['filename-label']} data-testid="filename-label">
      <span className={[styles.name, cssClassName].filter(Boolean).join(' ')} style={maxWidth && {maxWidth}}>
        {name}
      </span>
      {extension && (
        <span className={cssClassName || undefined}>{extension}</span>
      )}
    </span>
  )
}

FilenameLabel.propTypes = {
  children: PropTypes.string.isRequired,
  maxWidth: PropTypes.number,
  cssClassName: PropTypes.string,
}
