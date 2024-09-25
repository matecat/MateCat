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
      <SegmentationRule
        segmentationRule={segmentationRule}
        setSegmentationRule={setSegmentationRule}
      />
      <FiltersParams />
      <XliffSettings />
    </div>
  )
}
