import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {MMTOptions} from './MMTOptions'
import {LaraOptions} from './LaraOptions'
import {DeepLOptions} from './DeepLOptions'
import {BasicOptions} from './BasicOptions'
import {IntentoOptions} from './IntentoOptions'
import IconDown from '../../../icons/IconDown'

const ContentComponent = {
  MMT: MMTOptions,
  Lara: LaraOptions,
  DeepL: DeepLOptions,
  Intento: IntentoOptions,
}

export const OptionsContainer = ({activeMTEngineData, isCattoolPage}) => {
  const [isExpanded, setIsExpanded] = useState(false)

  const OptionsContent = ContentComponent[activeMTEngineData.engine_type]
    ? ContentComponent[activeMTEngineData.engine_type]
    : BasicOptions

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
          <IconDown size={16} />
          Options
        </button>
      </div>
      {isExpanded && OptionsContent && (
        <OptionsContent id={activeMTEngineData.id} {...{isCattoolPage}} />
      )}
    </div>
  )
}

OptionsContainer.propTypes = {
  activeMTEngineData: PropTypes.object.isRequired,
  isCattoolPage: PropTypes.bool,
}
