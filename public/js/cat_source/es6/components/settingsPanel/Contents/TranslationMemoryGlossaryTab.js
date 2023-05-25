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

const DEFAULT_TRANSLATION_MEMORY = {
  id: 'mmSharedKey',
  name: 'MyMemory: Collaborative translation memory shared with all Matecat users.',
  isActive: true,
  isDraggable: false,
  isLocked: true,
  r: true,
  w: true,
}

export const TranslationMemoryGlossaryTab = () => {
  const {tmKeys} = useContext(SettingsPanelContext)

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
    const rows = [DEFAULT_TRANSLATION_MEMORY, ...tmKeys]

    setKeysRows(
      rows.map((row, index) => ({
        node: <TmKeyRow key={index} {...{row}} />,
        isDraggable: row.isDraggable ?? row.isActive,
        isActive: row.isActive,
        isLocked: row.isLocked,
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
          <h2>Active Resources</h2>
          <div>
            <button className="ui primary button">
              <IconAdd /> Add shared resource
            </button>
            <button className="ui primary button">
              <IconAdd /> New resource
            </button>
          </div>
        </div>
        <SettingsPanelTable
          columns={COLUMNS_TABLE}
          rows={keysRows.filter(({isActive}) => isActive)}
          onChangeRowsOrder={onOrderActiveRows}
        />
      </div>
      <div className="translation-memory-glossary-tab-inactive-resources">
        <div className="translation-memory-glossary-tab-table-title">
          <h2>Inactive Resources</h2>
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
