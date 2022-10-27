import React from 'react'
import InfoIcon from '../../../../../../img/icons/InfoIcon'
import {
  DeleteIcon,
  GlossaryDefinitionIcon,
  ModifyIcon,
} from './SegmentFooterTabGlossary'

export const GlossaryItem = ({
  item,
  modifyElement,
  deleteElement,
  highlight,
  isEnabledToModify = false,
}) => {
  const {metadata, source, target} = item

  const canModifyItem = isEnabledToModify && item.term_id

  return (
    <div className="glossary_item">
      <div className={'glossary_item-header'}>
        <div className={'glossary_definition-container'}>
          <span
            className={`glossary_definition${
              !metadata.definition ? ' glossary_definition--hidden' : ''
            }`}
          >
            <GlossaryDefinitionIcon />
            {metadata.definition}
          </span>
          <span className={'glossary_badge'}>{metadata.domain}</span>
          <span className={'glossary_badge'}>{metadata.subdomain}</span>
          <div className={'glossary_source'}>
            <b>{metadata.key_name}</b>
            <span>{metadata.last_update}</span>
          </div>
        </div>
        <div
          className={`glossary_item-actions${
            !canModifyItem ? ' glossary_item-actions--disabled' : ''
          }`}
        >
          <div onClick={() => canModifyItem && modifyElement()}>
            <ModifyIcon />
          </div>
          <div onClick={() => canModifyItem && deleteElement()}>
            <DeleteIcon />
          </div>
        </div>
      </div>

      <div className={'glossary_item-body'}>
        {!item.term_id && <span className="loader loader_on"></span>}
        <div className={'glossary-item_column'}>
          <div className="glossary_word">
            <span
              className={`${
                highlight && !highlight.isTarget
                  ? ` glossary_word--highlight glossary_word--highlight-${highlight.type}`
                  : ''
              }`}
            >{`${source.term} `}</span>
            <div>
              <InfoIcon size={16} />
              {source.sentence && (
                <div className={'glossary_item-tooltip'}>{source.sentence}</div>
              )}
            </div>
          </div>
          <div className={'glossary-description'}>{source.note}</div>
        </div>
        <div className={'glossary-item_column'}>
          <div className="glossary_word">
            <span
              className={`${
                highlight && highlight.isTarget
                  ? ` glossary_word--highlight glossary_word--highlight-${highlight.type}`
                  : ''
              }`}
            >{`${target.term} `}</span>
            <div>
              <InfoIcon size={16} />
              {target.sentence && (
                <div className={'glossary_item-tooltip'}>{target.sentence}</div>
              )}
            </div>
          </div>
          <div className={'glossary-description'}>{target.note}</div>
        </div>
      </div>
    </div>
  )
}
