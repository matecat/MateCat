import React, {Fragment, useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import Trash from '../../../../../../../../img/icons/Trash'
import {MachineTranslationTabContext} from '../MachineTranslationTab'
import {deleteDeepLGlossary} from '../../../../../api/deleteDeepLGlossary'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import CreateProjectActions from '../../../../../actions/CreateProjectActions'

export const DeepLGlossaryRow = ({engineId, row, setRows, isReadOnly}) => {
  const {setNotification} = useContext(MachineTranslationTabContext)
  const {projectTemplates} = useContext(SettingsPanelContext)

  const [isActive, setIsActive] = useState(false)
  const [isWaitingResult, setIsWaitingResult] = useState(false)

  useEffect(() => {
    setIsActive(row.isActive)
  }, [row.isActive])

  const onChangeIsActive = (e) => {
    setNotification()
    const isActive = e.currentTarget.checked
    setRows((prevState) =>
      prevState.map((glossary) => ({
        ...glossary,
        isActive: isActive && glossary.id === row.id,
      })),
    )
  }

  const deleteGlossary = () => {
    setNotification()

    setIsWaitingResult(true)
    deleteDeepLGlossary({engineId, id: row.id})
      .then((data) => {
        if (data.id === row.id) {
          setRows((prevState) => prevState.filter(({id}) => id !== row.id))

          const templatesInvolved = projectTemplates
            .filter(
              (template) => template.mt.extra.deepl_id_glossary === row.id,
            )
            .map((template) => {
              const mtObject = template.mt
              const {deepl_id_glossary, ...extra} = mtObject.extra // eslint-disable-line

              return {
                ...template,
                mt: {
                  ...mtObject,
                  extra: {
                    ...extra,
                    ...(deepl_id_glossary !== row.id && {
                      deepl_id_glossary,
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

  return (
    <Fragment>
      <div className="align-center">
        <input
          name="active"
          checked={isActive}
          onChange={onChangeIsActive}
          type="checkbox"
          disabled={isWaitingResult || isReadOnly}
        />
      </div>
      <div className="glossary-row-name">
        <div className="tooltip-input-name">
          <div className="glossary-row-name-input glossary-deepl-row-name-input">
            {row.name}
          </div>
        </div>
      </div>
      {!isReadOnly && (
        <>
          <div className="glossary-row-import-button" />
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

DeepLGlossaryRow.propTypes = {
  engineId: PropTypes.number,
  row: PropTypes.object,
  setRows: PropTypes.func,
  isReadOnly: PropTypes.bool,
}
