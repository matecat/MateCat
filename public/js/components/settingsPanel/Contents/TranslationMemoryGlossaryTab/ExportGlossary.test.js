jest.mock('./hooks/useExport')
jest.mock('../../../../actions/CatToolActions')

import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {ExportGlossary} from './ExportGlossary'
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

test('uses the glossary export type and passes row/onClose through to the hook', () => {
  const onClose = jest.fn()
  render(<ExportGlossary row={row} onClose={onClose} />)

  expect(useExport).toHaveBeenCalledWith({
    type: EXPORT_TYPE.glossary,
    row,
    onClose,
  })
})

test('renders the export explanation text', () => {
  render(<ExportGlossary row={row} onClose={jest.fn()} />)

  expect(
    screen.getByText(/We will send a link to download the exported termbase/),
  ).toBeInTheDocument()
})

test('submitting the form calls the hook-provided onSubmit', () => {
  const onSubmit = jest.fn((e) => e.preventDefault())
  useExport.mockReturnValue({...baseHookReturn, onSubmit})

  render(<ExportGlossary row={row} onClose={jest.fn()} />)
  const form = screen.getByRole('button', {name: 'Confirm'}).form
  fireEvent.submit(form)

  expect(onSubmit).toHaveBeenCalledTimes(1)
})

test('resetting the form calls the hook-provided onReset', () => {
  const onReset = jest.fn()
  useExport.mockReturnValue({...baseHookReturn, onReset})

  render(<ExportGlossary row={row} onClose={jest.fn()} />)
  const form = screen.getByRole('button', {name: 'Confirm'}).form
  fireEvent.reset(form)

  expect(onReset).toHaveBeenCalledTimes(1)
})

test('disables the submit button while the export is in an error state', () => {
  useExport.mockReturnValue({
    ...baseHookReturn,
    status: {errors: {message: 'boom'}},
  })

  render(<ExportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.getByRole('button', {name: 'Confirm'})).toBeDisabled()
})

test('enables the submit button when there is no error', () => {
  render(<ExportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.getByRole('button', {name: 'Confirm'})).toBeEnabled()
})

test('notifies success with the destination email once the export succeeds', () => {
  useExport.mockReturnValue({
    ...baseHookReturn,
    email: 'someone@example.com',
    status: {successfull: true},
  })

  render(<ExportGlossary row={row} onClose={jest.fn()} />)

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Request submitted',
      text: 'You will receive the link at someone@example.com',
      type: 'success',
    }),
  )
})

test('does not notify when there is no status yet', () => {
  render(<ExportGlossary row={row} onClose={jest.fn()} />)

  expect(CatToolActions.addNotification).not.toHaveBeenCalled()
})
