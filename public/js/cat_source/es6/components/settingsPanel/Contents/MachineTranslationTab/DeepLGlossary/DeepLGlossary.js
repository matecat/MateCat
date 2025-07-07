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
import {DeleteResource} from '../DeleteResource'
import {deleteDeepLGlossary} from '../../../../../api/deleteDeepLGlossary'
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

export const DEEPL_GLOSSARY_CREATE_ROW_ID = 'createRow'

export const DeepLGlossary = ({id, isCattoolPage = false}) => {
  const {currentProjectTemplate, modifyingCurrentTemplate, projectTemplates} =
    useContext(SettingsPanelContext)

  const {mt: {extra: deeplGlossaryProps} = {}} = currentProjectTemplate ?? {}

  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState()
  const [deleteGlossaryRequest, setDeleteGlossaryRequest] = useState()

  const activeGlossaryRef = useRef()
  activeGlossaryRef.current = deeplGlossaryProps?.deepl_id_glossary

  const deleteGlossary = useRef()
  deleteGlossary.current = (glossary = deleteGlossaryRequest) => {
    deleteDeepLGlossary({engineId: id, id: glossary.id})
      .then((data) => {
        if (data.id === glossary.id) {
          setRows((prevState) => prevState.filter(({id}) => id !== glossary.id))

          const templatesInvolved = projectTemplates
            .filter(
              (template) =>
                template.mt?.extra?.deepl_id_glossary === glossary.id,
            )
            .map((template) => {
              const mtObject = template.mt
              const {deepl_id_glossary, ...extra} = mtObject.extra // eslint-disable-line

              return {
                ...template,
                [SCHEMA_KEYS.mt]: {
                  ...mtObject,
                  extra: {
                    ...extra,
                    ...(deepl_id_glossary !== glossary.id && {
                      deepl_id_glossary,
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
      .filter(
        (template) => template.mt?.extra?.deepl_id_glossary === glossary.id,
      )

    if (templatesInvolved.length) {
      ModalsActions.showModalComponent(
        ConfirmDeleteResourceProjectTemplates,
        {
          projectTemplatesInvolved: templatesInvolved,
          successCallback: () => deleteGlossary.current(glossary),
          content:
            'The glossary you are about to delete is linked to a DeepL license and used in the following project creation template(s):',
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
            'You are about to delete a resource linked to an DeepL license. If you confirm, it will be deleted permanently for you and any other user of the same license.',
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
            items.map(({name, glossary_id: idRow}) => {
              const isActive =
                typeof glossaryId !== 'undefined' ? idRow === glossaryId : false

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
    if (!isCattoolPage) {
      const deeplIdGlossary = activeGlossaryRef.current

      updateRowsState((prevState) =>
        Array.isArray(prevState)
          ? prevState.map(({name, id: idRow}) => {
              const isActive =
                typeof deeplIdGlossary !== 'undefined'
                  ? idRow === deeplIdGlossary
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
        rows.some(({id}) => id === DEEPL_GLOSSARY_CREATE_ROW_ID))
    )
      return

    const activeRow = rows.find(({isActive}) => isActive)

    modifyingCurrentTemplate((prevTemplate) => {
      const prevMt = prevTemplate.mt
      const prevMTExtra = prevMt?.extra ?? {}
      const {deepl_id_glossary, ...deepLExtra} = prevMTExtra //eslint-disable-line

      return {
        ...prevTemplate,
        mt: {
          ...prevMt,
          extra: {
            ...deepLExtra,
            ...(activeRow && {
              deepl_id_glossary: activeRow.id,
            }),
          },
        },
      }
    })
  }, [rows, isCattoolPage, modifyingCurrentTemplate])

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
      // setActiveMTEngine((prevState) => ({
      //   ...prevState,
      //   deeplGlossaryProps: {
      //     ...prevState.deeplGlossaryProps,
      //     isOpened: !isShowingRows,
      //   },
      // }))
    }
  }

  const haveRecords = rows?.length > 0
  const shouldHideNewButton = rows?.some(
    ({id}) => id === DEEPL_GLOSSARY_CREATE_ROW_ID,
  )

  return (
    <div className="mt-glossary">
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
