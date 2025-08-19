import React, {Fragment, useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {
  SPECIAL_ROWS_ID,
  TranslationMemoryGlossaryTabContext,
  getTmDataStructureToSendServer,
  isOwnerOfKey,
  orderTmKeys,
} from './TranslationMemoryGlossaryTab'
import {MenuButton} from '../../../common/MenuButton/MenuButton'
import {MenuButtonItem} from '../../../common/MenuButton/MenuButtonItem'
import {ImportTMX} from './ImportTMX'
import {ImportGlossary} from './ImportGlossary'
import {ExportTMX} from './ExportTMX'
import {ExportGlossary} from './ExportGlossary'
import {ShareResource} from './ShareResource'
import {DeleteResource} from './DeleteResource'
import {updateTmKey} from '../../../../api/updateTmKey'
import ModalsActions from '../../../../actions/ModalsActions'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

import Earth from '../../../../../img/icons/Earth'
import Lock from '../../../../../img/icons/Lock'
import Users from '../../../../../img/icons/Users'
import Upload from '../../../../../img/icons/Upload'
import Download from '../../../../../img/icons/Download'
import Share from '../../../../../img/icons/Share'
import Trash from '../../../../../img/icons/Trash'
import DotsHorizontal from '../../../../../img/icons/DotsHorizontal'
import {updateJobKeys} from '../../../../api/updateJobKeys'
import CatToolActions from '../../../../actions/CatToolActions'
import {ConfirmDeleteResourceProjectTemplates} from '../../../modals/ConfirmDeleteResourceProjectTemplates'
import CreateProjectActions from '../../../../actions/CreateProjectActions'
import {deleteTmKey} from '../../../../api/deleteTmKey'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import {Button, BUTTON_SIZE} from '../../../common/Button/Button'
import {NumericStepper} from '../../../common/NumericStepper/NumericStepper'
import IconClose from '../../../icons/IconClose'
import {getTmKeyEnginesInfo} from '../../../../api/getTmKeyEnginesInfo/getTmKeyEnginesInfo'

export const TMKeyRow = ({row, onExpandRow}) => {
  const {isImportTMXInProgress} = useContext(CreateProjectContext)
  const {
    tmKeys,
    setTmKeys,
    modifyingCurrentTemplate,
    currentProjectTemplate,
    projectTemplates,
    portalTarget,
  } = useContext(SettingsPanelContext)
  const {setSpecialRows} = useContext(TranslationMemoryGlossaryTabContext)

  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
  const [name, setName] = useState(row.name)

  const valueChange = useRef(false)
  const valueName = useRef(row.name)
  const deleteTmKeyRemoveFrom = useRef()

  const penalty = row.penalty ?? 0

  const onChangePenalty = (value) => {
    updateRow({isLookup, isUpdating, penalty: value})
  }

  const isMMSharedKey = row.id === SPECIAL_ROWS_ID.defaultTranslationMemory
  const isOwner = isOwnerOfKey(row.key)
  const getPublicMatches = currentProjectTemplate.get_public_matches
  const publicTmPenalty = currentProjectTemplate.public_tm_penalty

  useEffect(() => {
    setIsLookup(row.r ?? false)
    setIsUpdating(row.w ?? false)
  }, [row.r, row.w])

  const onChangeIsLookup = (e) => {
    const isLookup = e.currentTarget.checked
    if (
      isLookup &&
      !row.isActive &&
      tmKeys.filter((tm) => tm.isActive).length >= 10
    ) {
      CatToolActions.addNotification({
        title: 'Resource cannot be activated',
        type: 'error',
        text: 'You can activate up to 10 resources per project.',
        position: 'br',
        allowHtml: true,
        timer: 5000,
      })
      setIsLookup(false)
      return
    }
    updateRow({isLookup, isUpdating})
    if (isMMSharedKey) {
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        getPublicMatches: isLookup,
      }))
    }

    setIsLookup(isLookup)
  }

  const onChangeIsUpdating = (e) => {
    const isUpdating = e.currentTarget.checked
    updateRow({isLookup, isUpdating})
    setIsUpdating(isUpdating)
  }

  const updateRow = ({isLookup, isUpdating, penalty}) => {
    if (!isMMSharedKey) {
      const updatedKeys = tmKeys.map((tm) =>
        tm.id === row.id
          ? {
              ...tm,
              isActive: isLookup
                ? isLookup
                : !isLookup && !isUpdating
                  ? false
                  : true,
              r: isLookup,
              w: !tm.isActive ? isLookup : isUpdating,
              penalty: typeof penalty === 'number' ? penalty : tm.penalty,
            }
          : tm,
      )
      setTmKeys(updatedKeys)

      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        tm: orderTmKeys(
          updatedKeys.filter(({isActive}) => isActive),
          prevTemplate.tm.map(({key}) => key),
        ).map(({id, isActive, ...rest}) => rest), //eslint-disable-line
      }))
    } else {
      setSpecialRows((prevState) =>
        prevState.map((specialRow) =>
          specialRow.id === row.id
            ? {
                ...specialRow,
                r: isLookup,
                w: isUpdating,
                penalty:
                  typeof penalty === 'number' ? penalty : specialRow.penalty,
              }
            : specialRow,
        ),
      )
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        publicTmPenalty:
          typeof penalty === 'number' ? penalty : prevTemplate.publicTmPenalty,
      }))
    }
  }

  const onChangeName = (e) => {
    const {value} = e.currentTarget ?? {}
    if (value !== name) valueChange.current = true
    setName(value)
    if (value)
      setTmKeys((prevState) =>
        prevState.map((tm) => (tm.id === row.id ? {...tm, name: value} : tm)),
      )
  }

  const updateKeyName = () => {
    if (valueChange.current) {
      if (name.trim() !== '') {
        updateTmKey({
          key: row.key,
          penalty: row.penalty,
          description: name,
        })
          .then(() => {
            setName(row.name)
            valueName.current = row.name
          })
          .catch(({errors}) => {
            const errMessage =
              errors && errors.length > 0
                ? errors[0].message
                : 'The key you entered is invalid.'

            CatToolActions.addNotification({
              title: 'Error updating key',
              type: 'error',
              text: errMessage,
              position: 'br',
              allowHtml: true,
              timer: 5000,
            })
            setTmKeys((prevState) =>
              prevState.map((tm) =>
                tm.id === row.id ? {...tm, name: valueName.current} : tm,
              ),
            )
            setName(valueName.current)
          })

        if (config.is_cattool) {
          updateJobKeys({
            getPublicMatches,
            publicTmPenalty,
            dataTm: getTmDataStructureToSendServer({tmKeys}),
          }).then(() => CatToolActions.onTMKeysChangeStatus())
        }
      } else {
        CatToolActions.addNotification({
          title: 'Error updating resource',
          type: 'error',
          text: 'Resource name cannot be empty. Please provide a valid name.',
          position: 'br',
          allowHtml: true,
          timer: 5000,
        })
        setTmKeys((prevState) =>
          prevState.map((tm) =>
            tm.id === row.id ? {...tm, name: valueName.current} : tm,
          ),
        )
        setName(valueName.current)
      }

      valueChange.current = false
    }
  }

  const handleExpandeRow = (Component, props = {}) => {
    const onClose = () => onExpandRow({row, shouldExpand: false})
    const onConfirm = onConfirmDeleteTmKey
    const onShare = () => {
      const updatedKeys = tmKeys.map((tm) =>
        tm.id === row.id
          ? {
              ...tm,
              is_shared: true,
            }
          : tm,
      )
      setTmKeys(updatedKeys)
    }
    onExpandRow({
      row,
      shouldExpand: true,
      content: <Component {...{...props, row, onClose, onConfirm, onShare}} />,
    })
  }

  const isMMSharedUpdateChecked =
    !tmKeys || !tmKeys.filter(({owner}) => owner).some(({w}) => w)

  const iconDetails = isMMSharedKey
    ? {
        title: 'Public translation memory',
        icon: <Earth size={16} />,
      }
    : !row.is_shared
      ? {
          title: 'Private resource. \n' + 'Share it from the dropdown menu',
          icon: <Lock size={16} />,
        }
      : {
          title:
            'Shared resource.\n' +
            'Select Share resource from the dropdown menu to see owners',
          icon: <Users size={16} />,
        }

  const onConfirmDeleteTmKey = () => {
    const removeFrom = Object.entries(deleteTmKeyRemoveFrom.current)
      .filter(([, value]) => value)
      .map(([key]) => key)
      .join(',')

    deleteTmKeyRemoveFrom.current = {}

    deleteTmKey({key: row.key, removeFrom})
      .then(() => {
        setTmKeys((prevState) => prevState.filter(({key}) => key !== row.key))
        if (config.is_cattool) {
          !row.isActive && CatToolActions.onTMKeysChangeStatus()
        } else {
          const templatesInvolved = projectTemplates
            .filter((template) => template.tm.some(({key}) => key === row.key))
            .map((template) => ({
              ...template,
              [SCHEMA_KEYS.tm]: template.tm.filter(({key}) => key !== row.key),
            }))

          CreateProjectActions.updateProjectTemplates({
            templates: templatesInvolved,
            modifiedPropsCurrentProjectTemplate: {
              tm: templatesInvolved.find(({isTemporary}) => isTemporary)?.tm,
            },
          })
        }
        const notification = {
          title: 'Resource deleted',
          text: `The resource (<b>${row.name}</b>) has been successfully deleted`,
          type: 'success',
          position: 'br',
          allowHtml: true,
          timer: 5000,
        }
        CatToolActions.addNotification(notification)
      })
      .catch(() => {
        CatToolActions.addNotification({
          title: 'Error deleting resource',
          type: 'error',
          text: 'There was an error saving your data. Please retry!',
          position: 'br',
          allowHtml: true,
          timer: 5000,
        })
        onExpandRow({row, shouldExpand: false})
      })
  }

  const showConfirmDelete = () => {
    const templatesInvolved = projectTemplates
      .filter(({isTemporary}) => !isTemporary)
      .filter((template) => template.tm?.some(({key}) => key === row.key))

    deleteTmKeyRemoveFrom.current = {}

    getTmKeyEnginesInfo(row.key)
      .then((data) => {
        const isMMT = data.some((value) => value === 'MMT')
        const isLara = data.some((value) => value === 'Lara')

        const footerContent =
          isMMT && !isLara ? (
            <div className="tm-row-delete-remove-from-content">
              {templatesInvolved.length >= 1 && (
                <span>
                  If you confirm, it will be removed from the template(s).
                </span>
              )}
              <span>
                This resource is also linked to your ModernMT account:
              </span>
              <div>
                <input
                  checked={deleteTmKeyRemoveFrom.current.MMT}
                  onChange={(e) => {
                    deleteTmKeyRemoveFrom.current.MMT = e.currentTarget.checked
                  }}
                  type="checkbox"
                />
                Permanently delete it from my ModernMT account
              </div>
            </div>
          ) : !isMMT && isLara ? (
            <div className="tm-row-delete-remove-from-content">
              {templatesInvolved.length >= 1 && (
                <span>
                  If you confirm, it will be removed from the template(s).
                </span>
              )}
              <span>This resource is also linked to your Lara account:</span>
              <div>
                <input
                  checked={deleteTmKeyRemoveFrom.current.Lara}
                  onChange={(e) => {
                    deleteTmKeyRemoveFrom.current.Lara = e.currentTarget.checked
                  }}
                  type="checkbox"
                />
                Permanently delete it from my Lara account
              </div>
            </div>
          ) : (
            isMMT &&
            isLara && (
              <div className="tm-row-delete-remove-from-content">
                {templatesInvolved.length >= 1 && (
                  <span>
                    If you confirm, it will be removed from the template(s).
                  </span>
                )}
                <span>
                  This resource is also linked to your ModernMT and Lara
                  accounts:
                </span>
                <div>
                  <input
                    checked={deleteTmKeyRemoveFrom.current.Lara}
                    onChange={(e) => {
                      deleteTmKeyRemoveFrom.current.Lara =
                        e.currentTarget.checked
                    }}
                    type="checkbox"
                  />
                  Permanently delete it from my Lara account
                </div>
                <div>
                  <input
                    checked={deleteTmKeyRemoveFrom.current.MMT}
                    onChange={(e) => {
                      deleteTmKeyRemoveFrom.current.MMT =
                        e.currentTarget.checked
                    }}
                    type="checkbox"
                  />
                  Permanently delete it from my ModernMT account
                </div>
              </div>
            )
          )

        if (templatesInvolved.length) {
          ModalsActions.showModalComponent(
            ConfirmDeleteResourceProjectTemplates,
            {
              projectTemplatesInvolved: templatesInvolved,
              successCallback: onConfirmDeleteTmKey,
              content:
                'The memory key you are about to delete is used in the following project creation template(s):',
              ...(footerContent && {footerContent}),
            },
            'Confirm deletion',
          )
        } else {
          handleExpandeRow(DeleteResource, {footerContent})
        }
      })
      .catch(() => {
        const notification = {
          title: 'Error',
          text: `We got an error, please contact support`,
          type: 'error',
        }
        CatToolActions.addNotification(notification)
      })
  }

  const renderPenalty =
    penalty > 0 ? (
      <div className="tm-row-penalty-numeric-stepper">
        <NumericStepper
          value={penalty}
          valuePlaceholder={`${penalty}%`}
          onChange={onChangePenalty}
          minimumValue={1}
          maximumValue={100}
          stepValue={1}
        />
        <Button
          className="penalty-numeric-stepper-close-button"
          size={BUTTON_SIZE.ICON_SMALL}
          onClick={() => onChangePenalty(0)}
        >
          <IconClose />
        </Button>
      </div>
    ) : (
      <Button
        className="tm-row-penalty-button"
        size={BUTTON_SIZE.SMALL}
        onClick={() => onChangePenalty(1)}
      >
        Add penalty
      </Button>
    )

  return (
    <Fragment>
      <div className="tm-key-lookup align-center">
        <input
          checked={isLookup}
          onChange={onChangeIsLookup}
          disabled={
            (!isOwner && !isMMSharedKey) ||
            (isMMSharedKey && !config.ownerIsMe) ||
            row.isTmFromFile
          }
          type="checkbox"
          data-testid={`tmkey-lookup-${row.id}`}
        />
      </div>
      <div className="tm-key-update align-center">
        {row.isActive && (
          <input
            checked={isMMSharedKey ? isMMSharedUpdateChecked : isUpdating}
            onChange={onChangeIsUpdating}
            type="checkbox"
            disabled={isMMSharedKey || !isOwner}
            {...(isMMSharedKey &&
              isMMSharedUpdateChecked && {
                title: 'Add a private resource to disable updating',
              })}
            data-testid={`tmkey-update-${row.id}`}
          />
        )}
      </div>
      <div>
        <input
          className={`tm-key-row-name${
            isMMSharedKey ? ' tm-key-row-name-disabled' : ''
          }`}
          value={name}
          onChange={onChangeName}
          disabled={isMMSharedKey || !isOwner || row.isTmFromFile}
          onBlur={updateKeyName}
          data-testid={`tmkey-row-name-${row.id}`}
        ></input>
      </div>
      {!isMMSharedKey && <div className="tm-key-row-key">{row.key}</div>}
      <div title={iconDetails.title} className="align-center tm-key-row-icons">
        {iconDetails.icon}
      </div>
      {isOwner && row.isActive && (
        <div className="align-center tm-row-penalty">{renderPenalty}</div>
      )}
      {!isMMSharedKey && isOwner && !row.isTmFromFile ? (
        <div className="align-center">
          <MenuButton
            label="Import TMX"
            onClick={() => handleExpandeRow(ImportTMX)}
            icon={<DotsHorizontal />}
            className="tm-key-row-menu-button"
            dropdownClassName="tm-key-row-menu-button-dropdown"
            disabled={isImportTMXInProgress}
            itemsTarget={portalTarget}
          >
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ImportGlossary)}
              data-testid="import-glossary"
            >
              <div>
                <Upload size={20} /> Import Glossary
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ExportTMX)}
              data-testid="export-tmx"
            >
              <div>
                <Download size={20} /> Export TMX
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ExportGlossary)}
              data-testid="export-glossary"
            >
              <div>
                <Download size={20} /> Export Glossary
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ShareResource)}
              data-testid="share-resource"
            >
              <div>
                <Share size={20} /> Share resource
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={showConfirmDelete}
              data-testid="delete-resource"
            >
              <div>
                <Trash size={20} /> Delete resource
              </div>
            </MenuButtonItem>
          </MenuButton>
        </div>
      ) : isMMSharedKey && !config.not_empty_default_tm_key ? (
        <div className="tm-key-row-menu-button">
          <button
            className="just-button-import-tmx"
            onClick={() => handleExpandeRow(ImportTMX)}
          >
            Import TMX
          </button>
        </div>
      ) : undefined}
    </Fragment>
  )
}

TMKeyRow.propTypes = {
  row: PropTypes.object.isRequired,
  onExpandRow: PropTypes.func.isRequired,
}
