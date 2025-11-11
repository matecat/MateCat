import React from 'react'
import PropTypes from 'prop-types'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'

export const BasicOptions = ({isCattoolPage}) => {
  const {control} = useOptions()

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
    </div>
  )
}

BasicOptions.propTypes = {
  isCattoolPage: PropTypes.bool,
}
