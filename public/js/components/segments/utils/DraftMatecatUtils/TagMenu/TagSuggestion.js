import React from 'react'
import {getStyleForName} from '../tagModel'

const TagSuggestion = React.forwardRef((props, ref) => {
  const {index, tagRole} = props.suggestion.data
  // Every compressible (numbered) tag looks like a pc tag, so a numbered g
  // takes the ph style too.
  const tagStyle = getStyleForName(
    index >= 0 ? 'ph' : props.suggestion.data.name,
    config.isTargetRTL,
  ).join(' ')
  const tagRoleClass =
    tagRole === 'open' || tagRole === 'close' ? ` tag-paired-${tagRole}` : ''
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
            <div className={`tag ${tagStyle} tag-placeholder${tagRoleClass}`}>
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

TagSuggestion.displayName = 'TagSuggestion'

export default TagSuggestion
