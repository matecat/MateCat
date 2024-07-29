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
import {DeleteResource} from '../DeleteResource'
import {deleteMemoryGlossary} from '../../../../../api/deleteMemoryGlossary'
import CreateProjectActions from '../../../../../actions/CreateProjectActions'
import ModalsActions from '../../../../../actions/ModalsActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../../modals/ConfirmDeleteResourceProjectTemplates'
import {SCHEMA_KEYS} from '../../../../../hooks/useProjectTemplates'

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
  const {currentProjectTemplate, modifyingCurrentTemplate, projectTemplates} =
    useContext(SettingsPanelContext)

  const {mt: {extra: mtGlossaryProps} = {}} = currentProjectTemplate ?? {}

  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState()
  const [isGlossaryCaseSensitive, setIsGlossaryCaseSensitive] = useState(
    mtGlossaryProps?.ignore_glossary_case ?? false,
  )
  const [deleteGlossaryRequest, setDeleteGlossaryRequest] = useState()

  const activeGlossariesRef = useRef()
  activeGlossariesRef.current = mtGlossaryProps?.glossaries

  const deleteGlossary = useRef()
  deleteGlossary.current = (glossary = deleteGlossaryRequest) => {
    deleteMemoryGlossary({engineId: id, memoryId: glossary.id})
      .then((data) => {
        if (data.id === glossary.id) {
          setRows((prevState) => prevState.filter(({id}) => id !== glossary.id))

          const templatesInvolved = projectTemplates
            .filter((template) =>
              template.mt?.extra?.glossaries?.some(
                (value) => value === glossary.id,
              ),
            )
            .map((template) => {
              const mtObject = template.mt
              const {glossaries, ...extra} = mtObject.extra // eslint-disable-line
              const glossariesFiltered = mtObject.extra.glossaries.filter(
                (value) => value !== glossary.id,
              )

              return {
                ...template,
                [SCHEMA_KEYS.mt]: {
                  ...mtObject,
                  extra: {
                    ...extra,
                    ...(glossariesFiltered.length && {
                      glossaries: glossariesFiltered,
                    }),
                  },
                },
              }
            })
          CatToolActions.addNotification({
            title: 'Glossary deleted',
            type: 'success',
            text: `The glossary (<b>${glossary.name}</b>) has been successfully deleted`,
            position: 'br',
            allowHtml: true,
            timer: 5000,
          })
          CreateProjectActions.updateProjectTemplates({
            templates: templatesInvolved,
            modifiedPropsCurrentProjectTemplate: {
              mt: templatesInvolved.find(({isTemporary}) => isTemporary)?.mt,
            },
          })
        }
      })
      .catch(() => {
        CatToolActions.addNotification({
          title: 'Glossary delete error',
          type: 'error',
          text: 'Error deleting glossary',
          position: 'br',
          allowHtml: true,
          timer: 5000,
        })
      })
      .finally(() => setDeleteGlossaryRequest())
  }

  const showConfirmDelete = useRef()
  showConfirmDelete.current = (glossary) => {
    const templatesInvolved = projectTemplates
      .filter(({isTemporary}) => !isTemporary)
      .filter((template) =>
        template.mt?.extra?.glossaries?.some((value) => value === glossary.id),
      )

    if (templatesInvolved.length) {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: () => deleteGlossary.current(glossary),
          content:
            'The glossary you are about to delete is linked to an MT license and used in the following project creation template(s):',
          footerContent:
            'If you confirm, it will be removed from the template(s) and deleted permanently for you and any other user of the same license.',
        },
        'Confirm deletion',
      )
    } else {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: () => deleteGlossary.current(glossary),
          content:
            'You are about to delete a resource linked to an MT license. If you confirm, it will be deleted permanently for you and any other user of the same license.',
          footerContent: '',
        },
        'Confirm deletion',
      )
    }
  }

  const updateRowsState = useCallback(
    (value) => {
      setRows((prevState) => {
        const newValue = typeof value === 'function' ? value(prevState) : value
        if (!Array.isArray(newValue)) return prevState

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
                deleteGlossaryConfirm={(glossary) =>
                  showConfirmDelete.current(glossary)
                }
              />
            ),
          ...(deleteGlossaryRequest &&
            deleteGlossaryRequest.id === row.id && {
              isExpanded: true,
              extraNode: (
                <DeleteResource
                  row={deleteGlossaryRequest}
                  onClose={() => setDeleteGlossaryRequest()}
                  onConfirm={deleteGlossary.current}
                  type={'glossary'}
                />
              ),
            }),
        }))
      })
    },
    [id, isCattoolPage, deleteGlossaryRequest],
  )

  useEffect(() => {
    setIsGlossaryCaseSensitive(mtGlossaryProps?.ignore_glossary_case ?? false)
  }, [mtGlossaryProps?.ignore_glossary_case])

  useEffect(() => {
    let wasCleanup = false

    const glossaries = activeGlossariesRef.current
    let memories = []
    const getJobMetadata = ({jobMetadata: {project} = {}}) => {
      const rows = memories.filter(({id}) =>
        project.mmt_glossaries?.glossaries.some((value) => value === id),
      )
      updateRowsState(rows.map(({id, name}) => ({id, name, isActive: true})))
    }

    if (config.ownerIsMe || !isCattoolPage) {
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
      mt: {
        ...prevTemplate.mt,
        extra: {
          ...(rowsActive.length && {
            glossaries: rowsActive,
          }),
          ...(isGlossaryCaseSensitive && {
            ignore_glossary_case: isGlossaryCaseSensitive,
          }),
        },
      },
    }))
  }, [rows, isGlossaryCaseSensitive, isCattoolPage, modifyingCurrentTemplate])

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
      //   mt: {
      //     ...prevTemplate.mt,
      //     extra: {
      //       ...(prevTemplate.mt?.extra ?? {}),
      //       isOpened: !isShowingRows,
      //     },
      //   },
      // }))
    }
  }

  const onChangeCaseSensitive = (e) =>
    setIsGlossaryCaseSensitive(e.currentTarget.checked)

  const haveRecords = rows?.length > 0
  const isVisibleGlossaryOptions =
    !isCattoolPage || (isCattoolPage && haveRecords)
  const shouldHideNewButton = rows?.some(
    ({id}) => id === MT_GLOSSARY_CREATE_ROW_ID,
  )

  return (
    <div className="mt-glossary">
      {isVisibleGlossaryOptions && (
        <div className="expand-button">
          <button
            className={`${isShowingRows ? 'rotate' : ''}`}
            onClick={onShowingRows}
            title="Glossary options"
          >
            <ArrowDown />
            Glossary options
          </button>
        </div>
      )}

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
                  className={`grey-button create-glossary-button${shouldHideNewButton ? ' create-glossary-button-disabled' : ''}`}
                  onClick={addGlossary}
                  title="Add glossary"
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
