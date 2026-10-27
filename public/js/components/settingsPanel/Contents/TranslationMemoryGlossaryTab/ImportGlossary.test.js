jest.mock('./hooks/useImport')

import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {ImportGlossary} from './ImportGlossary'
import useImport, {IMPORT_TYPE} from './hooks/useImport'

const row = {key: 'the-key', name: 'the-name'}

const baseHookReturn = {
  files: [],
  uuids: undefined,
  status: [],
  onSubmit: jest.fn(),
  onReset: jest.fn(),
  onChangeFiles: jest.fn(),
}

beforeEach(() => {
  jest.clearAllMocks()
  useImport.mockReturnValue(baseHookReturn)
})

test('uses the glossary import type and passes row/onClose through to the hook', () => {
  const onClose = jest.fn()
  render(<ImportGlossary row={row} onClose={onClose} />)

  expect(useImport).toHaveBeenCalledWith({
    type: IMPORT_TYPE.glossary,
    row,
    onClose,
  })
})

test('hides the Confirm button until a file has been selected', () => {
  render(<ImportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.queryByText('Confirm')).not.toBeInTheDocument()
})

test('shows the Confirm button once a file is selected', () => {
  useImport.mockReturnValue({...baseHookReturn, files: [{name: 'a.xlsx'}]})

  render(<ImportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.getByText('Confirm')).toBeInTheDocument()
})

test('selecting a file forwards the change event to the hook', () => {
  const onChangeFiles = jest.fn()
  useImport.mockReturnValue({...baseHookReturn, onChangeFiles})

  render(<ImportGlossary row={row} onClose={jest.fn()} />)
  // Only the reset button is rendered before a file is selected, so it's the
  // one accessible element whose owning <form> also contains the file input.
  const form = screen.getAllByRole('button')[0].form
  const input = form.elements['uploaded_file[]']

  fireEvent.change(input, {target: {files: [{name: 'a.xlsx'}]}})

  expect(onChangeFiles).toHaveBeenCalledTimes(1)
})

test('submitting the form with a selected file calls the hook-provided onSubmit', () => {
  const onSubmit = jest.fn((e) => e.preventDefault())
  useImport.mockReturnValue({
    ...baseHookReturn,
    files: [{name: 'a.xlsx'}],
    onSubmit,
  })

  render(<ImportGlossary row={row} onClose={jest.fn()} />)
  fireEvent.submit(screen.getByRole('button', {name: 'Confirm'}).form)

  expect(onSubmit).toHaveBeenCalledTimes(1)
})

test('resetting the form calls the hook-provided onReset', () => {
  const onReset = jest.fn()
  useImport.mockReturnValue({...baseHookReturn, onReset})

  render(<ImportGlossary row={row} onClose={jest.fn()} />)
  fireEvent.reset(screen.getAllByRole('button')[0].form)

  expect(onReset).toHaveBeenCalledTimes(1)
})

test('shows an in-progress upload without marking it completed or errored', () => {
  useImport.mockReturnValue({
    ...baseHookReturn,
    uuids: [{uuid: '1', filename: 'a.xlsx'}],
    status: [{uuid: '1', isCompleted: false, percentage: 42}],
  })

  render(<ImportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.getByText('a.xlsx')).toBeInTheDocument()
  expect(screen.queryByText('Import completed')).not.toBeInTheDocument()
  expect(screen.getByText('a.xlsx')).not.toHaveClass('filename-error')
})

test('shows a completed message once the upload finishes', () => {
  useImport.mockReturnValue({
    ...baseHookReturn,
    uuids: [{uuid: '1', filename: 'a.xlsx'}],
    status: [{uuid: '1', isCompleted: true, percentage: 100}],
  })

  render(<ImportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.getByText('Import completed')).toBeInTheDocument()
})

test('shows the error message and lets the user dismiss it when a file fails', () => {
  useImport.mockReturnValue({
    ...baseHookReturn,
    uuids: [{uuid: '1', filename: 'a.xlsx'}],
    status: [{uuid: '1', errors: [{message: 'Invalid file format'}]}],
  })

  render(<ImportGlossary row={row} onClose={jest.fn()} />)

  expect(screen.getByText('Invalid file format')).toBeInTheDocument()
  expect(screen.getByText('a.xlsx')).toHaveClass('filename-error')

  // the dismiss button calls formRef.current.reset(), a native DOM API —
  // just confirm clicking it doesn't throw. No Confirm button is rendered
  // (files is empty in this test), so the reset button is first and the
  // per-row dismiss button is second, in document order.
  expect(() =>
    fireEvent.click(screen.getAllByRole('button')[1]),
  ).not.toThrow()
})
