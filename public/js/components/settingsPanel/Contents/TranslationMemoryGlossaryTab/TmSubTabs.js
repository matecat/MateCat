import React from 'react'
import PropTypes from 'prop-types'
import {useAreTemplatePropsModified} from '../../Tab'
import {
  TEMPLATE_PROPS_BY_TM_SUBTAB,
  TM_SUBTABS,
} from '../../SettingsPanelConstants'

const SUBTABS = [
  {id: TM_SUBTABS.resourceManagement, label: 'Resource management'},
  {id: TM_SUBTABS.advanced, label: 'Advanced'},
]

const SubTab = ({id, label, isActive, onClick}) => {
  const isModified = useAreTemplatePropsModified(
    TEMPLATE_PROPS_BY_TM_SUBTAB[id],
  )

  return (
    <button
      type="button"
      role="tab"
      className={`translation-memory-glossary-subtab${
        isActive ? ' translation-memory-glossary-subtab-active' : ''
      }`}
      aria-selected={isActive}
      onClick={onClick}
      data-testid={`tm-subtab-${id}`}
    >
      {isModified && (
        <span className="settings-panel-tab-modifyng-icon">●</span>
      )}
      {label}
    </button>
  )
}

SubTab.propTypes = {
  id: PropTypes.string.isRequired,
  label: PropTypes.string.isRequired,
  isActive: PropTypes.bool,
  onClick: PropTypes.func,
}

export const TmSubTabs = ({activeSubTab, onChange}) => (
  <div className="translation-memory-glossary-subtabs" role="tablist">
    {SUBTABS.map(({id, label}) => (
      <SubTab
        key={id}
        id={id}
        label={label}
        isActive={id === activeSubTab}
        onClick={() => onChange(id)}
      />
    ))}
  </div>
)

TmSubTabs.propTypes = {
  activeSubTab: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
}
