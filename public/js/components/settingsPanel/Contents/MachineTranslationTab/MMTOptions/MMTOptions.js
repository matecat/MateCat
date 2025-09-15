import React, {useEffect, useRef, useState} from 'react'
import {Controller, useForm} from 'react-hook-form'
import Switch from '../../../../common/Switch'
import {isEqual} from 'lodash'

export const MMTOptions = () => {
  const {control, watch, setValue} = useForm()

  const temporaryFormData = watch()
  const previousData = useRef()

  const [formData, setFormData] = useState()

  useEffect(() => {
    if (!isEqual(temporaryFormData, previousData.current))
      setFormData(temporaryFormData)

    previousData.current = temporaryFormData
  }, [temporaryFormData])

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
          name="activate_content_analyzer"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
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
          name="pretranslate"
          render={({field: {onChange, value, name}}) => (
            <Switch name={name} active={value} onChange={onChange} />
          )}
        />
      </div>
    </div>
  )
}
