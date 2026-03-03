import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelTable} from '../../../SettingsPanelTable'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import IconAdd from '../../../../icons/IconAdd'
import {LaraGlossaryRow} from './LaraGlossaryRow'
import {getLaraGlossaries} from '../../../../../api/getLaraGlossaries/getLaraGlossaries'
import CatToolStore from '../../../../../stores/CatToolStore'
import CatToolConstants from '../../../../../constants/CatToolConstants'
import CatToolActions from '../../../../../actions/CatToolActions'
import {Button, BUTTON_TYPE} from '../../../../common/Button/Button'

const COLUMNS_TABLE = [
  {name: 'Active'},
  {name: 'Glossary'},
  {name: ''},
  {name: ''},
]

export const LaraGlossary = ({id, setGlossaries, isCattoolPage = false}) => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const {mt: {extra} = {}} = currentProjectTemplate ?? {}

  const [rows, setRows] = useState()

  const activeGlossariesRef = useRef()
  activeGlossariesRef.current = extra?.lara_glossaries

  const updateRowsState = useCallback(
    (value) => {
      setRows((prevState) => {
        const newValue = typeof value === 'function' ? value(prevState) : value
        if (!Array.isArray(newValue)) return prevState

        return newValue.map((row) => ({
          ...row,
          node: (
            <LaraGlossaryRow
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
      const rows = memories.filter(({id}) => {
        const laraGlossaries = project.mt_extra?.lara_glossaries
          ? project.mt_extra.lara_glossaries
          : []

        return laraGlossaries.some((value) => value === id)
      })
      updateRowsState(rows.map(({id, name}) => ({id, name, isActive: true})))
    }

    if (config.ownerIsMe || !isCattoolPage) {
      getLaraGlossaries({engineId: id}).then((data) => {
        const items = data
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
    }

    return () => {
      wasCleanup = true
      CatToolStore.removeListener(
        CatToolConstants.GET_JOB_METADATA,
        getJobMetadata,
      )
    }
  }, [id, isCattoolPage, updateRowsState])

  useEffect(() => {
    if (!isCattoolPage) {
      const glossaries = activeGlossariesRef.current

      updateRowsState((prevState) =>
        Array.isArray(prevState)
          ? prevState.map(({name, id: idRow}) => {
              const isActive = Array.isArray(glossaries)
                ? glossaries.some((value) => value === idRow)
                : false

              return {
                id: idRow,
                name,
                isActive,
              }
            })
          : prevState,
      )
    }
  }, [currentProjectTemplate.id, isCattoolPage, updateRowsState])

  useEffect(() => {
    if (isCattoolPage || !rows) return

    const rowsActive = rows.filter(({isActive}) => isActive).map(({id}) => id)

    setGlossaries(rowsActive)
  }, [rows, isCattoolPage, modifyingCurrentTemplate, setGlossaries])

  const openGlossaryPage = () => {
    window.open('https://app.laratranslate.com/account/glossaries', '_blank')
  }

  const haveRecords = rows?.length > 0

  return (
    <div className="mt-glossary">
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
            <Button type={BUTTON_TYPE.PRIMARY} onClick={openGlossaryPage}>
              Manage your Lara glossaries
            </Button>
          </div>
        ) : Array.isArray(rows) ? (
          <div className="empty-list-mode">
            <p>Start using Lara's glossary feature</p>
            <Button
              className="settings-panel-grey-button"
              onClick={openGlossaryPage}
            >
              <IconAdd size={16} />
              Create a glossary on Lara
            </Button>
          </div>
        ) : (
          <p className="loading-list-mode">Loading...</p>
        ))}
    </div>
  )
}

LaraGlossary.propTypes = {
  id: PropTypes.number.isRequired,
  setGlossaries: PropTypes.func.isRequired,
  isCattoolPage: PropTypes.bool,
}
