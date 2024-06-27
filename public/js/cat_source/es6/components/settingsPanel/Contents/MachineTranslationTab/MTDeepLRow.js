import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import Trash from '../../../../../../../img/icons/Trash'
import {Select} from '../../../common/Select'
import CatToolStore from '../../../../stores/CatToolStore'
import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolActions from '../../../../actions/CatToolActions'
import InfoIcon from '../../../../../../../img/icons/InfoIcon'

const FORMALITIES = [
  {name: 'Default', id: 'default'},
  {name: 'Informal', id: 'prefer_less'},
  {name: 'Formal', id: 'prefer_more'},
]

export const MTDeepLRow = ({row, deleteMT, onCheckboxClick}) => {
  const {currentProjectTemplate, modifyingCurrentTemplate} =
    useContext(SettingsPanelContext)

  const activeMTEngine = currentProjectTemplate.mt?.id
  const {mt: {extra: deeplGlossaryProps} = {}} = currentProjectTemplate ?? {}

  const formalityAlreadySelected = deeplGlossaryProps?.deepl_formality

  const [formalityOptions, setFormalityOptions] = useState(FORMALITIES)

  useEffect(() => {
    if (typeof formalityAlreadySelected === 'undefined') {
      modifyingCurrentTemplate((prevTemplate) => {
        const prevMt = prevTemplate.mt
        const prevMTExtra = prevMt?.extra ?? {}

        return {
          ...prevTemplate,
          mt: {
            ...prevMt,
            extra: {
              ...prevMTExtra,
              deepl_formality: FORMALITIES[0].id,
            },
          },
        }
      })
    } else {
      setFormalityOptions((prevState) =>
        prevState.map((option) => ({
          ...option,
          isActive: option.id === formalityAlreadySelected,
        })),
      )
    }
  }, [formalityAlreadySelected, modifyingCurrentTemplate])

  useEffect(() => {
    const getJobMetadata = ({jobMetadata: {project} = {}}) => {
      setFormalityOptions((prevState) =>
        prevState.map((option) => ({
          ...option,
          isActive: option.id === project.deepl_formality,
        })),
      )
    }

    if (config.is_cattool) {
      CatToolStore.addListener(
        CatToolConstants.GET_JOB_METADATA,
        getJobMetadata,
      )
      CatToolActions.getJobMetadata({
        idJob: config.id_job,
        password: config.password,
      })
    }

    return () => {
      CatToolStore.removeListener(
        CatToolConstants.GET_JOB_METADATA,
        getJobMetadata,
      )
    }
  }, [])

  const formalitySelect = (
    <Select
      name="formality"
      options={formalityOptions}
      activeOption={formalityOptions.find(({isActive}) => isActive)}
      onSelect={(option) => {
        setFormalityOptions((prevState) =>
          prevState.map((optionItem) => ({
            ...optionItem,
            isActive: optionItem.id === option.id,
          })),
        )

        modifyingCurrentTemplate((prevTemplate) => {
          const prevMt = prevTemplate.mt
          const prevMTExtra = prevMt?.extra ?? {}

          return {
            ...prevTemplate,
            mt: {
              ...prevMt,
              extra: {
                ...prevMTExtra,
                deepl_formality: option.id,
              },
            },
          }
        })
      }}
    />
  )

  return (
    <>
      <div className="settings-panel-mt-row">
        {row.name}
        <a href="https://guides.matecat.com/my" target="_blank">
          <InfoIcon />
        </a>
      </div>
      <div className="settings-panel-mt-row-description">{row.description}</div>
      <div className="settings-panel-cell-center">
        {!config.is_cattool
          ? formalitySelect
          : formalityOptions.find(({isActive}) => isActive)?.name}
      </div>
      {!config.is_cattool && (
        <div className="settings-panel-cell-center">
          <input
            type="checkbox"
            checked={row.id === activeMTEngine ? true : false}
            onChange={() => onCheckboxClick(row)}
          ></input>
        </div>
      )}
      {!row.default && !config.is_cattool && (
        <div className="settings-panel-cell-center">
          <button className="grey-button" onClick={deleteMT}>
            <Trash size={12} />
            Delete
          </button>
        </div>
      )}
      {config.is_cattool && row.id === activeMTEngine && (
        <>
          <div></div>
          <div className="settings-panel-cell-center">Enabled</div>
        </>
      )}
    </>
  )
}

MTDeepLRow.propTypes = {
  row: PropTypes.object.isRequired,
  deleteMT: PropTypes.func,
  onCheckboxClick: PropTypes.func,
}
