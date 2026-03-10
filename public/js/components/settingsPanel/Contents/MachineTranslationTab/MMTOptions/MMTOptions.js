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
      <div className="mt-params-option">
        <div>
          <h3>Activate context analyzer</h3>
          <p>
            Choose whether to analyze the source content against all your
            translation memories and automatically identify the most relevant
            ones for adaptation.
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
      <h2>Glossaries</h2>
      <div className="mt-params-option">
        <div>
          <h3>Case-sensitive matching</h3>
          <p>
            Choose whether glossary terms must match case exactly.
            <br />
            If enabled, only terms with the same capitalization are recognized
            and applied (e.g. the glossary translation for 'apple' won’t be
            applied when 'Apple' appears in the source text).
          </p>
        </div>
        <Controller
          control={control}
          name="mmt_ignore_glossary_case"
          disabled={isCattoolPage}
          render={({field: {onChange, value, name, disabled}}) => (
            <Switch
              name={name}
              active={!value}
              onChange={() => onChange(!value)}
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
