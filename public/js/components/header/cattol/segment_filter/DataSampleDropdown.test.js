import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'

import {DataSampleDropdown} from './DataSampleDropdown'

beforeEach(() => {
  window.eventHandler = {
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
  }
})

const renderDropdown = (props = {}) => {
  const onChange = jest.fn()
  const onChangeSampleSize = jest.fn()
  const utils = render(
    <DataSampleDropdown
      onChange={onChange}
      onChangeSampleSize={onChangeSampleSize}
      isDisabled={false}
      samplingSize={5}
      samplingType={undefined}
      {...props}
    />,
  )
  return {...utils, onChange, onChangeSampleSize}
}

test('shows the placeholder label when no sampling type is selected', () => {
  renderDropdown()
  expect(screen.getByText('Data sample')).toBeInTheDocument()
})

test('shows the selected sampling type and its sample size', () => {
  renderDropdown({samplingType: 'edit_distance_high_to_low', samplingSize: 30})
  expect(screen.getByText('Edit distance (A - Z) - 30%')).toBeInTheDocument()
})

test('shows the placeholder label when disabled even with a sampling type set', () => {
  renderDropdown({isDisabled: true, samplingType: 'edit_distance_high_to_low'})
  expect(screen.getByText('Data sample')).toBeInTheDocument()
  expect(screen.getByRole('button', {name: /Data sample/})).toBeDisabled()
})

test('toggles the dropdown open state and registers a click listener', () => {
  const {container} = renderDropdown()
  const trigger = screen.getByRole('button', {name: /Data sample/})

  fireEvent.click(trigger)
  expect(
    container.querySelector('.data-sample-dropdown-container.open'),
  ).toBeInTheDocument()
  expect(window.eventHandler.addEventListener).toHaveBeenCalledWith(
    'click.dataSampleDropdown',
    expect.any(Function),
  )

  fireEvent.click(trigger)
  expect(
    container.querySelector('.data-sample-dropdown-container.open'),
  ).not.toBeInTheDocument()
  expect(window.eventHandler.removeEventListener).toHaveBeenCalledWith(
    'click.dataSampleDropdown',
    expect.any(Function),
  )
})

test('selecting a sampling type calls onChange with its value', () => {
  const {onChange} = renderDropdown()
  fireEvent.click(screen.getByText('Regular interval'))
  expect(onChange).toHaveBeenCalledWith('regular_intervals')
})

test('marks the currently selected sampling type as active', () => {
  renderDropdown({samplingType: 'regular_intervals'})

  expect(screen.getByText('Regular interval').closest('li')).toHaveClass(
    'active',
  )
  expect(
    screen.getByText('Segment length (A - Z)').closest('li'),
  ).not.toHaveClass('active')
})

test('updates the sample size when a valid value is entered', () => {
  const {onChangeSampleSize} = renderDropdown()
  const input = screen.getByPlaceholderText('n°')

  fireEvent.change(input, {target: {value: '42'}})

  expect(onChangeSampleSize).toHaveBeenCalledWith(42)
  expect(input.value).toBe('42')
})

test('ignores out of range or invalid sample size values', () => {
  const {onChangeSampleSize} = renderDropdown()
  const input = screen.getByPlaceholderText('n°')

  fireEvent.change(input, {target: {value: '0'}})
  fireEvent.change(input, {target: {value: '101'}})
  fireEvent.change(input, {target: {value: 'abc'}})

  expect(onChangeSampleSize).not.toHaveBeenCalled()
})

test('clicking the close icon resets the filter without toggling the dropdown', () => {
  const {onChange, container} = renderDropdown({
    samplingType: 'regular_intervals',
  })

  const icons = container.querySelectorAll('.trigger-button svg')
  fireEvent.click(icons[0])

  expect(onChange).not.toHaveBeenCalled()
  expect(
    container.querySelector('.data-sample-dropdown-container.open'),
  ).not.toBeInTheDocument()
})

test('supports a custom resetFunction and stops the click from bubbling to the trigger', () => {
  const resetFunction = jest.fn()
  const {container} = renderDropdown({
    samplingType: 'regular_intervals',
    resetFunction,
  })

  const icons = container.querySelectorAll('.trigger-button svg')
  fireEvent.click(icons[0])

  expect(resetFunction).toHaveBeenCalledTimes(1)
})

test('closes the dropdown when clicking outside of it', () => {
  const {container} = renderDropdown()
  fireEvent.click(screen.getByRole('button', {name: /Data sample/}))

  const [, closeDropdown] = window.eventHandler.addEventListener.mock.calls[0]
  act(() => {
    closeDropdown({target: document.body, stopPropagation: jest.fn()})
  })

  expect(
    container.querySelector('.data-sample-dropdown-container.open'),
  ).not.toBeInTheDocument()
  expect(window.eventHandler.removeEventListener).toHaveBeenCalledWith(
    'click.dataSampleDropdown',
    closeDropdown,
  )
})

test('keeps the dropdown open when the click originates inside the wrapper', () => {
  const {container} = renderDropdown()
  fireEvent.click(screen.getByRole('button', {name: /Data sample/}))

  const [, closeDropdown] = window.eventHandler.addEventListener.mock.calls[0]
  const insideElement = container.querySelector(
    '.data-sample-dropdown-container',
  )
  act(() => {
    closeDropdown({target: insideElement, stopPropagation: jest.fn()})
  })

  expect(
    container.querySelector('.data-sample-dropdown-container.open'),
  ).toBeInTheDocument()
})
