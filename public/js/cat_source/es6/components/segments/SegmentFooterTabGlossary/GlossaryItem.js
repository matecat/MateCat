import React from 'react'
import PropTypes from 'prop-types'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import {
  DeleteIcon,
  GlossaryDefinitionIcon,
  LockIcon,
  ModifyIcon,
} from './SegmentFooterTabGlossary'

export const GlossaryItem = ({
  item,
  modifyElement,
  deleteElement,
  highlight,
  isEnabledToModify = false,
  isStatusDeleting = false,
}) => {
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
              aria-label="You can only edit entries from keys that you own"
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
            <div className="glossary-description" aria-label={source.note}>
              <p>{source.note}</p>
            </div>
          )}
        </div>
        <div className="glossary-item_column">
          <div className={`glossary_word${config.isTargetRTL ? ' rtl' : ''}`}>
            <span
              className={`${
                highlight && highlight.isTarget
                  ? ` glossary_word--highlight glossary_word--highlight-${highlight.type}`
                  : ''
              }`}
            >{`${target.term} `}</span>
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
            <div className="glossary-description" aria-label={target.note}>
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
  isEnabledToModify: PropTypes.bool,
  isStatusDeleting: PropTypes.bool,
}
