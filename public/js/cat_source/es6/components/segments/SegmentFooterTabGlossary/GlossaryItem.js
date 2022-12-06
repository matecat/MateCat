import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import Forbidden from '../../../../../../img/icons/Forbidden'
import {
  DeleteIcon,
  GlossaryDefinitionIcon,
  LockIcon,
  ModifyIcon,
} from './SegmentFooterTabGlossary'

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
  const [toolipsRuleDescription, setToolipsRuleDescription] = useState({
    source: false,
    target: false,
  })

  useEffect(() => {
    const placeholder = document.createElement('div')
    placeholder.className = 'glossary-description'
    const pTag = document.createElement('p')
    placeholder.appendChild(pTag)
    document.body.appendChild(placeholder)

    const lineHeight = Math.round(
      window.getComputedStyle(pTag).lineHeight.split('px')[0],
    )

    pTag.innerText = item?.source?.note ?? ''
    const source =
      pTag.offsetHeight / lineHeight > DESCRIPTION_ELEMENTS_LINE_CLAMP

    pTag.innerText = item?.target?.note ?? ''
    const target =
      pTag.offsetHeight / lineHeight > DESCRIPTION_ELEMENTS_LINE_CLAMP

    console.log('target', pTag.offsetHeight, pTag.innerText)
    document.body.removeChild(placeholder)

    const newState = {
      source,
      target,
    }
    console.log('---->', newState)
    setToolipsRuleDescription(newState)
  }, [item?.source?.note, item?.target?.note])

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
            <span className="glossary_badge">{metadata.domain}</span>
          )}
          {metadata.subdomain && (
            <span className="glossary_badge">{metadata.subdomain}</span>
          )}
          <div className="glossary_source">
            <b>
              {metadata.key_name
                ? metadata.key_name
                : `No name (${metadata.key})`}
            </b>
            <span>{metadata.last_update}</span>
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
              }`}
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
            >
              <p>{source.note}</p>
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
            >
              <p>{target.note}</p>
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
