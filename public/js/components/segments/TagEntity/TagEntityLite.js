import React, {useRef} from 'react'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'

export const TagEntityLite = ({
  entityKey,
  contentState,
  offsetkey,
  isRTL,
  children,
}) => {
  const tagRef = useRef()

  const getStyle = () => {
    const {
      data: {name: entityName},
    } = contentState.getEntity(entityKey)

    // Basic style accordin to language direction
    const baseStyle =
      tagSignatures[entityName] &&
      (isRTL && tagSignatures[entityName].styleRTL
        ? tagSignatures[entityName].styleRTL
        : tagSignatures[entityName].style)

    return baseStyle
  }

  const style = getStyle()

  const {
    data: {index},
  } = contentState.getEntity(entityKey)

  return (
    <div className="tag-container tag-container-lite">
      <span
        ref={tagRef}
        className={`tag ${style}`}
        data-offset-key={offsetkey}
        unselectable="on"
        suppressContentEditableWarning={true}
      >
        {children}
        {index >= 0 && <span className="index-counter">{index + 1}</span>}
      </span>
    </div>
  )
}
