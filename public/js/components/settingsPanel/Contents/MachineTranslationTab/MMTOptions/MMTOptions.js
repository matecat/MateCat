import React, {useCallback, useContext} from 'react'
import PropTypes from 'prop-types'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {MTGlossary} from '../MTGlossary/MTGlossary'
import useOptions from '../useOptions'

export const MMTOptions = ({isCattoolPage}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {control, setValue} = useOptions()

  const setGlossaries = useCallback(
    (value) => setValue('mmt_glossaries', value),
    [setValue],
  )

  return (
    <div className="options-container-content">
      <div className="mt-params-option">
        <div>
          <h3>Activate context analyzer</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="mmt_activate_context_analyzer"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Switch
              name={name}
              active={value}
              onChange={onChange}
              disabled={disabled}
            />
          )}
        />
      </div>
      <div className="mt-params-option">
        <div>
          <h3>Pre-translate files</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="pre_translate_files"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Switch
              name={name}
              active={value}
              onChange={onChange}
              disabled={disabled}
            />
          )}
        />
      </div>
      <h2>Glossary</h2>
      <div className="mt-params-option">
        <div>
          <h3>Case-sensitive matching</h3>
          <p>
            Lorem ipsum dolor sit amet consectetur. Nullam a vitae augue cras
            pharetra. Proin mauris velit nisi feugiat ultricies tortor velit
            condimentum.
          </p>
        </div>
        <Controller
          control={control}
          name="mmt_glossaries_case_sensitive_matching"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Switch
              name={name}
              active={value}
              onChange={onChange}
              disabled={disabled}
            />
          )}
        />
      </div>
      <MTGlossary
        id={currentProjectTemplate.mt.id}
        {...{setGlossaries, isCattoolPage}}
      />
    </div>
  )
}

MMTOptions.propTypes = {
  isCattoolPage: PropTypes.bool,
}
