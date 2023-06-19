import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable/SettingsPanelTable'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {TMKeyRow} from './TMKeyRow'
import {TMCreateResourceRow} from './TMCreateResourceRow'

import Users from '../../../../../../../img/icons/Users'
import AddWide from '../../../../../../../img/icons/AddWide'
import {MessageNotification} from '../MessageNotification'

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
  key: '',
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

const NEW_RESOURCE = {
  id: SPECIAL_ROWS_ID.newResource,
  isActive: true,
  isLocked: true,
  r: true,
  w: true,
}

export const TranslationMemoryGlossaryTabContext = createContext({})

export const TranslationMemoryGlossaryTab = () => {
  const {tmKeys, openLoginModal} = useContext(SettingsPanelContext)

  const [specialRows, setSpecialRows] = useState([
    {...DEFAULT_TRANSLATION_MEMORY, r: Boolean(config.get_public_matches)},
  ])
  const [keyRows, setKeyRows] = useState([])
  const [filterInactiveKeys, setFilterInactiveKeys] = useState('')
  const [notification, setNotification] = useState({})

  const ref = useRef()

  const onOrderActiveRows = useCallback(
    ({index, indexToMove}) => {
      const activeRows = keyRows.filter(({isActive}) => isActive)
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

      setKeyRows((prevState) => [
        ...prevState.filter(({isActive}) => !isActive),
        ...orderedRows,
      ])
    },
    [keyRows],
  )

  useEffect(() => {
    const onExpandRow = ({row, shouldExpand, content}) =>
      setKeyRows((prevState) =>
        prevState.map((item) =>
          item.id === row.id
            ? {
                ...item,
                isExpanded: shouldExpand,
                ...(shouldExpand && {extraNode: content}),
              }
            : item,
        ),
      )

    setKeyRows((prevState) => {
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
        ...(tmKeys ?? []),
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
        const {id, key, name, owner, isActive, isLocked} = row
        const {isExpanded, extraNode} = prevStateRow

        const isCreateResourceRow =
          id === SPECIAL_ROWS_ID.addSharedResource ||
          id === SPECIAL_ROWS_ID.newResource

        const isSpecialRow = Object.values(SPECIAL_ROWS_ID).some(
          (value) => value === id,
        )

        return {
          id,
          key,
          name,
          isDraggable: isActive && !isSpecialRow && owner,
          isActive,
          isLocked,
          isExpanded,
          className:
            id === SPECIAL_ROWS_ID.defaultTranslationMemory
              ? 'row-content-default-memory'
              : id === SPECIAL_ROWS_ID.addSharedResource ||
                id === SPECIAL_ROWS_ID.newResource
              ? 'row-content-create-resource'
              : '',
          node: !isCreateResourceRow ? (
            <TMKeyRow key={row.id} {...{row, onExpandRow}} />
          ) : (
            <TMCreateResourceRow key={row.id} {...{row}} />
          ),
          ...(extraNode && {extraNode}),
        }
      })
    })
  }, [tmKeys, specialRows])

  const onAddSharedResource = () =>
    setSpecialRows([DEFAULT_TRANSLATION_MEMORY, ADD_SHARED_RESOURCE])
  const onNewResource = () =>
    setSpecialRows([DEFAULT_TRANSLATION_MEMORY, NEW_RESOURCE])

  const inactiveKeys = keyRows.filter(
    ({isActive, name}) =>
      !isActive &&
      (filterInactiveKeys
        ? new RegExp(filterInactiveKeys, 'gi').test(name)
        : true),
  )

  const resetNotification = () => setNotification({})

  const {type, message, rowKey} = notification

  const isActiveResourceNotification = keyRows.some(
    (row) => row.key === rowKey && row.isActive,
  )

  const activeResourcersNotification = message &&
    isActiveResourceNotification && (
      <MessageNotification
        {...{type, message, closeCallback: resetNotification}}
      />
    )
  const inactiveResourcersNotification = message &&
    !isActiveResourceNotification && (
      <MessageNotification
        {...{type, message, closeCallback: resetNotification}}
      />
    )

  return (
    <TranslationMemoryGlossaryTabContext.Provider
      value={{ref, setSpecialRows, setNotification}}
    >
      <div ref={ref} className="translation-memory-glossary-tab">
        <div className="translation-memory-glossary-tab-pre-translate">
          <input type="checkbox" />
          Pre-translate 100% matches from TM
        </div>
        <div className="translation-memory-glossary-tab-active-resources">
          {activeResourcersNotification}
          <div className="translation-memory-glossary-tab-table-title">
            <h2>Active Resources</h2>
            <div className="translation-memory-glossary-tab-buttons-group">
              {config.isLoggedIn && (
                <button
                  className="ui primary button settings-panel-button-icon"
                  onClick={onAddSharedResource}
                >
                  <Users size={18} /> Add shared resource
                </button>
              )}

              <button
                className="ui primary button settings-panel-button-icon"
                onClick={onNewResource}
              >
                <AddWide size={18} /> New resource
              </button>
            </div>
          </div>
          <SettingsPanelTable
            columns={COLUMNS_TABLE}
            rows={keyRows.filter(({isActive}) => isActive)}
            onChangeRowsOrder={onOrderActiveRows}
          />
        </div>
        <div className="translation-memory-glossary-tab-inactive-resources">
          {inactiveResourcersNotification}
          <div className="translation-memory-glossary-tab-table-title">
            <h2>Inactive Resources</h2>
            <input
              className="translation-memory-glossary-tab-input-text"
              placeholder="Search resources"
              value={filterInactiveKeys}
              onChange={(e) => setFilterInactiveKeys(e.currentTarget.value)}
            />
          </div>
          {config.isLoggedIn ? (
            <SettingsPanelTable
              className="translation-memory-glossary-tab-inactive-table"
              columns={COLUMNS_TABLE}
              rows={inactiveKeys}
            />
          ) : (
            <button className="ui primary button" onClick={openLoginModal}>
              Login to see your TM
            </button>
          )}
        </div>
      </div>
    </TranslationMemoryGlossaryTabContext.Provider>
  )
}
