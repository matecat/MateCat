import React from 'react'

import {DropdownMenu} from '../../common/DropdownMenu/DropdownMenu'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../../common/Button/Button'
import {currencies, formatPriceWithCommas} from '../outsourceConstants'

const OrderBox = ({
  price,
  priceCurrencySymbol,
  pricePWord,
  outsourceConfirmed,
  jobOutsourced,
  onSendOutsource,
  onOpenOutsourcePage,
  onCurrencyChange,
}) => (
  <div className="order-box-outsource">
    <div className="order-box">
      <div className="outsource-price">
        {priceCurrencySymbol} {formatPriceWithCommas(price)}
      </div>
      <DropdownMenu
        toggleButtonProps={{
          children: (
            <a className="price-pw">
              about {priceCurrencySymbol} {pricePWord} / word
            </a>
          ),
          mode: BUTTON_MODE.LINK,
        }}
        items={Object.keys(currencies).map((key) => ({
          label: currencies[key].name,
          onClick: () => onCurrencyChange(key),
        }))}
      />
    </div>
    <div className="order-button-outsource">
      {!outsourceConfirmed ? (
        <Button
          type={BUTTON_TYPE.SUCCESS}
          className="open-order"
          id="accept-outsource-quote"
          onClick={onSendOutsource}
        >
          Order now
        </Button>
      ) : !jobOutsourced ? (
        <Button
          type={BUTTON_TYPE.SUCCESS}
          className="open-order"
          id="accept-outsource-quote"
          onClick={onSendOutsource}
        >
          Confirm
        </Button>
      ) : (
        <Button
          type={BUTTON_TYPE.SUCCESS}
          className="open-outsourced"
          id="accept-outsource-quote"
          onClick={onOpenOutsourcePage}
        >
          View status
        </Button>
      )}
    </div>
  </div>
)

export default OrderBox

