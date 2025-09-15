import React, {useState} from 'react'
import PropTypes from 'prop-types'
import ArrowDown from '../../../../../img/icons/ArrowDown'
import {MMTOptions} from './MMTOptions'

const ContentComponent = {
  MMT: MMTOptions,
}

export const OptionsContainer = ({activeMTEngineData, isCattoolPage}) => {
  const [isExpanded, setIsExpanded] = useState(false)

  const OptionsContent = ContentComponent[activeMTEngineData.engine_type]

  return (
    <div
      className={`options-container ${isExpanded ? 'options-container-expanded' : ''}`}
    >
      <div className="expand-button">
        <button
          className={`${isExpanded ? 'rotate' : ''}`}
          onClick={() => setIsExpanded((prevState) => !prevState)}
          title="Glossary options"
        >
          <ArrowDown />
          Options
        </button>
      </div>
      {isExpanded && OptionsContent && <OptionsContent />}
    </div>
  )
}

OptionsContainer.propTypes = {
  activeMTEngineData: PropTypes.object.isRequired,
  isCattoolPage: PropTypes.bool,
}
