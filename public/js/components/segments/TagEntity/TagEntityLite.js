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
  const [phTagsCompressed, setPhTagsCompressed] = useState(
    CatToolStore.isPhTagsCompressed(),
  )

  useEffect(() => {
    const handler = () => setPhTagsCompressed(CatToolStore.isPhTagsCompressed())
    CatToolStore.addListener(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      handler,
    )
    return () =>
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
        handler,
      )
  }, [])

  const getStyle = () => {
    const {
      data: {name: entityName},
    } = contentState.getEntity(entityKey)

    const baseStyle =
      tagSignatures[entityName] &&
      (isRTL && tagSignatures[entityName].styleRTL
        ? tagSignatures[entityName].styleRTL
        : tagSignatures[entityName].style)

    return baseStyle
  }

  const style = getStyle()

  const {
    data: {index, name: entityName, placeholder, pcRole},
  } = contentState.getEntity(entityKey)

  const isPhTag = entityName === 'ph'
  const isCompressedPh = isPhTag && phTagsCompressed && index >= 0
  const pcRoleClass = isPhTag && pcRole ? ` tag-pc-${pcRole}` : ''

  const isPcClose = isPhTag && pcRole === 'close'

  const getChildrenContent = () => {
    if (isPhTag && index >= 0) {
      // A closing pc tag always shows only its number.
      return (
        <>
          <span className="index-counter">{index + 1}</span>
          {!phTagsCompressed && !isPcClose && (
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
          isCompressedPh ? ' tag-compressed' : ''
        }${pcRoleClass}`}
        data-offset-key={offsetkey}
        unselectable="on"
        suppressContentEditableWarning={true}
      >
        {getChildrenContent()}
      </span>
    </span>
  )

  if (isPhTag && placeholder && !isPcClose) {
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
