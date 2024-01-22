import React from 'react'
import {useForm} from 'react-hook-form'

export const SmartMate = ({addMTEngine, error, isRequestInProgress}) => {
  const {
    register,
    handleSubmit,
    formState: {errors},
  } = useForm()
  const onSubmit = (data) => {
    addMTEngine(data)
  }
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
              User<sup>*</sup>
            </label>
            <input
              className="required"
              type="text"
              {...register('client_id', {required: true})}
            />
            {errors.client_id && (
              <span className="field-error">Required field</span>
            )}
          </div>
          <div className="provider-field">
            <label>
              Key<sup>*</sup>
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
          <strong>SmartMATE</strong> is the software toolkit created by Capita
          TI that creates, manages and hosts MT engines, solutions and services.
          Finely tuned on our client’s data, SmartMATE engines can be used as an
          additional productivity tool in the localization process, as well as
          used to produce a fully automated translation used for gisting
          purposes.
        </p>
        <p>
          Based on the MOSES statistical machine translation model,{' '}
          <strong>SmartMATE</strong> has been developed as a secure environment,
          offering customers a viable and cost-effective alternative to publicly
          available machine translation solutions.
        </p>
        <p>
          More info on{' '}
          <a href="https://www.smartmate.co/" title="SmartMATE">
            https://www.smartmate.co/
          </a>
        </p>
        <a
          href="mailto:enquiries@smartmate.co"
          rel="noreferrer"
          className="ui positive button"
        >
          Contact SmartMATE
        </a>
      </div>
    </div>
  )
}
