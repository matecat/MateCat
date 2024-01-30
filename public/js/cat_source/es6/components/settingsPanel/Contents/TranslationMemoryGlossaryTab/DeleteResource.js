import React, {useContext, useEffect, useRef} from 'react'
import PropTypes from 'prop-types'
import {deleteTmKey} from '../../../../api/deleteTmKey'
import {TranslationMemoryGlossaryTabContext} from './TranslationMemoryGlossaryTab'
import CatToolActions from '../../../../actions/CatToolActions'

import Checkmark from '../../../../../../../img/icons/Checkmark'
import Close from '../../../../../../../img/icons/Close'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import CreateProjectActions from '../../../../actions/CreateProjectActions'

export const DeleteResource = ({row, onClose}) => {
  const {setTmKeys, projectTemplates, availableTemplateProps} =
    useContext(SettingsPanelContext)
  const {setNotification} = useContext(TranslationMemoryGlossaryTabContext)

  const tmOutOnCloseRef = useRef()

  useEffect(() => {
    return () => clearTimeout(tmOutOnCloseRef.current)
  }, [])

  const onClickConfirm = () => {
    deleteTmKey({key: row.key})
      .then(() => {
        setTmKeys((prevState) => prevState.filter(({key}) => key !== row.key))
        if (APP.isCattool) {
          CatToolActions.onTMKeysChangeStatus()
        } else {
          const tmProp = availableTemplateProps.tm

          const templatesInvolved = projectTemplates
            .filter((template) =>
              template[tmProp].some(({key}) => key === row.key),
            )
            .map((template) => ({
              ...template,
              [tmProp]: template[tmProp].filter(({key}) => key !== row.key),
            }))

          CreateProjectActions.updateProjectTemplates({
            templates: templatesInvolved,
            modifiedPropsCurrentProjectTemplate: {
              [tmProp]: templatesInvolved.find(
                ({isTemporary}) => isTemporary,
              )?.[tmProp],
            },
          })
        }
        tmOutOnCloseRef.current = setTimeout(onClose, 2000)
      })
      .catch(() => {
        setNotification({
          type: 'error',
          message: 'There was an error saving your data. Please retry!',
          rowKey: row.key,
        })
        onClose()
      })
  }

  const onClickClose = () => {
    setNotification({})
    onClose()
  }

  return (
    <div className="translation-memory-glossary-tab-delete">
      <div className="action-form">
        <div>
          <span>
            Do you really want to delete this resource (<b>{row.key}</b>)
          </span>
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <button
            className="ui primary button settings-panel-button-icon confirm-button"
            onClick={onClickConfirm}
          >
            <Checkmark size={12} />
            Confirm
          </button>

          <button
            className="ui button orange close-button"
            onClick={onClickClose}
          >
            <Close size={18} />
          </button>
        </div>
      </div>
    </div>
  )
}

DeleteResource.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
