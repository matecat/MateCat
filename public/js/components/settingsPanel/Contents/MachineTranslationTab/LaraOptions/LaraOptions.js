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
            Choose whether to automatically translate project files during the
            analysis phase. Pre-translation may generate additional charges from
            your MT provider.
          </p>
        </div>
        <Controller
          control={control}
          name="enable_mt_analysis"
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
