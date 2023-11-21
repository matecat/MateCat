import React, {useCallback, useContext, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {useState} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTGlossaryRow} from './MTGlossaryRow'
import Upload from '../../../../../../../img/icons/Upload'
import {MTGlossaryCreateRow} from './MTGlossaryCreateRow'
import {getMMTKeys} from '../../../../api/getMMTKeys/getMMTKeys'
import {getStatusMemoryGlossaryImport} from '../../../../api/getStatusMemoryGlossaryImport/getStatusMemoryGlossaryImport'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import CatToolActions from '../../../../actions/CatToolActions'
import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolStore from '../../../../stores/CatToolStore'
import ArrowDown from '../../../../../../../img/icons/ArrowDown'

const COLUMNS_TABLE = [
  {name: 'Activate'},
  {name: 'Name'},
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

  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState([])
  const [isGlossaryCaseSensitive, setIsGlossaryCaseSensitive] = useState(
    activeMTEngine.isGlossaryCaseSensitive ?? true,
  )

  const activeGlossariesRef = useRef()
  activeGlossariesRef.current = activeMTEngine.glossaries

  const updateRowsState = useCallback(
    (value) => {
      setRows((prevState) => {
        const newValue = typeof value === 'function' ? value(prevState) : value

        return newValue.map((row) => ({
          ...row,
          node: (
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
    setActiveMTEngine((prevState) => ({
      ...prevState,
      glossaries: rows.filter(({isActive}) => isActive).map(({id}) => id),
      isGlossaryCaseSensitive,
    }))
  }, [rows, isGlossaryCaseSensitive, setActiveMTEngine])

  const addGlossary = () => {
    const row = {
      id: MT_GLOSSARY_CREATE_ROW_ID,
      isActive: false,
      className: 'row-content-create-glossary',
    }

    setRows((prevState) => [
      ...prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
      {
        ...row,
        node: (
          <MTGlossaryCreateRow
            {...{engineId: id, row, setRows: updateRowsState}}
          />
        ),
      },
    ])
  }

  const onChangeCaseSensitive = (e) =>
    setIsGlossaryCaseSensitive(e.currentTarget.checked)

  return (
    <div className="mt-glossary">
      <div className="expand-button">
        <button
          className={`${isShowingRows ? 'rotate' : ''}`}
          onClick={() => setIsShowingRows((prevState) => !prevState)}
        >
          <ArrowDown />
          ModernMT glossary
        </button>
      </div>
      {isShowingRows && (
        <>
          <SettingsPanelTable
            columns={COLUMNS_TABLE}
            rows={rows}
            className="mt-glossary-table"
          />
          {!isCattoolPage && (
            <div className="bottom-buttons">
              <button className="grey-button" onClick={addGlossary}>
                <Upload size={14} />
                Add glossary
              </button>
              <div className="mt-glossary-case-sensitive">
                <input
                  checked={isGlossaryCaseSensitive}
                  onChange={onChangeCaseSensitive}
                  type="checkbox"
                  title=""
                />
                <label>Make glossary case sensitive</label>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}

MTGlossary.propTypes = {
  id: PropTypes.number.isRequired,
  isCattoolPage: PropTypes.bool,
}
