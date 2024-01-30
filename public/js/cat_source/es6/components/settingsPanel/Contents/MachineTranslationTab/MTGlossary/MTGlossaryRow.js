import React, {
  Fragment,
  useContext,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import Upload from '../../../../../../../../img/icons/Upload'
import Trash from '../../../../../../../../img/icons/Trash'
import {deleteMemoryGlossary} from '../../../../../api/deleteMemoryGlossary/deleteMemoryGlossary'
import {MachineTranslationTabContext} from '../MachineTranslationTab'
import {MTGlossaryStatus} from './MTGlossary'
import {importMemoryGlossary} from '../../../../../api/importMemoryGlossary/importMemoryGlossary'
import {updateMemoryGlossary} from '../../../../../api/updateMemoryGlossary/updateMemoryGlossary'
import IconEdit from '../../../../icons/IconEdit'
import Checkmark from '../../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../../img/icons/Close'
import LabelWithTooltip from '../../../../common/LabelWithTooltip'
import CreateProjectActions from '../../../../../actions/CreateProjectActions'
import {SettingsPanelContext} from '../../../SettingsPanelContext'

export const MTGlossaryRow = ({engineId, row, setRows, isReadOnly}) => {
  const {setNotification} = useContext(MachineTranslationTabContext)
  const {projectTemplates, availableTemplateProps} =
    useContext(SettingsPanelContext)

  const [isActive, setIsActive] = useState(row.isActive)
  const [isEditingName, setIsEditingName] = useState(false)
  const [name, setName] = useState(row.name)
  const [file, setFile] = useState()
  const [isWaitingResult, setIsWaitingResult] = useState(false)

  const statusImport = useRef()
  const inputNameRef = useRef()

  useEffect(() => {
    setIsActive(row.isActive)
  }, [row.isActive])

  // user import new one glossary
  useEffect(() => {
    if (!file) return

    const dispatchSuccessfullImportNotification = () => {
      setNotification({
        type: 'success',
        message: `Glossary file ${file.name} imported successfully`,
      })
      setIsWaitingResult(false)
    }
    const dispatchErrorImportNotification = () => {
      setNotification({
        type: 'error',
        message: `Glossary file ${file.name} import error`,
      })
      setIsWaitingResult(false)
    }

    statusImport.current = new MTGlossaryStatus()

    setIsWaitingResult(true)
    importMemoryGlossary({engineId, glossary: file, memoryId: row.id})
      .then((data) => {
        if (data.progress === 1) {
          dispatchSuccessfullImportNotification()
        } else {
          //   start polling to get status
          statusImport.current
            .get({engineId, uuid: data.id})
            .then(() => {
              dispatchSuccessfullImportNotification()
            })
            .catch(() => dispatchErrorImportNotification())
        }
      })
      .catch(() => dispatchErrorImportNotification())

    return () => statusImport.current.cancel()
  }, [file, row.id, engineId, setNotification])

  useLayoutEffect(() => {
    inputNameRef.current.focus()
  }, [isEditingName])

  const onChangeIsActive = (e) => {
    setNotification()
    const isActive = e.currentTarget.checked
    setIsActive(isActive)
    setRows((prevState) =>
      prevState.map((glossary) =>
        glossary.id === row.id ? {...glossary, isActive} : glossary,
      ),
    )
  }

  const onChangeName = (e) => {
    setNotification()

    const {value} = e.currentTarget ?? {}
    setName(value)
  }

  const updateKeyName = () => {
    if (name) {
      updateMemoryGlossary({engineId, memoryId: row.id, name})
        .then((data) => {
          setRows((prevState) =>
            prevState.map((glossary) =>
              glossary.id === row.id
                ? {
                    ...glossary,
                    name: data.name,
                  }
                : glossary,
            ),
          )
          setIsEditingName(false)
        })
        .catch(() => {
          setName(row.name)
        })
    } else {
      setName(row.name)
    }
  }

  const onChangeFile = (e) => {
    setNotification()
    if (e.target.files) setFile(Array.from(e.target.files)[0])
  }

  const deleteGlossary = () => {
    setNotification()

    setIsWaitingResult(true)
    deleteMemoryGlossary({engineId, memoryId: row.id})
      .then((data) => {
        if (data.id === row.id) {
          setRows((prevState) => prevState.filter(({id}) => id !== row.id))

          const mtProp = availableTemplateProps.mt

          const templatesInvolved = projectTemplates
            .filter((template) =>
              template[mtProp].extra.glossaries?.some(
                (value) => value === row.id,
              ),
            )
            .map((template) => {
              const mtObject = template[mtProp]
              const {glossaries, ...extra} = mtObject.extra // eslint-disable-line
              const glossariesFiltered = mtObject.extra.glossaries.filter(
                (value) => value !== row.id,
              )

              return {
                ...template,
                [mtProp]: {
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

          CreateProjectActions.updateProjectTemplates({
            templates: templatesInvolved,
            modifiedPropsCurrentProjectTemplate: {
              [mtProp]: templatesInvolved.find(
                ({isTemporary}) => isTemporary,
              )?.[mtProp],
            },
          })
        }
      })
      .catch(() => {
        setNotification({
          type: 'error',
          message: 'Glossary delete error',
        })
      })
      .finally(() => setIsWaitingResult(false))
  }

  const editingNameButtons = !isEditingName ? (
    <button className="grey-button" onClick={() => setIsEditingName(true)}>
      <IconEdit size={15} />
    </button>
  ) : (
    <div className="editing-buttons">
      <button
        className="ui primary button settings-panel-button-icon confirm-button"
        disabled={!name}
        onClick={updateKeyName}
      >
        <Checkmark size={12} />
        Confirm
      </button>
      <button
        className="ui button orange close-button"
        onClick={() => {
          setIsEditingName(false)
          setName(row.name)
        }}
      >
        <Close size={18} />
      </button>
    </div>
  )

  const setInputNameContainer = (children) =>
    !isEditingName ? (
      <LabelWithTooltip className="tooltip-input-name">
        {children}
      </LabelWithTooltip>
    ) : (
      <div className="tooltip-input-name">{children}</div>
    )

  return (
    <Fragment>
      <div className="align-center">
        <input
          checked={isActive}
          onChange={onChangeIsActive}
          type="checkbox"
          disabled={isWaitingResult || isReadOnly}
        />
      </div>
      <div className="glossary-row-name">
        {setInputNameContainer(
          <input
            ref={inputNameRef}
            className={`glossary-row-name-input${
              isEditingName ? ' active' : ''
            }`}
            value={name}
            onChange={onChangeName}
            disabled={!isEditingName || isReadOnly}
          />,
        )}
        {!isReadOnly && editingNameButtons}
      </div>
      {!isReadOnly && (
        <>
          <div className="glossary-row-import-button">
            <input
              type="file"
              id={`file-import${row.id}`}
              onChange={onChangeFile}
              name="import_file"
              accept=".xls, .xlsx"
              disabled={isWaitingResult}
            />
            <label htmlFor={`file-import${row.id}`} className="grey-button">
              <Upload size={12} />
              Update
            </label>
          </div>
          <div className="glossary-row-delete">
            <button
              className="grey-button"
              disabled={isWaitingResult}
              onClick={deleteGlossary}
            >
              <Trash size={12} />
            </button>
          </div>
          {isWaitingResult && <div className="spinner"></div>}
        </>
      )}
    </Fragment>
  )
}

MTGlossaryRow.propTypes = {
  engineId: PropTypes.number,
  row: PropTypes.object,
  setRows: PropTypes.func,
  isReadOnly: PropTypes.bool,
}
