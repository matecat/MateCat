import React from 'react'
import {FiltersParams} from './FiltersParams/FiltersParams'
import {XliffSettings} from './XliffSettings/XliffSettings'

export const FileImportTab = () => {
  return (
    <div className="settings-panel-file-import-tab">
      <FiltersParams />
      <XliffSettings />
    </div>
  )
}
