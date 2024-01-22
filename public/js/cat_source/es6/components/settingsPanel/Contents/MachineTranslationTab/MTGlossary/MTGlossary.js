import React, {useCallback, useContext, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useState} from 'react'
import {SettingsPanelTable} from '../../../SettingsPanelTable'
import {MTGlossaryRow} from './MTGlossaryRow'
import {MTGlossaryCreateRow} from './MTGlossaryCreateRow'
import {getMMTKeys} from '../../../../../api/getMMTKeys/getMMTKeys'
import {getStatusMemoryGlossaryImport} from '../../../../../api/getStatusMemoryGlossaryImport/getStatusMemoryGlossaryImport'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import CatToolActions from '../../../../../actions/CatToolActions'
import CatToolConstants from '../../../../../constants/CatToolConstants'
import CatToolStore from '../../../../../stores/CatToolStore'
import ArrowDown from '../../../../../../../../img/icons/ArrowDown'
import IconAdd from '../../../../icons/IconAdd'

const COLUMNS_TABLE = [
  {name: 'Activate'},
  {name: 'Glossary'},
  {name: ''},
  {name: ''},
]

export const MT_GLOSSARY_CREATE_ROW_ID = 'createRow'

export class MTGlossaryStatus {
  constructor() {
    this.wasAborted = false
  }

  get(props, promise = getStatusMemoryGlossaryImport) {
    this.wasAborted = false
    return new Promise((resolve, reject) => {
      this.executeApi({promise, props, resolve, reject})
    })
  }

  cancel() {
    this.wasAborted = true
  }

  executeApi({promise, props, resolve, reject}) {
    const DELAY = 1000

    promise(props).then((data) => {
      if (typeof data?.progress === 'undefined') {
        reject()
        return
      }
      if (data.progress === 0) {
        setTimeout(() => {
          if (!this.wasAborted)
            this.executeApi({promise, props, resolve, reject})
        }, DELAY)
      } else {
        resolve(data)
      }
    })
  }
}

