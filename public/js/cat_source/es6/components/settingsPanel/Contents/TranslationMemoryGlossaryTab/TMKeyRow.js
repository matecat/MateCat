import React, {Fragment, useContext, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {
  SPECIAL_ROWS_ID,
  TranslationMemoryGlossaryTabContext,
} from '../TranslationMemoryGlossaryTab/TranslationMemoryGlossaryTab'
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
import ConfirmMessageModal from '../../../modals/ConfirmMessageModal'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

import Earth from '../../../../../../../img/icons/Earth'
import Lock from '../../../../../../../img/icons/Lock'
import Users from '../../../../../../../img/icons/Users'
import Upload from '../../../../../../../img/icons/Upload'
import Download from '../../../../../../../img/icons/Download'
import Share from '../../../../../../../img/icons/Share'
import Trash from '../../../../../../../img/icons/Trash'
import DotsHorizontal from '../../../../../../../img/icons/DotsHorizontal'

export const TMKeyRow = ({row, onExpandRow}) => {
  const {isImportTMXInProgress} = useContext(CreateProjectContext)
  const {tmKeys, setTmKeys} = useContext(SettingsPanelContext)
  const {setSpecialRows, setNotification} = useContext(
    TranslationMemoryGlossaryTabContext,
  )

  const [isLookup, setIsLookup] = useState(row.r ?? false)
  const [isUpdating, setIsUpdating] = useState(row.w ?? false)
  const [name, setName] = useState(row.name)

  const isMMSharedKey = row.id === SPECIAL_ROWS_ID.defaultTranslationMemory
  const isOwner = Boolean(row.owner)

  const onChangeIsLookup = (e) => {
    const isLookup = e.currentTarget.checked

    const notUpdateRow = !config.isLoggedIn && !isLookup && !isUpdating
    if (notUpdateRow) {
      showModalLostPrivateTmKeyNotLoggedIn(setIsLookup)
    } else {
      updateRow({isLookup, isUpdating})
    }
    setIsLookup(isLookup)
  }

  const onChangeIsUpdating = (e) => {
    const isUpdating = e.currentTarget.checked

    const notUpdateRow = !config.isLoggedIn && !isLookup && !isUpdating
    if (notUpdateRow) {
      showModalLostPrivateTmKeyNotLoggedIn(setIsUpdating)
    } else {
      updateRow({isLookup, isUpdating})
    }
    setIsUpdating(isUpdating)
  }

  const updateRow = ({isLookup, isUpdating}) => {
    if (!isMMSharedKey) {
      setTmKeys((prevState) =>
        prevState.map((tm) =>
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
              }
            : tm,
        ),
      )
    } else {
      setSpecialRows((prevState) =>
        prevState.map((specialRow) =>
          specialRow.id === row.id
            ? {
                ...specialRow,
                r: isLookup,
                w: isUpdating,
              }
            : specialRow,
        ),
      )
    }
  }

  const onChangeName = (e) => {
    const {value: name} = e.currentTarget ?? {}
    setName(name)
    if (name)
      setTmKeys((prevState) =>
        prevState.map((tm) => (tm.id === row.id ? {...tm, name} : tm)),
      )
  }

  const updateKeyName = () => {
    updateTmKey({
      key: row.key,
      description: name,
    }).catch((errors) => {
      setNotification({
        type: 'error',
        message: errors[0].message,
      })
    })
  }

  const showModalLostPrivateTmKeyNotLoggedIn = (restoreState) => {
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      {
        text: 'If you confirm this action, your Private TM key will be lost. <br />If you want to avoid this, please, log in with your account now.',
        successText: 'Continue',
        cancelText: 'Cancel',
        successCallback: () =>
          setTmKeys((prevState) =>
            prevState.filter(({key}) => row.key !== key),
          ),
        cancelCallback: () => restoreState(true),
        closeOnSuccess: true,
      },
      'Confirmation required',
    )
  }

  const handleExpandeRow = (Component) => {
    const onClose = () => onExpandRow({row, shouldExpand: false})

    onExpandRow({
      row,
      shouldExpand: true,
      content: <Component {...{row, onClose}} />,
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
        title: 'Private resource. Share it from the dropdown menu',
        icon: <Lock size={16} />,
      }
    : {
        title:
          'Shared resource. Select Share resource from the dropdown menu to see owners',
        icon: <Users size={16} />,
      }

  return (
    <Fragment>
      <div className="tm-key-lookup align-center">
        <input
          checked={isLookup}
          onChange={onChangeIsLookup}
          disabled={!isOwner}
          type="checkbox"
        />
      </div>
      <div className="tm-key-update align-center">
        {row.isActive && (
          <input
            checked={isMMSharedKey ? isMMSharedUpdateChecked : isUpdating}
            onChange={onChangeIsUpdating}
            type="checkbox"
            disabled={isMMSharedKey || !isOwner}
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
          disabled={isMMSharedKey || !isOwner}
          onBlur={updateKeyName}
        ></input>
      </div>
      <div>{row.key}</div>
      <div title={iconDetails.title} className="align-center tm-key-row-icons">
        {iconDetails.icon}
      </div>
      {!isMMSharedKey && isOwner ? (
        <div className="align-center">
          <MenuButton
            label="Import TMX"
            onClick={() => handleExpandeRow(ImportTMX)}
            icon={<DotsHorizontal />}
            className="tm-key-row-menu-button"
            disabled={isImportTMXInProgress}
          >
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ImportGlossary)}
            >
              <div>
                <Upload size={20} /> Import Glossary
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ExportTMX)}
            >
              <div>
                <Download size={20} /> Export TMX
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ExportGlossary)}
            >
              <div>
                <Download size={20} /> Export Glossary
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(ShareResource)}
            >
              <div>
                <Share size={20} /> Share resource
              </div>
            </MenuButtonItem>
            <MenuButtonItem
              className="tm-key-row-button-item"
              onMouseDown={() => handleExpandeRow(DeleteResource)}
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
