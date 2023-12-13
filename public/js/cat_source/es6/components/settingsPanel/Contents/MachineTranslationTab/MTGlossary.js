import React, {useCallback, useContext, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useState} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTGlossaryRow} from './MTGlossaryRow'
import {MTGlossaryCreateRow} from './MTGlossaryCreateRow'
import {getMMTKeys} from '../../../../api/getMMTKeys/getMMTKeys'
import {getStatusMemoryGlossaryImport} from '../../../../api/getStatusMemoryGlossaryImport/getStatusMemoryGlossaryImport'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import CatToolActions from '../../../../actions/CatToolActions'
import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolStore from '../../../../stores/CatToolStore'
import ArrowDown from '../../../../../../../img/icons/ArrowDown'
import IconAdd from '../../../icons/IconAdd'

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
  const {activeMTEngine, setActiveMTEngine} = useContext(SettingsPanelContext)

  const [isShowingRows, setIsShowingRows] = useState(
    activeMTEngine.mtGlossaryProps?.isOpened ?? false,
  )
  const [rows, setRows] = useState()
  const [isGlossaryCaseInsensitive, setIsGlossaryCaseInsensitive] = useState(
    activeMTEngine.mtGlossaryProps?.isGlossaryCaseInsensitive ?? false,
  )

  const activeGlossariesRef = useRef()
  activeGlossariesRef.current = activeMTEngine.mtGlossaryProps?.glossaries

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
      if (!wasCleanup) {
        if (!isCattoolPage) {
          updateRowsState(
            data.map(({name, id: idRow}) => {
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
          memories = data
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
    if (isCattoolPage || !rows) return

    setActiveMTEngine((prevState) => ({
      ...prevState,
      mtGlossaryProps: {
        ...prevState.mtGlossaryProps,
        glossaries: rows.filter(({isActive}) => isActive).map(({id}) => id),
        isGlossaryCaseInsensitive,
      },
    }))
  }, [rows, isGlossaryCaseInsensitive, isCattoolPage, setActiveMTEngine])

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
      setActiveMTEngine((prevState) => ({
        ...prevState,
        mtGlossaryProps: {
          ...prevState.mtGlossaryProps,
          isOpened: !isShowingRows,
        },
      }))
    }
  }

  const onChangeCaseSensitive = (e) =>
    setIsGlossaryCaseInsensitive(e.currentTarget.checked)

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
                <div className="mt-glossary-case-sensitive">
                  <input
                    checked={isGlossaryCaseInsensitive}
                    onChange={onChangeCaseSensitive}
                    type="checkbox"
                    title=""
                  />
                  <label>Enable case-sensitive matching</label>
                </div>
              </div>
            ) : Array.isArray(rows) ? (
              <div className="empty-list-mode">
                <p>
                  Create a glossary to start using ModernMT&apos;s glossary
                  feature
                </p>
                <button
                  className="grey-button create-glossary-button"
                  onClick={addGlossary}
                >
                  <IconAdd size={14} />
                  Create glossary
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
