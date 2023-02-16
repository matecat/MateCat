import React, {Component} from 'react'
import {getStyleForName} from '../tagModel'

const TagSuggestion = React.forwardRef((props, ref) => {
  const tagStyle = getStyleForName(props.suggestion.data.name).join(' ')
  const {index} = props.suggestion.data
  return (
    <div
      className={`tag-menu-suggestion ${props.isFocused ? `active` : ''}`}
      onMouseDown={(e) => {
        e.preventDefault()
        props.onTagClick(props.suggestion)
      }}
      style={props.isFocused ? {fontWeight: '700'} : null}
      tabIndex={props.tabIndex}
      ref={ref}
    >
      <div className={'tag-menu-suggestion-item'}>
        {props.suggestion ? (
          <div className={'tag-container'}>
            <div className={`tag ${tagStyle} tag-placeholder`}>
              <span>{props.suggestion.data.placeholder}</span>
              {index >= 0 && <span className="index-counter">{index + 1}</span>}
            </div>
          </div>
        ) : (
          'No tags'
        )}
        {/*<span className={`place-here-tips`}>Place here</span>*/}
      </div>
    </div>
  )
})

export default TagSuggestion
