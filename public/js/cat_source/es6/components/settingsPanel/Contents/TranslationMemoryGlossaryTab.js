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

    const onExpandRow = ({row, shouldExpand}) =>
      setKeysRows((prevState) =>
        prevState.map((item) =>
          item.id === row.id ? {...item, isExpanded: shouldExpand} : item,
        ),
      )

    setKeysRows((prevState) => {
      const rows = [DEFAULT_TRANSLATION_MEMORY, ...tmKeys]
      // preserve rows order
      const rowsActive = rows
        .filter(({isActive}) => isActive)
        .reduce((acc, cur) => {
          const copyAcc = [...acc]
          const index = prevState
            .filter(({isActive}) => isActive)
            .findIndex(({id}) => id === cur.id)

          if (index >= 0) {
            const previousItem = copyAcc[index]
            copyAcc[index] = cur
            if (previousItem) copyAcc.push(previousItem)
          } else {
            copyAcc.push(cur)
          }
          return copyAcc
        }, [])
        .filter((row) => row)

      const rowsNotActive = rows.filter(({isActive}) => !isActive)

      return [...rowsActive, ...rowsNotActive].map((row) => {
        const prevStateRow = prevState.find(({id}) => id === row.id) ?? {}
        const {id, isActive, isLocked} = row
        const {isExpanded} = prevStateRow
        return {
          id,
          isDraggable: isActive,
          isActive,
          isLocked,
          isExpanded,
          node: <TmKeyRow key={row.id} {...{row, onExpandRow}} />,
        }
      })
    })
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
