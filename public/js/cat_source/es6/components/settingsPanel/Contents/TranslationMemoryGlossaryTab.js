import React, {Fragment, useCallback, useState} from 'react'
import {SettingsPanelTable} from '../SettingsPanelTable/SettingsPanelTable'

import IconAdd from '../../icons/IconAdd'

const fakeRows = new Array(10).fill({}).map((item, index) => ({
  node: (
    <Fragment key={index}>
      <span>L{index}</span>
      <span>U{index}</span>
      <span>Key name - {index}</span>
      <span>I</span>
      <button>Import TMX</button>
    </Fragment>
  ),
  isDraggable: true,
}))

const COLUMNS_TABLE = [
  {name: 'Lookup'},
  {name: 'Update'},
  {name: ''},
  {name: ''},
  {name: ''},
]

export const TranslationMemoryGlossaryTab = () => {
  const [keyRows, setKeyRows] = useState(fakeRows)

  const onOrderRows = useCallback(
    ({index, indexToMove}) => {
      const rowSelected = keyRows.find((row, indexRow) => indexRow === index)

      const isLastIndexToMove = indexToMove === keyRows.length

      const orderedRows = keyRows.flatMap((row, indexRow) =>
        indexRow === indexToMove
          ? [rowSelected, row]
          : indexRow === index
          ? []
          : indexRow === keyRows.length - 1 && isLastIndexToMove
          ? [row, rowSelected]
          : row,
      )

      setKeyRows(orderedRows)
    },
    [keyRows],
  )

  return (
    <div className="translation-memory-glossary-tab">
      <div className="translation-memory-glossary-tab-pre-translate">
        <input type="checkbox" />
        Pre-translate 100% matches from TM
      </div>
      <div className="translation-memory-glossary-tab-resource-buttons">
        <span>Active Resources</span>
        <button className="ui primary button">
          <IconAdd /> Add shared resource
        </button>
        <button className="ui primary button">
          <IconAdd /> New resource
        </button>
      </div>
      <SettingsPanelTable
        columns={COLUMNS_TABLE}
        rows={keyRows}
        onChangeRowsOrder={onOrderRows}
      />
    </div>
  )
}
