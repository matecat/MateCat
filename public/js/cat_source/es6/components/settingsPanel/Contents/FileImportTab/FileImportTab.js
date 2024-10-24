import React, {useCallback, useContext} from 'react'
import {FiltersParams} from './FiltersParams/FiltersParams'
import {XliffSettings} from './XliffSettings/XliffSettings'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {SegmentationRule} from './SegmentationRule'

export const FileImportTab = () => {
  const {modifyingCurrentTemplate, currentProjectTemplate} =
    useContext(SettingsPanelContext)

  // Segmentation rule
  const segmentationRule = currentProjectTemplate.segmentationRule
  const setSegmentationRule = useCallback(
    (value) =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        segmentationRule: value,
      })),
    [modifyingCurrentTemplate],
  )

  return (
    <div className="settings-panel-file-import-tab">
      <div className="settings-panel-box">
        <div className="file-import-tab file-import-options-box settings-panel-contentwrapper-tab-background">
          <SegmentationRule
            segmentationRule={segmentationRule}
            setSegmentationRule={setSegmentationRule}
          />
        </div>
      </div>
      <FiltersParams />
      <XliffSettings />
    </div>
  )
}
