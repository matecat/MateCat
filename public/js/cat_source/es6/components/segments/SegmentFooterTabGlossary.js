/**
 * React Component .

 */
import React, {useState} from 'react'

export const SegmentFooterTabGlossary = ({active_class}) => {
  const [searchSource, setSearchSource] = useState()
  const [searchTarget, setSearchTarget] = useState()
  return (
    <div className={`tab sub-editor glossary ${active_class}`}>
      <div className={'glossary_search'}>
        <div className={'glossary_search-source'}>
          <input
            className={'glossary_search-input'}
            placeholder={'Search source'}
            onChange={(event) => setSearchSource(event.target.value)}
          />
        </div>
        <div className={'glossary_search-target'}>
          <input
            className={'glossary_search-input'}
            placeholder={'Search target'}
            onChange={(event) => setSearchTarget(event.target.value)}
          />
          <button className={'glossary__button-add'}>+ Add Term</button>
        </div>
      </div>
      <div className={'glossary_items'}>
        <div className={'glossary_item'}>
          <div className={'glossary_item-header'}>
            <div className={'glossary_definition-container'}>
              <span className={'glossary_badge'}>Uber</span>
              <span className={'glossary_badge'}>Rider</span>
              <span className={'glossary_definition'}>
                The action or process of paying someone or something or of being
                paid.
              </span>
              <div className={'glossary_source'}>
                Source: <b>Uber Glossary</b> 2022-07-08
              </div>
            </div>
            <div className={'glossary_item-actions'}>
              <span>Mod</span>
              <span>del</span>
            </div>
          </div>
          <div className={'glossary_item-body'}></div>
        </div>
      </div>
    </div>
  )
}
