import React, {useState} from 'react'
import {Select} from '../../../common/Select'
import {Controller, useForm} from 'react-hook-form'

export const Intento = ({addMTEngine}) => {
  const [provider, setProvider] = useState()
  const {
    register,
    handleSubmit,
    control,
    formState: {errors},
  } = useForm()
  const onSubmit = (data) => {
    addMTEngine(data)
  }
  const selectOptions = config.intento_providers
    ? Object.values(config.intento_providers)
    : []
  return (
    <div className="add-provider-container">
      <div className="add-provider-fields">
        <div className="provider-data">
          <div className="provider-field">
            <label>
              Engine Name<sup>*</sup>
            </label>
            <input
              className="new-engine-name required"
              type="text"
              {...register('name', {required: true})}
            />
          </div>
          <div className="provider-field">
            <label>
              Api key<sup>*</sup>
            </label>
            <input
              className="required"
              name="secret"
              type="text"
              {...register('secret', {required: true})}
            />
          </div>
          <div className="provider-field">
            <label>
              Providers<sup>*</sup>
            </label>
            <Controller
              name="provider"
              control={control}
              rules={{required: true}}
              render={() => (
                <Select
                  placeholder="Choose provider"
                  options={selectOptions}
                  activeOption={provider}
                  onSelect={(option) => setProvider(option)}
                />
              )}
            />
          </div>
          <div className="provider-field">
            <label>Provider auth data</label>
            <input
              className="required"
              type="text"
              {...register('providerkey')}
            />
            <span>{provider ? `Example: ${provider.auth_example}` : null}</span>
          </div>
          <div className="provider-field">
            <label>Custom Model</label>
            <input
              className="required"
              name="providercategory"
              type="text"
              {...register('providercategory')}
            />
          </div>

          <button
            className="ui primary button"
            onClick={handleSubmit(onSubmit)}
          >
            Confirm
          </button>
        </div>
      </div>
      <div className="add-provider-message">
        <p>
          A performance of third-party AI models varies up to 4x case by case.
          It depends on how their training data is similar to yours and you
          never know. Given 100x difference in price, the wrong choice may be a
          complete miss. We help to source, evaluate and use the right models in
          a vendor-agnostic fashion.
        </p>
        <a
          href="https://inten.to"
          target="_blank"
          rel="noreferrer"
          className="ui positive button"
        >
          Intento
        </a>
      </div>
    </div>
  )
}
