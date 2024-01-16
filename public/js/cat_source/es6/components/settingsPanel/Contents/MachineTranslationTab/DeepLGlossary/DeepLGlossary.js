import React, {useCallback, useContext, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {useEffect} from 'react'
import ArrowDown from '../../../../../../../../img/icons/ArrowDown'
import {SettingsPanelTable} from '../../../SettingsPanelTable'
import IconAdd from '../../../../icons/IconAdd'
import {getDeepLGlosssaries} from '../../../../../api/getDeepLGlosssaries/getDeepLGlosssaries'
import {DeepLGlossaryRow} from './DeepLGlossaryRow'
import {DeepLGlossaryCreateRow} from './DeepLGlossaryCreateRow'
import CatToolStore from '../../../../../stores/CatToolStore'
import CatToolConstants from '../../../../../constants/CatToolConstants'
import CatToolActions from '../../../../../actions/CatToolActions'

const COLUMNS_TABLE = [
  {name: 'Activate'},
  {name: 'Glossary'},
  {name: ''},
  {name: ''},
]

export const DEEPL_GLOSSARY_CREATE_ROW_ID = 'createRow'

export const DeepLGlossary = ({id, isCattoolPage = false}) => {
  const {activeMTEngine, setActiveMTEngine} = useContext(SettingsPanelContext)

  const [isShowingRows, setIsShowingRows] = useState(
    activeMTEngine.mtGlossaryProps?.isOpened ?? false,
  )
  const [rows, setRows] = useState()

  const activeGlossaryRef = useRef()
  activeGlossaryRef.current =
    activeMTEngine.deeplGlossaryProps?.deepl_id_glossary

  const updateRowsState = useCallback(
    (value) => {
      setRows((prevState) => {
        const newValue = typeof value === 'function' ? value(prevState) : value

        return newValue.map((row) => ({
          ...row,
          node:
            row.id === DEEPL_GLOSSARY_CREATE_ROW_ID ? (
              <DeepLGlossaryCreateRow
                {...{engineId: id, row, setRows: updateRowsState}}
              />
            ) : (
              <DeepLGlossaryRow
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

    const glossaryId = activeGlossaryRef.current
    let glossariesFromJobMetadata = []
    const getJobMetadata = ({jobMetadata: {project} = {}}) => {
      const rows = glossariesFromJobMetadata.filter(
        ({glossary_id}) => project.deepl_id_glossary === glossary_id,
      )

      updateRowsState(
        rows.map(({glossary_id: id, name}) => ({id, name, isActive: true})),
      )
    }

    getDeepLGlosssaries({engineId: id}).then(({glossaries}) => {
      const items = [...glossaries].reverse()
      if (!wasCleanup) {
        if (!isCattoolPage) {
          updateRowsState(
            items.map(({name, glossary_id: idRow}, index) => {
              const isActive =
                typeof glossaryId !== 'undefined'
                  ? idRow === glossaryId
                  : index === 0

              return {
                id: idRow,
                name,
                isActive,
              }
            }),
          )
        } else {
          glossariesFromJobMetadata = items
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
      deeplGlossaryProps: {
        ...prevState.deeplGlossaryProps,
        deepl_id_glossary: rows.find(({isActive}) => isActive)?.id,
      },
    }))
  }, [rows, isCattoolPage, setActiveMTEngine])

  const addGlossary = () => {
    const row = {
      id: DEEPL_GLOSSARY_CREATE_ROW_ID,
      isActive: false,
      className: 'row-content-create-glossary',
    }

    updateRowsState((prevState) => [
      row,
      ...prevState.filter(({id}) => id !== DEEPL_GLOSSARY_CREATE_ROW_ID),
    ])
  }

  const onShowingRows = () => {
    setIsShowingRows((prevState) => !prevState)
    if (!isCattoolPage) {
      setActiveMTEngine((prevState) => ({
        ...prevState,
        deeplGlossaryProps: {
          ...prevState.deeplGlossaryProps,
          isOpened: !isShowingRows,
        },
      }))
    }
  }

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
              </div>
            ) : Array.isArray(rows) ? (
              <div className="empty-list-mode">
                <p>Start using DeepL's glossary feature</p>
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

DeepLGlossary.propTypes = {
  id: PropTypes.number.isRequired,
  isCattoolPage: PropTypes.bool,
}
