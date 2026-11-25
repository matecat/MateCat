import React, {useRef, useState, useEffect} from 'react'
import {tagSignatures} from '../utils/DraftMatecatUtils/tagModel'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import Tooltip from '../../common/Tooltip'

export const TagEntityLite = ({
  entityKey,
  contentState,
  offsetkey,
  isRTL,
  children,
}) => {
  const tagRef = useRef()
  const containerRef = useRef()
  const [tagsCompressed, setTagsCompressed] = useState(
    CatToolStore.isTagsCompressed(),
  )

  useEffect(() => {
    const handler = () => setTagsCompressed(CatToolStore.isTagsCompressed())
    CatToolStore.addListener(CatToolConstants.TOGGLE_TAGS_COMPRESSED, handler)
    return () =>
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_TAGS_COMPRESSED,
        handler,
      )
  }, [])

  const getStyle = () => {
    const {
      data: {name: entityName, index},
    } = contentState.getEntity(entityKey)

    // Every compressible (numbered) tag looks like a pc tag, so a numbered g
    // takes the ph style too.
    const styleName = index >= 0 ? 'ph' : entityName
    const baseStyle =
      tagSignatures[styleName] &&
      (isRTL && tagSignatures[styleName].styleRTL
        ? tagSignatures[styleName].styleRTL
        : tagSignatures[styleName].style)

    return baseStyle
  }

  const style = getStyle()

  const {
    data: {index, name: entityName, placeholder, tagRole},
  } = contentState.getEntity(entityKey)

  const isPhTag = entityName === 'ph'
  // Only compressible tags carry an index (see compressibleTagUtils).
  const isNumbered = index >= 0
  const isCompressed = isNumbered && tagsCompressed
  const isPaired = isNumbered && (tagRole === 'open' || tagRole === 'close')
  const tagRoleClass = isNumbered
    ? ` tag-numbered${isPaired ? ` tag-paired-${tagRole}` : ''}`
    : ''

  const isClose = isNumbered && tagRole === 'close'

  const getChildrenContent = () => {
    if (isNumbered) {
      // A closing tag always shows only its number.
      return (
        <>
          <span className="index-counter">{index + 1}</span>
          {!tagsCompressed && !isClose && (
            <span className="tag-text-lite">{children}</span>
          )}
        </>
      )
    }
    return children
  }

  const tag = (
    <span ref={containerRef} className="tag-container tag-container-lite">
      <span
        ref={tagRef}
        className={`tag ${style}${
          isCompressed ? ' tag-compressed' : ''
        }${tagRoleClass}`}
        data-offset-key={offsetkey}
        unselectable="on"
        suppressContentEditableWarning={true}
      >
        {getChildrenContent()}
      </span>
    </span>
  )

  if ((isPhTag || isNumbered) && placeholder && !isClose) {
    return (
      <Tooltip
        stylePointerElement={{display: 'inline-block', position: 'relative'}}
        content={
          <span className={`tag ${style}`}>
            <span>{placeholder}</span>
          </span>
        }
      >
        {tag}
      </Tooltip>
    )
  }

  return tag
}
