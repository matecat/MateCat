import React, {useState} from 'react'
import {Select} from '../../../common/Select'

export const Intento = () => {
  const [provider, setProvider] = useState()
  //const selectOptions = config.intentoProviders ? config.intentoProviders.filter()
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
              Api key<sup>*</sup>
            </label>
            <input className="required" name="secret" type="text" />
          </div>
          <div className="provider-field">
            <label>
              Providers<sup>*</sup>
            </label>
            <Select
              placeholder="Choose provider"
              name="provider"
              options={[
                {
                  id: 'Test',
                  name: 'provider',
                  auth_example:
                    '{"appid":"Your APP ID","access_key":"Your Access Key"}',
                },
              ]}
              activeOption={provider}
              onSelect={(option) => setProvider(option)}
            />
          </div>
          <div className="provider-field">
            <label>Provider auth data</label>
            <input className="required" name="providerkey" type="text" />
            <span>{provider ? `Example: ${provider.auth_example}` : null}</span>
          </div>
          <div className="provider-field">
            <label>Custom Model</label>
            <input className="required" name="providercategory" type="text" />
          </div>

          <button className="ui primary button">Confirm</button>
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
