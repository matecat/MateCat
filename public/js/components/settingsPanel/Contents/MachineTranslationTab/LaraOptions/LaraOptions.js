import React, {useCallback, useContext} from 'react'
import PropTypes from 'prop-types'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {LaraGlossary} from '../LaraGlossary/LaraGlossary'

export const LaraOptions = ({isCattoolPage}) => {
  const {currentProjectTemplate} = useContext(SettingsPanelContext)

  const {control, setValue} = useOptions()

  const setGlossaries = useCallback(
    (value) => setValue('lara_glossaries', value),
    [setValue],
  )

  return (
    <div className="options-container-content">
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
      <LaraGlossary
        id={currentProjectTemplate.mt.id}
        {...{setGlossaries, isCattoolPage}}
      />
    </div>
  )
}

LaraOptions.propTypes = {
  isCattoolPage: PropTypes.bool,
}
