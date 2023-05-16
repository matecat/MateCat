import React, {useCallback, useContext, useEffect, useState} from 'react'
import {SettingsPanelTable} from '../SettingsPanelTable/SettingsPanelTable'
import {SettingsPanelContext} from '../SettingsPanelContext'
import {TmKeyRow} from './TmKeyRow'

import IconAdd from '../../icons/IconAdd'

const COLUMNS_TABLE = [
  {name: 'Lookup'},
  {name: 'Update'},
  {name: ''},
  {name: ''},
  {name: ''},
]

export const TranslationMemoryGlossaryTab = () => {
  const {tmKeys} = useContext(SettingsPanelContext)

  const [keysRows, setKeysRows] = useState([])

  const onOrderRows = useCallback(
    ({index, indexToMove}) => {
      const rowSelected = keysRows.find((row, indexRow) => indexRow === index)

      const isLastIndexToMove = indexToMove === keysRows.length

      const orderedRows = keysRows.flatMap((row, indexRow) =>
        indexRow === indexToMove
          ? [rowSelected, row]
          : indexRow === index
          ? []
          : indexRow === keysRows.length - 1 && isLastIndexToMove
          ? [row, rowSelected]
          : row,
      )

      setKeysRows(orderedRows)
    },
    [keysRows],
  )

  useEffect(() => {
    if (!tmKeys) return

    setKeysRows(
      tmKeys.map((row, index) => ({
        node: <TmKeyRow key={index} {...{row}} />,
        isDraggable: true,
      })),
    )
  }, [tmKeys])

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
        rows={keysRows}
        onChangeRowsOrder={onOrderRows}
      />
    </div>
  )
}
