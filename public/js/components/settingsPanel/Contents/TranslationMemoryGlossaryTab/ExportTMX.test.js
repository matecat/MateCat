jest.mock('./hooks/useExport')
jest.mock('../../../../actions/CatToolActions')

import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {ExportTMX} from './ExportTMX'
import useExport, {EXPORT_TYPE} from './hooks/useExport'
import CatToolActions from '../../../../actions/CatToolActions'

const row = {key: 'the-key', name: 'the-name'}

const baseHookReturn = {
  email: 'me@example.com',
  status: undefined,
  onSubmit: jest.fn(),
  onReset: jest.fn(),
}

beforeEach(() => {
  jest.clearAllMocks()
  useExport.mockReturnValue(baseHookReturn)
})

test('uses the tmx export type and passes row/onClose through to the hook', () => {
  const onClose = jest.fn()
  render(<ExportTMX row={row} onClose={onClose} />)

  expect(useExport).toHaveBeenCalledWith({type: EXPORT_TYPE.tmx, row, onClose})
})

test('renders the export explanation text and the strip-tags checkbox', () => {
  render(<ExportTMX row={row} onClose={jest.fn()} />)

  expect(
    screen.getByText(/We will send a link to download the exported TM/),
  ).toBeInTheDocument()
  expect(screen.getByText('Export TM without tags')).toBeInTheDocument()
})

test('submitting the form passes the current strip-tags checkbox state to onSubmit', () => {
  const onSubmit = jest.fn((e) => e.preventDefault())
  useExport.mockReturnValue({...baseHookReturn, onSubmit})

  render(<ExportTMX row={row} onClose={jest.fn()} />)

  const checkbox = screen.getByRole('checkbox')
  fireEvent.click(checkbox)
  fireEvent.submit(checkbox.form)

  expect(onSubmit).toHaveBeenCalledTimes(1)
  const [, stripTags] = onSubmit.mock.calls[0]
  expect(stripTags).toBe(true)
})

test('submitting without toggling the checkbox sends stripTags as false', () => {
  const onSubmit = jest.fn((e) => e.preventDefault())
  useExport.mockReturnValue({...baseHookReturn, onSubmit})

  render(<ExportTMX row={row} onClose={jest.fn()} />)
  fireEvent.submit(screen.getByRole('checkbox').form)

  const [, stripTags] = onSubmit.mock.calls[0]
  expect(stripTags).toBe(false)
})

test('resetting the form calls the hook-provided onReset', () => {
  const onReset = jest.fn()
  useExport.mockReturnValue({...baseHookReturn, onReset})

  render(<ExportTMX row={row} onClose={jest.fn()} />)
  fireEvent.reset(screen.getByRole('checkbox').form)

  expect(onReset).toHaveBeenCalledTimes(1)
})

test('disables the submit button while the export is in an error state', () => {
  useExport.mockReturnValue({
    ...baseHookReturn,
    status: {errors: {message: 'boom'}},
  })

  render(<ExportTMX row={row} onClose={jest.fn()} />)

  expect(screen.getByRole('button', {name: 'Confirm'})).toBeDisabled()
})

test('notifies success with the destination email once the export succeeds', () => {
  useExport.mockReturnValue({
    ...baseHookReturn,
    email: 'someone@example.com',
    status: {successfull: true},
  })

  render(<ExportTMX row={row} onClose={jest.fn()} />)

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Request submitted',
      text: 'You will receive the link at someone@example.com',
      type: 'success',
    }),
  )
})
