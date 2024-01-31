import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import Forbidden from '../../../../../../img/icons/Forbidden'
import {
  DeleteIcon,
  GlossaryDefinitionIcon,
  LockIcon,
  ModifyIcon,
} from './SegmentFooterTabGlossary'
import {TabGlossaryContext} from './TabGlossaryContext'
import LabelWithTooltip from '../../common/LabelWithTooltip'

const DESCRIPTION_ELEMENTS_LINE_CLAMP = 3

export const GlossaryItem = ({
  item,
  modifyElement,
  deleteElement,
  highlight,
  onClick,
  isEnabledToModify = false,
  isStatusDeleting = false,
  isBlacklist = false,
}) => {
  const {isActive} = useContext(TabGlossaryContext)

  const [toolipsRuleDescription, setToolipsRuleDescription] = useState({
    source: false,
    target: false,
  })

  const noteDescriptionPlaceholderRef = useRef()

  useEffect(() => {
    if (!isActive || !noteDescriptionPlaceholderRef?.current) return

    const checkContentLength = () => {
      const placeholderTag = noteDescriptionPlaceholderRef.current
      if (!placeholderTag) return
      placeholderTag.style.display = 'block'

      const lineHeight = Math.round(
        window.getComputedStyle(placeholderTag).lineHeight.split('px')[0],
      )

      placeholderTag.innerText = item?.source?.note ?? ''
      const source =
        placeholderTag.offsetHeight / lineHeight >
        DESCRIPTION_ELEMENTS_LINE_CLAMP

      placeholderTag.innerText = item?.target?.note ?? ''
      const target =
        placeholderTag.offsetHeight / lineHeight >
        DESCRIPTION_ELEMENTS_LINE_CLAMP

      placeholderTag.style.display = 'none'

      setToolipsRuleDescription({source, target})
    }

    checkContentLength()

    window.addEventListener('resize', checkContentLength)

    return () => window.removeEventListener('resize', checkContentLength)
  }, [isActive, item?.source?.note, item?.target?.note])

  const {metadata, source, target} = item

  const canModifyItem = isEnabledToModify && item.term_id && !isStatusDeleting

  return (
    <div className="glossary_item" id={item.term_id}>
      <div className="glossary_item-header">
        <div className="glossary_definition-container">
          <div
            className={`glossary_definition${
              !metadata.definition ? ' glossary_definition--hidden' : ''
            }`}
          >
            <GlossaryDefinitionIcon />
            <span>{metadata.definition}</span>
          </div>
          {metadata.domain && (
            <LabelWithTooltip className="glossary_badge">
              <span>{metadata.domain}</span>
            </LabelWithTooltip>
          )}
          {metadata.subdomain && (
            <LabelWithTooltip className="glossary_badge">
              <span>{metadata.subdomain}</span>
            </LabelWithTooltip>
          )}
          <div className="glossary_source_details">
            <LabelWithTooltip className="glossary_source_tooltip">
              <div className="glossary_source">
                <b>
                  {metadata.key_name
                    ? metadata.key_name
                    : `No name (${metadata.key})`}
                </b>
              </div>
            </LabelWithTooltip>
            <span>{metadata.last_update_date}</span>
          </div>
        </div>
        <div
          className={`glossary_item-actions${
            !canModifyItem && !isStatusDeleting
              ? ' glossary_item-actions--disabled'
              : ''
          }`}
        >
          <div onClick={() => canModifyItem && modifyElement()}>
            <ModifyIcon />
          </div>
          {!canModifyItem && !isStatusDeleting && (
            <div
              className="locked-button"
              aria-label={
                isBlacklist
                  ? 'Forbidden words can only be edited offline'
                  : 'You can only edit entries from keys that you own'
              }
              tooltip-position="left"
            >
              <LockIcon />
            </div>
          )}
          <div onClick={() => canModifyItem && deleteElement()}>
            {isStatusDeleting ? (
              <div className="loader loader_on"></div>
            ) : (
              <DeleteIcon />
            )}
          </div>
        </div>
      </div>

      <div
        className={`glossary_item-body${
          !source.note && !target.note
            ? ' glossary_item-body-no-bottom-padding'
            : ''
        }`}
      >
        <div className="glossary-item_column">
          <div className={`glossary_word${config.isSourceRTL ? ' rtl' : ''}`}>
            <span
              className={`${
                highlight && !highlight.isTarget
                  ? ` glossary_word--highlight glossary_word--highlight-${highlight.type}`
                  : ''
              } source_label`}
            >{`${source.term} `}</span>
            {source.sentence && (
              <div
                className="info-icon"
                aria-label={source.sentence}
                tooltip-position="right"
              >
                <InfoIcon size={16} />
              </div>
            )}
          </div>
          {source.note && (
            <div
              className={`glossary-description${
                config.isSourceRTL ? ' rtl' : ''
              }`}
              aria-label={
                toolipsRuleDescription.source ? source.note : undefined
              }
              tooltip-position="right"
            >
              <p>{source.note}</p>
              <p ref={noteDescriptionPlaceholderRef}>{source.note}</p>
            </div>
          )}
        </div>
        <div className="glossary-item_column">
          <div className={`glossary_word${config.isTargetRTL ? ' rtl' : ''}`}>
            <span
              className={`target_label${
                highlight && highlight.isTarget
                  ? ` glossary_word--highlight glossary_word--highlight-${highlight.type}`
                  : ''
              }`}
              onMouseDown={() => onClick && onClick(target.term)}
              aria-label="Click to insert the term in the target segment"
            >{`${target.term} `}</span>
            {isBlacklist && (
              <div className="forbidden-badge">
                <Forbidden /> Forbidden term
              </div>
            )}
            {target.sentence && (
              <div
                className="info-icon"
                aria-label={target.sentence}
                tooltip-position="right"
              >
                <InfoIcon size={16} />
              </div>
            )}
          </div>
          {target.note && (
            <div
              className={`glossary-description${
                config.isTargetRTL ? ' rtl' : ''
              }`}
              aria-label={
                toolipsRuleDescription.target ? target.note : undefined
              }
              tooltip-position="left"
            >
              <p>{target.note}</p>
              {!source.note && (
                <p ref={noteDescriptionPlaceholderRef}>{target.note}</p>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

GlossaryItem.propTypes = {
  item: PropTypes.object.isRequired,
  modifyElement: PropTypes.func.isRequired,
  deleteElement: PropTypes.func.isRequired,
  highlight: PropTypes.oneOfType([PropTypes.object, PropTypes.bool]).isRequired,
  onClick: PropTypes.func,
  isEnabledToModify: PropTypes.bool,
  isStatusDeleting: PropTypes.bool,
  isBlacklist: PropTypes.bool,
}
