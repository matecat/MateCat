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
  const {tmKeys, setTmKeys} = useContext(SettingsPanelContext)

  const [keysRows, setKeysRows] = useState([])

  const onOrderActiveRows = useCallback(
    ({index, indexToMove}) => {
      const activeRows = keysRows.filter(({isActive}) => isActive)
      const rowSelected = activeRows.find((row, indexRow) => indexRow === index)

      const isLastIndexToMove = indexToMove === activeRows.length

      const orderedRows = activeRows.flatMap((row, indexRow) =>
        indexRow === indexToMove
          ? [rowSelected, row]
          : indexRow === index
          ? []
          : indexRow === activeRows.length - 1 && isLastIndexToMove
          ? [row, rowSelected]
          : row,
      )

      setKeysRows((prevState) => [
        ...prevState.filter(({isActive}) => !isActive),
        ...orderedRows,
      ])
    },
    [keysRows],
  )

  useEffect(() => {
    if (!tmKeys) return
    setKeysRows(
      tmKeys.map((row, index) => ({
        node: <TmKeyRow key={index} {...{row}} />,
        isDraggable: row.isActive,
        isActive: row.isActive,
      })),
    )
  }, [tmKeys])

  return (
    <div className="translation-memory-glossary-tab">
      <div className="translation-memory-glossary-tab-pre-translate">
        <input type="checkbox" />
        Pre-translate 100% matches from TM
      </div>
      <div className="translation-memory-glossary-tab-active-resources">
        <div className="translation-memory-glossary-tab-table-title">
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
          rows={keysRows.filter(({isActive}) => isActive)}
          onChangeRowsOrder={onOrderActiveRows}
        />
      </div>
      <div className="translation-memory-glossary-tab-inactive-resources">
        <div className="translation-memory-glossary-tab-table-title">
          <span>Inactive Resources</span>
        </div>
        <SettingsPanelTable
          className="translation-memory-glossary-tab-inactive-table"
          columns={COLUMNS_TABLE}
          rows={keysRows.filter(({isActive}) => !isActive)}
        />
      </div>
    </div>
  )
}
