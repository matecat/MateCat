import React, {useState} from 'react'
import {TM_SUBTABS} from '../../SettingsPanelConstants'
import {AdvancedSettings} from './AdvancedSettings'
import {ResourceManagement} from './ResourceManagement'
import {TmSubTabs} from './TmSubTabs'

export {
  SPECIAL_ROWS_ID,
  isOwnerOfKey,
  orderTmKeys,
  getTmDataStructureToSendServer,
} from './ResourceManagement'
export {
  getSavedTemplateTm,
  toTemplateTmEntry,
} from './TranslationMemoryGlossaryTabUtils'

export const TranslationMemoryGlossaryTab = () => {
  const [activeSubTab, setActiveSubTab] = useState(
    TM_SUBTABS.resourceManagement,
  )

  // Both panes stay mounted: unmounting the resources would stop a TMX import
  // in progress and drop a resource being created
  return (
    <>
      {!config.is_cattool && (
        <TmSubTabs activeSubTab={activeSubTab} onChange={setActiveSubTab} />
      )}
      <div className="translation-memory-glossary-tab settings-panel-contentwrapper-tab-background">
        {!config.is_cattool && (
          <div
            className="translation-memory-glossary-subtab-panel"
            hidden={activeSubTab !== TM_SUBTABS.advanced}
          >
            <AdvancedSettings />
          </div>
        )}
        <div
          className="translation-memory-glossary-subtab-panel"
          hidden={activeSubTab !== TM_SUBTABS.resourceManagement}
        >
          <ResourceManagement />
        </div>
      </div>
    </>
  )
}
