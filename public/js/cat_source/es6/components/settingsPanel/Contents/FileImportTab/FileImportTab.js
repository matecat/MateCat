import React from 'react'
import {FiltersParams} from './FiltersParams'
import {XliffSettings} from './XliffSettings'

export const FileImportTab = () => {
  return (
    <div className="settings-panel-file-import-tab">
      <FiltersParams />
      <XliffSettings />
    </div>
  )
}
