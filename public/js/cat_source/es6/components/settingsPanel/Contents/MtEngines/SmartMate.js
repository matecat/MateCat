import React from 'react'

export const SmartMate = () => {
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
              name="engine-name"
              type="text"
            />
          </div>
          <div className="provider-field">
            <label>
              User<sup>*</sup>
            </label>
            <input className="required" name="client_id" type="text" />
          </div>
          <div className="provider-field">
            <label>
              Key<sup>*</sup>
            </label>
            <input className="required" name="secret" type="text" />
          </div>

          <button className="ui primary button">Confirm</button>
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
