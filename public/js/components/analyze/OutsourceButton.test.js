import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import OutsourceButton from './OutsourceButton'
import {ANALYSIS_STATUS} from '../../constants/Constants'

test('shows a disabled tooltip button when not outsource_available and custom_payable_rate is set', () => {
  const chunk = {
    outsource_available: false,
    outsource_info: {custom_payable_rate: true},
  }
  render(
    <OutsourceButton
      chunk={chunk}
      index={1}
      status={ANALYSIS_STATUS.DONE}
      openOutsourceModal={jest.fn()}
    />,
  )
  const button = screen.getByText('Buy Translation').closest('button')
  expect(button).toBeDisabled()
})

test('renders an enabled button that calls openOutsourceModal when status is DONE', async () => {
  const spy = jest.fn()
  const chunk = {outsource_available: true, outsource_info: {}}
  const openOutsourceModal = jest.fn((index, chunkArg) => (e) => {
    spy(index, chunkArg)
  })
  render(
    <OutsourceButton
      chunk={chunk}
      index={7}
      status={ANALYSIS_STATUS.DONE}
      openOutsourceModal={openOutsourceModal}
    />,
  )
  const button = screen.getByText('Buy Translation').closest('button')
  expect(button).toBeEnabled()

  await userEvent.click(button)
  expect(spy).toHaveBeenCalledWith(7, chunk)
})

test('disables the button when status is not DONE', () => {
  const chunk = {outsource_available: true, outsource_info: {}}
  render(
    <OutsourceButton
      chunk={chunk}
      index={1}
      status={ANALYSIS_STATUS.NEW}
      openOutsourceModal={jest.fn(() => () => {})}
    />,
  )
  expect(screen.getByText('Buy Translation').closest('button')).toBeDisabled()
})
