import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import DeliverySection from './DeliverySection'

jest.mock('react-datepicker', () => ({
  __esModule: true,
  default: ({selected, onChange}) => (
    <input
      data-testid="datepicker"
      value={selected ? selected.toISOString() : ''}
      onChange={(e) => onChange(new Date(e.target.value))}
      readOnly
    />
  ),
}))

jest.mock('../GMTSelect', () => ({
  GMTSelect: ({changeValue, showLabel}) => (
    <button onClick={() => changeValue('3')}>
      gmt-select{showLabel ? '-labeled' : ''}
    </button>
  ),
}))

jest.mock('../../common/Select', () => ({
  Select: ({label, options, activeOption, onSelect}) => (
    <div>
      <span>{label}</span>
      <span data-testid="active-time">{activeOption?.name}</span>
      {options.map((option) => (
        <button key={option.id} onClick={() => onSelect(option)}>
          {option.name}
        </button>
      ))}
    </div>
  ),
}))

const baseDelivery = {day: '20', month: 'Jul', time: '2:00 PM'}

const baseProps = {
  delivery: baseDelivery,
  errorQuote: false,
  needItFaster: false,
  outsourceConfirmed: false,
  errorPastDate: false,
  quoteNotAvailable: false,
  showDateMessage: false,
  deliveryDate: new Date('2026-07-20T00:00:00.000Z'),
  selectedTime: '14',
  onChangeTimezone: jest.fn(),
  onToggleNeedItFaster: jest.fn(),
  onDateChange: jest.fn(),
  onTimeChange: jest.fn(),
  onGetNewRates: jest.fn(),
  extendedView: true,
}

describe('DeliverySection', () => {
  test('renders the not-available message when errorQuote is true', () => {
    render(<DeliverySection {...baseProps} errorQuote={true} />)
    expect(
      screen.getByText(/Quote not available, please contact us/),
    ).toBeInTheDocument()
  })

  test('renders the "need it faster" fields and wires the callbacks', () => {
    const onDateChange = jest.fn()
    const onTimeChange = jest.fn()
    const onChangeTimezone = jest.fn()
    const onGetNewRates = jest.fn()
    const onToggleNeedItFaster = jest.fn()

    render(
      <DeliverySection
        {...baseProps}
        needItFaster={true}
        onDateChange={onDateChange}
        onTimeChange={onTimeChange}
        onChangeTimezone={onChangeTimezone}
        onGetNewRates={onGetNewRates}
        onToggleNeedItFaster={onToggleNeedItFaster}
      />,
    )

    fireEvent.change(screen.getByTestId('datepicker'), {
      target: {value: '2026-08-01T00:00:00.000Z'},
    })
    expect(onDateChange).toHaveBeenCalled()

    fireEvent.click(screen.getByText('4:00 PM'))
    expect(onTimeChange).toHaveBeenCalledWith('16')

    fireEvent.click(screen.getByText('gmt-select-labeled'))
    expect(onChangeTimezone).toHaveBeenCalledWith('3')

    fireEvent.click(screen.getByText('Get Price'))
    expect(onGetNewRates).toHaveBeenCalled()

    fireEvent.click(screen.getByText('Close'))
    expect(onToggleNeedItFaster).toHaveBeenCalled()
  })

  test('renders the standard delivery box with the "need it faster" link in extended view', () => {
    const onToggleNeedItFaster = jest.fn()
    render(
      <DeliverySection
        {...baseProps}
        onToggleNeedItFaster={onToggleNeedItFaster}
      />,
    )

    expect(screen.getByText('20 Jul at 2:00 PM')).toBeInTheDocument()

    fireEvent.click(screen.getByText('Need it faster?'))
    expect(onToggleNeedItFaster).toHaveBeenCalled()
  })

  test('hides the "need it faster" link outside of extended view', () => {
    render(<DeliverySection {...baseProps} extendedView={false} />)
    expect(screen.queryByText('Need it faster?')).not.toBeInTheDocument()
  })

  test('shows the past-date and generic errors and hides them once confirmed', () => {
    render(
      <DeliverySection
        {...baseProps}
        errorPastDate={true}
        quoteNotAvailable={true}
      />,
    )
    expect(
      screen.getByText('* Chosen delivery date is in the past'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('* Deadline too close, pick another one.'),
    ).toBeInTheDocument()
  })

  test('shows the too-far-date message when showDateMessage is true', () => {
    render(<DeliverySection {...baseProps} showDateMessage={true} />)
    expect(
      screen.getByText('We will deliver before the selected date'),
    ).toBeInTheDocument()
  })

  test('shows the confirmation box and hides need-it-faster options once outsourceConfirmed', () => {
    render(
      <DeliverySection
        {...baseProps}
        outsourceConfirmed={true}
        showDateMessage={true}
        errorPastDate={true}
      />,
    )
    expect(screen.getByText('Order sent correctly')).toBeInTheDocument()
    expect(
      screen.queryByText('* Chosen delivery date is in the past'),
    ).not.toBeInTheDocument()
  })
})
