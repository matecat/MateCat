import React, {useCallback, useContext, useEffect, useState} from 'react'
import {SettingsPanelTable} from '../SettingsPanelTable/SettingsPanelTable'
import {SettingsPanelContext} from '../SettingsPanelContext'
import {TMKeyRow} from './TMKeyRow/TMKeyRow'
import {TMCreateResourceRow} from './TMKeyRow/TMCreateResourceRow'

import IconAdd from '../../icons/IconAdd'

const COLUMNS_TABLE = [
  {name: 'Lookup'},
  {name: 'Update'},
  {name: ''},
  {name: ''},
  {name: ''},
]

export const SPECIAL_ROWS_ID = {
  defaultTranslationMemory: 'mmSharedKey',
  addSharedResource: 'addSharedResource',
  newResource: 'newResource',
}

const DEFAULT_TRANSLATION_MEMORY = {
  id: SPECIAL_ROWS_ID.defaultTranslationMemory,
  name: 'MyMemory: Collaborative translation memory shared with all Matecat users.',
  isActive: true,
  isDraggable: false,
  isLocked: true,
  r: true,
  w: true,
}

const ADD_SHARED_RESOURCE = {
  id: SPECIAL_ROWS_ID.addSharedResource,
  isActive: true,
  isLocked: true,
  r: true,
  w: true,
}

export const TranslationMemoryGlossaryTab = () => {
  const {tmKeys} = useContext(SettingsPanelContext)

  const [specialRows, setSpecialRows] = useState([DEFAULT_TRANSLATION_MEMORY])
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
      const defaultTranslationMemoryRow = specialRows.find(
        ({id}) => id === SPECIAL_ROWS_ID.defaultTranslationMemory,
      )
      const createResourceRow = specialRows.find(
        ({id}) =>
          id === SPECIAL_ROWS_ID.addSharedResource ||
          id === SPECIAL_ROWS_ID.newResource,
      )

      const allRows = [
        defaultTranslationMemoryRow,
        ...tmKeys,
        ...(createResourceRow ? [createResourceRow] : []),
      ]

      // preserve rows order
      const rowsActive = allRows
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

      const rowsNotActive = allRows.filter(({isActive}) => !isActive)

      return [...rowsActive, ...rowsNotActive].map((row) => {
        const prevStateRow = prevState.find(({id}) => id === row.id) ?? {}
        const {id, isActive, isLocked} = row
        const {isExpanded} = prevStateRow

        const isCreateResourceRow =
          id === SPECIAL_ROWS_ID.addSharedResource ||
          id === SPECIAL_ROWS_ID.newResource

        const isSpecialRow = Object.values(SPECIAL_ROWS_ID).some(
          (value) => value === id,
        )

        return {
          id,
          isDraggable: isActive && !isSpecialRow,
          isActive,
          isLocked,
          isExpanded,
          node: !isCreateResourceRow ? (
            <TMKeyRow key={row.id} {...{row, onExpandRow, setSpecialRows}} />
          ) : (
            <TMCreateResourceRow key={row.id} {...{row, setSpecialRows}} />
          ),
        }
      })
    })
  }, [tmKeys, specialRows])

  const onAddSharedResource = () =>
    setSpecialRows([DEFAULT_TRANSLATION_MEMORY, ADD_SHARED_RESOURCE])

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
            <button className="ui primary button" onClick={onAddSharedResource}>
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
