import React from 'react'
import {Select} from '../../../../common/Select'
import {Controller, useForm} from 'react-hook-form'

export const Intento = ({addMTEngine, error, isRequestInProgress}) => {
  const {
    register,
    handleSubmit,
    control,
    watch,
    formState: {errors},
  } = useForm()
  const onSubmit = (data) => {
    addMTEngine(data)
  }
  const selectOptions = config.intento_providers
    ? Object.values(config.intento_providers)
    : []
  const provider = watch('provider')
  console.log('Errors', errors)
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
            {errors.name && <span className="field-error">Required field</span>}
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
            {errors.secret && (
              <span className="field-error">Required field</span>
            )}
          </div>
          <div className="provider-field">
            <label>
              Providers<sup>*</sup>
            </label>
            <Controller
              name="provider"
              control={control}
              rules={{required: true}}
              render={({
                field: {onChange, value, name},
                fieldState: {error},
              }) => (
                <Select
                  name={name}
                  placeholder="Choose provider"
                  options={selectOptions}
                  activeOption={value}
                  onSelect={(option) => onChange(option)}
                  error={error}
                />
              )}
            />
            {errors.provider && (
              <span className="field-error">Required field</span>
            )}
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

          <div className="provider-field">
            {error && <span className={'mt-error'}>{error.message}</span>}
            <button
              className="ui primary button"
              disabled={isRequestInProgress}
              onClick={handleSubmit(onSubmit)}
            >
              Confirm
            </button>
          </div>
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
