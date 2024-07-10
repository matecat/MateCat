import React, {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable/SettingsPanelTable'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {TMKeyRow} from './TMKeyRow'
import {TMCreateResourceRow} from './TMCreateResourceRow'
import {updateJobKeys} from '../../../../api/updateJobKeys'

import Users from '../../../../../../../img/icons/Users'
import AddWide from '../../../../../../../img/icons/AddWide'
import CatToolActions from '../../../../actions/CatToolActions'

const COLUMNS_TABLE_ACTIVE = [
  {name: 'Lookup'},
  {name: 'Update'},
  {name: ''},
  {name: ''},
  {name: ''},
]

const COLUMNS_TABLE_INACTIVE = [
  {name: 'Activate'},
  {name: ''},
  {name: 'Name'},
  {name: 'Key'},
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

export const isOwnerOfKey = (key) => !/[*]/g.test(key)

export const orderTmKeys = (tmKeys, keysOrdered) => {
  const order = (acc, cur) => {
    const copyAcc = [...acc]
    const index = keysOrdered.findIndex((key) => key === cur.key)

    if (index >= 0) {
      const previousItem = copyAcc[index]
      copyAcc[index] = cur
      if (previousItem) copyAcc.push(previousItem)
    } else {
      copyAcc.push(cur)
    }
    return copyAcc
  }
  return Array.isArray(keysOrdered)
    ? tmKeys.reduce(order, []).filter((row) => row)
    : tmKeys
}

export const getTmDataStructureToSendServer = ({tmKeys = [], keysOrdered}) => {
  const mine = tmKeys
    .filter(({key, isActive}) => isOwnerOfKey(key) && isActive)
    .map(({tm, glos, key, name, r, w}) => ({tm, glos, key, name, r, w}))

  return JSON.stringify({
    ownergroup: [],
    mine: orderTmKeys(mine, keysOrdered),
    anonymous: [],
  })
}

export const TranslationMemoryGlossaryTabContext = createContext({})

export const TranslationMemoryGlossaryTab = () => {
  const {
    tmKeys,
    setTmKeys,
    openLoginModal,
    modifyingCurrentTemplate,
    currentProjectTemplate,
  } = useContext(SettingsPanelContext)

  const getPublicMatches = currentProjectTemplate.getPublicMatches
  const isPretranslate100Active = currentProjectTemplate.pretranslate100
  const setIsPretranslate100Active = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      pretranslate100: value,
    }))
  const [specialRows, setSpecialRows] = useState([
    {
      ...DEFAULT_TRANSLATION_MEMORY,
      r: getPublicMatches,
    },
  ])
  const [keyRows, setKeyRows] = useState([])
  const [filterInactiveKeys, setFilterInactiveKeys] = useState('')

  const ref = useRef()
  const previousStatesRef = useRef({
    tmKeys: undefined,
    getPublicMatches: undefined,
    currentProjectTemplate: undefined,
  })

  previousStatesRef.current.currentProjectTemplate = currentProjectTemplate

  // Sync tmKeys state when current project template changed in homepage
  useEffect(() => {
    if (config.is_cattool) return

    const tm = currentProjectTemplate?.tm ?? []

    setTmKeys((prevState) =>
      prevState.map((tmItem) => {
        const tmFromTemplate = tm.find(({key}) => key === tmItem.key)
        return {
          ...tmItem,
          r: false,
          w: false,
          isActive: false,
          ...(tmFromTemplate && {...tmFromTemplate, isActive: true}),
        }
      }),
    )
  }, [currentProjectTemplate?.tm, setTmKeys])

  const onOrderActiveRows = ({index, indexToMove}) => {
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

    setKeyRows([...keyRows.filter(({isActive}) => !isActive), ...orderedRows])
    const keysOrdered = orderedRows.map(({key}) => key).filter((key) => key)

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      tm: orderTmKeys(tmKeys, keysOrdered)
        .filter(({isActive}) => isActive)
        .map(({id, isActive, ...rest}) => rest), // eslint-disable-line
    }))

    // Cattol page updateJobKeys
    if (config.is_cattool) {
      updateJobKeys({
        getPublicMatches,
        dataTm: getTmDataStructureToSendServer({tmKeys, keysOrdered}),
      }).then(() => CatToolActions.onTMKeysChangeStatus())
    }
  }

  useEffect(() => {
    setSpecialRows((prevState) =>
      prevState.map((row) => ({
        ...row,
        r:
          row.id === SPECIAL_ROWS_ID.defaultTranslationMemory
            ? getPublicMatches
            : row.r,
      })),
    )
  }, [getPublicMatches])

  useEffect(() => {
    const tmCurrentProjectTemplate =
      previousStatesRef.current.currentProjectTemplate.tm

    const getTmKeysOrderedByTemplate = () => {
      const tmCurrentTemplate = tmCurrentProjectTemplate
      return Array.isArray(tmCurrentTemplate)
        ? orderTmKeys(
            tmKeys,
            tmCurrentTemplate.map(({key}) => key),
          )
        : tmKeys
    }

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
        ...(getTmKeysOrderedByTemplate() ?? []),
        ...(createResourceRow ? [createResourceRow] : []),
      ]

      // preserve rows order
      const rowsActive = allRows
        .filter(({isActive}) => isActive)
        .reduce((acc, cur) => {
          const copyAcc = [...acc]
          const index = [
            defaultTranslationMemoryRow,
            ...tmCurrentProjectTemplate,
          ].findIndex(({id}) => id === cur.id)

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
        const {id, key, name, isActive, isLocked} = row
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
          isDraggable: isActive && !isSpecialRow && isOwnerOfKey(key),
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

  // Update job keys
  useEffect(() => {
    if (!config.is_cattool) return

    const {current} = previousStatesRef
    if (typeof current.tmKeys === 'object') {
      const tmKeysActive = tmKeys.filter(({isActive}) => isActive)
      const prevTmKeysActive = current.tmKeys.filter(({isActive}) => isActive)

      const shouldUpdateTmKeysJob =
        getPublicMatches !== current.getPublicMatches ||
        tmKeysActive.length !== prevTmKeysActive.length ||
        tmKeysActive
          .filter(({key}) => isOwnerOfKey(key))
          .some(({key, r, w}) => {
            const prevTm = prevTmKeysActive.find((prev) => prev.key === key)
            return prevTm && (r !== prevTm.r || w !== prevTm.w)
          })

      if (shouldUpdateTmKeysJob) {
        updateJobKeys({
          getPublicMatches,
          dataTm: getTmDataStructureToSendServer({tmKeys}),
        }).then(() => CatToolActions.onTMKeysChangeStatus())
      }
    }

    current.tmKeys = tmKeys
    current.getPublicMatches = getPublicMatches
  }, [tmKeys, getPublicMatches])

  const onAddSharedResource = () =>
    setSpecialRows([
      {...DEFAULT_TRANSLATION_MEMORY, r: getPublicMatches},
      ADD_SHARED_RESOURCE,
    ])
  const onNewResource = () =>
    setSpecialRows([
      {...DEFAULT_TRANSLATION_MEMORY, r: getPublicMatches},
      NEW_RESOURCE,
    ])

  const inactiveKeys = keyRows.filter(
    ({isActive, name}) =>
      !isActive &&
      (filterInactiveKeys
        ? new RegExp(filterInactiveKeys, 'gi').test(name)
        : true),
  )

  return (
    <TranslationMemoryGlossaryTabContext.Provider value={{ref, setSpecialRows}}>
      <div
        ref={ref}
        className="translation-memory-glossary-tab settings-panel-contentwrapper-tab-background"
      >
        {!config.is_cattool && (
          <div className="translation-memory-glossary-tab-pre-translate">
            <input
              checked={isPretranslate100Active}
              onChange={(e) =>
                setIsPretranslate100Active(e.currentTarget.checked)
              }
              type="checkbox"
              data-testid="pretranslate-checkbox"
            />
            Pre-translate 100% matches from TM
          </div>
        )}
        <div className="translation-memory-glossary-tab-active-resources">
          <div className="translation-memory-glossary-tab-table-title">
            <h2>Active Resources</h2>
            <div className="translation-memory-glossary-tab-buttons-group">
              {config.isLoggedIn && (
                <button
                  className="ui primary button settings-panel-button-icon"
                  onClick={onAddSharedResource}
                  data-testid="add-shared-resource-tm"
                >
                  <Users size={18} /> Add shared resource
                </button>
              )}

              <button
                className="ui primary button settings-panel-button-icon"
                onClick={onNewResource}
                data-testid="new-resource-tm"
              >
                <AddWide size={18} /> New resource
              </button>
            </div>
          </div>
          <SettingsPanelTable
            columns={COLUMNS_TABLE_ACTIVE}
            rows={keyRows.filter(({isActive}) => isActive)}
            onChangeRowsOrder={onOrderActiveRows}
          />
        </div>
        <div className="translation-memory-glossary-tab-inactive-resources">
          <div className="translation-memory-glossary-tab-table-title">
            <h2>Inactive Resources</h2>
            <input
              className="translation-memory-glossary-tab-input-text"
              placeholder="Search resources"
              value={filterInactiveKeys}
              onChange={(e) => setFilterInactiveKeys(e.currentTarget.value)}
              data-testid="search-inactive-tmkeys"
            />
          </div>
          {config.isLoggedIn ? (
            <SettingsPanelTable
              className="translation-memory-glossary-tab-inactive-table"
              columns={COLUMNS_TABLE_INACTIVE}
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
