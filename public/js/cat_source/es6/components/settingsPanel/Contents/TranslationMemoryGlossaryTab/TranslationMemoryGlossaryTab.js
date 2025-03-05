import React, {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable/SettingsPanelTable'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper'
import {TMKeyRow} from './TMKeyRow'
import {TMCreateResourceRow} from './TMCreateResourceRow'
import CatToolActions from '../../../../actions/CatToolActions'
import SegmentActions from '../../../../actions/SegmentActions'
import SegmentStore from '../../../../stores/SegmentStore'
import {updateJobKeys} from '../../../../api/updateJobKeys'
import Users from '../../../../../../../img/icons/Users'
import AddWide from '../../../../../../../img/icons/AddWide'
import {METADATA_KEY} from '../../../../constants/Constants'
import {updateJobMetadata} from '../../../../api/updateJobMetadata/updateJobMetadata'

const COLUMNS_TABLE_ACTIVE = [
  {name: 'Lookup'},
  {name: 'Update'},
  {name: ''},
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
    .map(({tm, glos, key, name, r, w, penalty}) => ({
      tm,
      glos,
      key,
      name,
      r,
      w,
      penalty,
    }))

  return JSON.stringify({
    ownergroup: [],
    mine: orderTmKeys(mine, keysOrdered),
    anonymous: [],
  })
}

export const TranslationMemoryGlossaryTabContext = createContext({})

export const TranslationMemoryGlossaryTab = () => {
  const {tmKeys, setTmKeys, modifyingCurrentTemplate, currentProjectTemplate} =
    useContext(SettingsPanelContext)
  const {userInfo} = useContext(ApplicationWrapperContext)
  const getPublicMatches = currentProjectTemplate.getPublicMatches
  const isPretranslate100Active = currentProjectTemplate.pretranslate100
  const setIsPretranslate100Active = (value) =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      pretranslate100: value,
    }))
  const tmPrioritization = currentProjectTemplate.tmPrioritization
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
    tmPrioritization: undefined,
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
          penalty: 0,
          ...(tmFromTemplate && {
            ...tmFromTemplate,
            isActive: true,
          }),
          name: tmItem.name,
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
      }).then(() => {
        CatToolActions.onTMKeysChangeStatus()
        SegmentActions.getContributions(
          SegmentStore.getCurrentSegmentId(),
          userInfo.metadata[METADATA_KEY],
          true,
        )
      })
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
                ? 'settings-panel-row-active row-content-create-resource'
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
          .some(({key, r, w, penalty}) => {
            const prevTm = prevTmKeysActive.find((prev) => prev.key === key)
            return (
              prevTm &&
              (r !== prevTm.r || w !== prevTm.w || penalty !== prevTm.penalty)
            )
          }) ||
        tmPrioritization !== current.tmPrioritization

      if (shouldUpdateTmKeysJob) {
        const tmCurrentProjectTemplate =
          previousStatesRef.current.currentProjectTemplate.tm

        const keysOrdered = tmCurrentProjectTemplate.map(({key}) => key)

        updateJobKeys({
          getPublicMatches,
          dataTm: getTmDataStructureToSendServer({tmKeys, keysOrdered}),
        }).then(() => {
          CatToolActions.onTMKeysChangeStatus()
          SegmentActions.getContributions(
            SegmentStore.getCurrentSegmentId(),
            userInfo.metadata[METADATA_KEY],
            true,
          )
        })
      }

      if (tmPrioritization !== current.tmPrioritization) {
        updateJobMetadata({
          tmPrioritization,
        })
      }
    }

    current.tmKeys = tmKeys
    current.getPublicMatches = getPublicMatches
    current.tmPrioritization = tmPrioritization
  }, [tmKeys, getPublicMatches, tmPrioritization])

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
              <button
                className="ui primary button settings-panel-button-icon"
                onClick={onAddSharedResource}
                data-testid="add-shared-resource-tm"
              >
                <Users size={18} /> Add shared resource
              </button>

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
            className="translation-memory-glossary-tab-active-table"
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
          <SettingsPanelTable
            columns={COLUMNS_TABLE_INACTIVE}
            rows={inactiveKeys}
          />
        </div>
      </div>
    </TranslationMemoryGlossaryTabContext.Provider>
  )
}