export const MTGlossary = ({id, isCattoolPage = false}) => {
  const {
    currentProjectTemplate,
    modifyingCurrentTemplate,
    availableTemplateProps,
  } = useContext(SettingsPanelContext)
  const {mt: {extra: mtGlossaryProps} = {}} = currentProjectTemplate ?? {}

  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState()
  const [isGlossaryCaseSensitive, setIsGlossaryCaseSensitive] = useState(
    mtGlossaryProps?.isGlossaryCaseSensitive ?? false,
  )

  const activeGlossariesRef = useRef()
  activeGlossariesRef.current = mtGlossaryProps?.glossaries

  const updateRowsState = useCallback(
    (value) => {
      setRows((prevState) => {
        const newValue = typeof value === 'function' ? value(prevState) : value

        return newValue.map((row) => ({
          ...row,
          node:
            row.id === MT_GLOSSARY_CREATE_ROW_ID ? (
              <MTGlossaryCreateRow
                {...{engineId: id, row, setRows: updateRowsState}}
              />
            ) : (
              <MTGlossaryRow
                key={row.id}
                {...{
                  engineId: id,
                  row,
                  setRows: updateRowsState,
                  isReadOnly: isCattoolPage,
                }}
              />
            ),
        }))
      })
    },
    [id, isCattoolPage],
  )

  useEffect(() => {
    let wasCleanup = false

    const glossaries = activeGlossariesRef.current
    let memories = []
    const getJobMetadata = ({jobMetadata: {project} = {}}) => {
      const rows = memories.filter(({id}) =>
        project.mmt_glossaries.glossaries.some((value) => value === id),
      )
      updateRowsState(rows.map(({id, name}) => ({id, name, isActive: true})))
    }

    getMMTKeys({engineId: id}).then((data) => {
      const items = [...data].reverse()
      if (!wasCleanup) {
        if (!isCattoolPage) {
          updateRowsState(
            items.map(({name, id: idRow}) => {
              const isActive = Array.isArray(glossaries)
                ? glossaries.some((value) => value === idRow)
                : false

              return {
                id: idRow,
                name,
                isActive,
              }
            }),
          )
        } else {
          memories = items
          CatToolStore.addListener(
            CatToolConstants.GET_JOB_METADATA,
            getJobMetadata,
          )
          CatToolActions.getJobMetadata({
            idJob: config.id_job,
            password: config.password,
          })
        }
      }
    })

    return () => {
      wasCleanup = true
      CatToolStore.removeListener(
        CatToolConstants.GET_JOB_METADATA,
        getJobMetadata,
      )
    }
  }, [id, isCattoolPage, updateRowsState])

  useEffect(() => {
    if (
      isCattoolPage ||
      !rows ||
      (rows.length === 1 &&
        rows.some(({id}) => id === MT_GLOSSARY_CREATE_ROW_ID))
    )
      return

    const rowsActive = rows.filter(({isActive}) => isActive).map(({id}) => id)

    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      [availableTemplateProps.mt]: {
        ...prevTemplate[availableTemplateProps.mt],
        extra: {
          ...(rowsActive.length
            ? {
                glossaries: rowsActive,
                isGlossaryCaseSensitive,
              }
            : {}),
        },
      },
    }))
  }, [
    rows,
    isGlossaryCaseSensitive,
    isCattoolPage,
    modifyingCurrentTemplate,
    availableTemplateProps,
  ])

  const addGlossary = () => {
    const row = {
      id: MT_GLOSSARY_CREATE_ROW_ID,
      isActive: false,
      className: 'row-content-create-glossary',
    }

    updateRowsState((prevState) => [
      row,
      ...prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
    ])
  }

  const onShowingRows = () => {
    setIsShowingRows((prevState) => !prevState)
    if (!isCattoolPage) {
      // modifyingCurrentTemplate((prevTemplate) => ({
      //   ...prevTemplate,
      //   [availableTemplateProps.mt]: {
      //     ...prevTemplate[availableTemplateProps.mt],
      //     extra: {
      //       ...(prevTemplate[availableTemplateProps.mt]?.extra ?? {}),
      //       isOpened: !isShowingRows,
      //     },
      //   },
      // }))
    }
  }

  const onChangeCaseSensitive = (e) =>
    setIsGlossaryCaseSensitive(e.currentTarget.checked)

  const haveRecords = rows?.length > 0

  return (
    <div className="mt-glossary">
      <div className="expand-button">
        <button
          className={`${isShowingRows ? 'rotate' : ''}`}
          onClick={onShowingRows}
        >
          <ArrowDown />
          Glossary options
        </button>
      </div>
      {isShowingRows && (
        <>
          {haveRecords && (
            <SettingsPanelTable
              columns={COLUMNS_TABLE}
              rows={rows}
              className="mt-glossary-table"
            />
          )}

          {!isCattoolPage &&
            (haveRecords ? (
              <div className="main-buttons-container">
                <button
                  className="grey-button create-glossary-button"
                  onClick={addGlossary}
                >
                  <IconAdd size={18} />
                  New
                </button>
                <div
                  className="mt-glossary-case-sensitive"
                  title='Activating this option makes glossary matching case-sensitive: if your glossary includes a translation for "Cat", it will only be applied when "Cat" is found with an initial capital letter'
                >
                  <input
                    checked={isGlossaryCaseSensitive}
                    onChange={onChangeCaseSensitive}
                    type="checkbox"
                  />
                  <label>Enable case-sensitive matching</label>
                </div>
              </div>
            ) : Array.isArray(rows) ? (
              <div className="empty-list-mode">
                <p>Start using ModernMT’s glossary feature</p>
                <button
                  className="grey-button create-glossary-button"
                  onClick={addGlossary}
                >
                  <IconAdd size={18} />
                  New glossary
                </button>
              </div>
            ) : (
              <p className="loading-list-mode">Loading...</p>
            ))}
        </>
      )}
    </div>
  )
}

MTGlossary.propTypes = {
  id: PropTypes.number.isRequired,
  isCattoolPage: PropTypes.bool,
}
