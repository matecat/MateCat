import React from 'react'
import DatePicker from 'react-datepicker'

import {GMTSelect} from '../GMTSelect'
import {Select} from '../../common/Select'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../../common/Button/Button'
import HelpCircle from '../../../../img/icons/HelpCircle'
import {timeOptions} from '../outsourceConstants'

import 'react-datepicker/dist/react-datepicker.css'

const DeliverySection = ({
  delivery,
  errorQuote,
  needItFaster,
  outsourceConfirmed,
  errorPastDate,
  quoteNotAvailable,
  showDateMessage,
  deliveryDate,
  selectedTime,
  onChangeTimezone,
  onToggleNeedItFaster,
  onDateChange,
  onTimeChange,
  onGetNewRates,
  extendedView,
}) => {
  if (errorQuote) {
    return (
      <div className="delivery-order-not-available">
        <div className="quote-not-available-message">
          Quote not available, please contact us at info@translated.net or call
          +39 06 90 254 001
        </div>
      </div>
    )
  }

  if (needItFaster) {
    return (
      <div className="need-it-faster-box">
        <div className="fields">
          <div className="field">
            <label>Delivery Date</label>
            <DatePicker selected={deliveryDate} onChange={onDateChange} />
          </div>
          <div className="field input-time">
            <Select
              label="Time"
              onSelect={({id}) => onTimeChange(id)}
              activeOption={timeOptions.find(({id}) => id === selectedTime)}
              options={timeOptions}
            />
          </div>
          <div className="field gmt">
            <GMTSelect showLabel={true} changeValue={onChangeTimezone} />
          </div>
          <div className="field-buttons">
            <Button
              type={BUTTON_TYPE.PRIMARY}
              mode={BUTTON_MODE.OUTLINE}
              className="get-price"
              onClick={onGetNewRates}
            >
              Get Price
            </Button>

            <Button mode={BUTTON_MODE.OUTLINE} onClick={onToggleNeedItFaster}>
              Close
            </Button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="delivery-box">
      <label>Delivery date:</label>
      <div className={'delivery-date'}>
        {delivery.day + ' ' + delivery.month} at {delivery.time}
      </div>
      <div className={'delivery-options'}>
        <div className="gmt">
          <GMTSelect changeValue={onChangeTimezone} />
        </div>
        {!outsourceConfirmed && (
          <div className="need-it-faster">
            {errorPastDate && (
              <div className="errors-date past-date">
                * Chosen delivery date is in the past
              </div>
            )}
            {quoteNotAvailable && (
              <div className="errors-date generic-error">
                * Deadline too close, pick another one.
              </div>
            )}
            {showDateMessage && (
              <div className="errors-date too-far-date">
                We will deliver before the selected date
                <div
                  className="tip"
                  data-tooltip="This date already provides us with all the time we need to deliver quality work at the lowest price"
                  data-position="bottom center"
                  data-variation="wide"
                >
                  <HelpCircle />
                </div>
              </div>
            )}
            {extendedView && (
              <div className="faster" onClick={onToggleNeedItFaster}>
                Need it faster?
              </div>
            )}
          </div>
        )}
      </div>
      {outsourceConfirmed && (
        <div className="confirm-delivery-box">
          <div className="confirm-title">Order sent correctly</div>
          <p>
            Thank you for choosing our Outsource service
            <br />
            You will soon be contacted by a Account Manager to send you an
            invoice
          </p>
        </div>
      )}
    </div>
  )
}

export default DeliverySection
