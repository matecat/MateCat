import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import OrderBox from './OrderBox'

jest.mock('../../common/DropdownMenu/DropdownMenu', () => ({
  DropdownMenu: ({toggleButtonProps, items}) => (
    <div>
      {toggleButtonProps.children}
      {items.map((item) => (
        <button key={item.label} onClick={item.onClick}>
          {item.label}
        </button>
      ))}
    </div>
  ),
}))

const baseProps = {
  price: '100.00',
  priceCurrencySymbol: '€',
  pricePWord: '0.10',
  outsourceConfirmed: false,
  jobOutsourced: false,
  onSendOutsource: jest.fn(),
  onOpenOutsourcePage: jest.fn(),
  onCurrencyChange: jest.fn(),
}

describe('OrderBox', () => {
  test('renders the price and per-word breakdown', () => {
    render(<OrderBox {...baseProps} />)
    expect(screen.getByText('€ 100.00')).toBeInTheDocument()
    expect(screen.getByText('= € 0.10 / word')).toBeInTheDocument()
  })

  test('shows "Order now" and triggers onSendOutsource when not confirmed', () => {
    const onSendOutsource = jest.fn()
    render(<OrderBox {...baseProps} onSendOutsource={onSendOutsource} />)
    fireEvent.click(screen.getByText('Order now'))
    expect(onSendOutsource).toHaveBeenCalledTimes(1)
  })

  test('shows "Confirm" and triggers onSendOutsource when confirmed but not yet outsourced', () => {
    const onSendOutsource = jest.fn()
    render(
      <OrderBox
        {...baseProps}
        outsourceConfirmed={true}
        onSendOutsource={onSendOutsource}
      />,
    )
    fireEvent.click(screen.getByText('Confirm'))
    expect(onSendOutsource).toHaveBeenCalledTimes(1)
  })

  test('shows "View status" and triggers onOpenOutsourcePage once outsourced', () => {
    const onOpenOutsourcePage = jest.fn()
    render(
      <OrderBox
        {...baseProps}
        outsourceConfirmed={true}
        jobOutsourced={true}
        onOpenOutsourcePage={onOpenOutsourcePage}
      />,
    )
    fireEvent.click(screen.getByText('View status'))
    expect(onOpenOutsourcePage).toHaveBeenCalledTimes(1)
  })

  test('lists currency options and calls onCurrencyChange when one is chosen', () => {
    const onCurrencyChange = jest.fn()
    render(<OrderBox {...baseProps} onCurrencyChange={onCurrencyChange} />)
    fireEvent.click(screen.getByText('US dollar (USD)'))
    expect(onCurrencyChange).toHaveBeenCalledWith('USD')
  })
})
