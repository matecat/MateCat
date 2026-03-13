import React from 'react'

import {DropdownMenu} from '../../common/DropdownMenu/DropdownMenu'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import {currencies, formatWithCommas} from '../outsourceConstants'

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
        {priceCurrencySymbol} {formatWithCommas(price)}
      </div>
      <DropdownMenu
        toggleButtonProps={{
          children: (
            <a className="price-pw">
              = {priceCurrencySymbol} {pricePWord} / word
            </a>
          ),
          mode: BUTTON_MODE.LINK,
          size: BUTTON_SIZE.LINK_MEDIUM,
        }}
        items={Object.keys(currencies).map((key) => ({
          label: currencies[key].name,
          onClick: () => onCurrencyChange(key),
        }))}
      />
    </div>
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
)

export default OrderBox
