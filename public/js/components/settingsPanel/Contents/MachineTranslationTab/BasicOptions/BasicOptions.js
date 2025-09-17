import React from 'react'
import useOptions from '../useOptions'
import {Controller} from 'react-hook-form'
import Switch from '../../../../common/Switch'

export const BasicOptions = () => {
  const {control} = useOptions()

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
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>
    </div>
  )
}
