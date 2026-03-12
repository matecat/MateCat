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
      <div className="delivery-order need-it-faster-box">
        <a className="need-it-faster-close" onClick={onToggleNeedItFaster}>
          <i className="icon-cancel3 icon need-it-faster-close-icon" />
        </a>
        <div className="delivery-box">
          <div className="ui form">
            <div className="fields">
              <div className="field">
                <label>Delivery Date</label>
                <div className="ui calendar">
                  <div className="ui input">
                    <DatePicker
                      selected={deliveryDate}
                      onChange={onDateChange}
                    />
                  </div>
                </div>
              </div>
              <div className="field input-time">
                <Select
                  label="Time"
                  onSelect={({id}) => onTimeChange(id)}
                  activeOption={timeOptions.find(
                    ({id}) => id === selectedTime,
                  )}
                  options={timeOptions}
                />
              </div>
              <div className="field gmt">
                <GMTSelect showLabel={true} changeValue={onChangeTimezone} />
              </div>
              <div className="field">
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  mode={BUTTON_MODE.OUTLINE}
                  className="get-price"
                  onClick={onGetNewRates}
                >
                  Get Price
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="delivery-order">
      <div className="delivery-box">
        <label>Delivery date:</label>
        <div className="delivery-date">
          {delivery.day + ' ' + delivery.month}
        </div>
        <div className="atdd">at</div>
        <div className="delivery-time">{delivery.time}</div>
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
            <a className="faster" onClick={onToggleNeedItFaster}>
              Need it faster?
            </a>
          </div>
        )}
      </div>
    </div>
  )
}

export default DeliverySection

