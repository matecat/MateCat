jest.mock('../../../../../api/downloadTMX')
jest.mock('../../../../../api/downloadGlossary')
jest.mock('../../../../../actions/CatToolActions')

import {renderHook, act} from '@testing-library/react'
import {downloadTMX} from '../../../../../api/downloadTMX'
import {downloadGlossary} from '../../../../../api/downloadGlossary'
import CatToolActions from '../../../../../actions/CatToolActions'
import useExport, {EXPORT_TYPE} from './useExport'

const row = {key: 'the-key', name: 'the-name'}

beforeEach(() => {
  jest.clearAllMocks()
  jest.useFakeTimers()
  global.config = {...config, userMail: 'jest-user@example.com'}
})

afterEach(() => {
  jest.useRealTimers()
})

test('initializes email from config.userMail and no status', () => {
  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  expect(result.current.email).toBe('jest-user@example.com')
  expect(result.current.status).toBeUndefined()
})

test('onChange updates email and resets status', () => {
  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  act(() =>
    result.current.onChange({currentTarget: {value: 'other@example.com'}}),
  )

  expect(result.current.email).toBe('other@example.com')
  expect(result.current.status).toBeUndefined()
})

test('onChange ignores an empty value', () => {
  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChange({currentTarget: {value: ''}}))

  expect(result.current.email).toBe('jest-user@example.com')
})

test('onSubmit calls downloadTMX for tmx type and closes after success', async () => {
  downloadTMX.mockResolvedValue()
  const onClose = jest.fn()
  const preventDefault = jest.fn()

  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose}),
  )

  await act(async () => {
    result.current.onSubmit({preventDefault}, true)
    await Promise.resolve()
  })

  expect(downloadTMX).toHaveBeenCalledWith({
    key: row.key,
    name: row.name,
    stripTags: true,
  })
  expect(downloadGlossary).not.toHaveBeenCalled()
  expect(preventDefault).toHaveBeenCalled()
  expect(result.current.status).toEqual({successfull: true})

  act(() => jest.advanceTimersByTime(2000))
  expect(onClose).toHaveBeenCalledTimes(1)
})

test('onSubmit calls downloadGlossary for glossary type', async () => {
  downloadGlossary.mockResolvedValue()

  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.glossary, row, onClose: jest.fn()}),
  )

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await Promise.resolve()
  })

  expect(downloadGlossary).toHaveBeenCalledWith({
    key: row.key,
    name: row.name,
    stripTags: undefined,
  })
  expect(result.current.status).toEqual({successfull: true})
})

test('onSubmit sets error status and notifies on failure with error payload', async () => {
  downloadTMX.mockRejectedValue([{message: 'boom'}])

  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await Promise.resolve()
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error export', text: 'boom'}),
  )
  expect(result.current.status).toEqual({errors: {message: 'boom'}})
})

test('onSubmit falls back to a generic error message when errors payload is empty', async () => {
  downloadTMX.mockRejectedValue([])

  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await Promise.resolve()
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      text: 'We got an error, please contact support',
    }),
  )
})

test('onReset restores the default email, clears status and calls onClose', () => {
  const onClose = jest.fn()
  const {result} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose}),
  )

  act(() =>
    result.current.onChange({currentTarget: {value: 'changed@example.com'}}),
  )
  act(() => result.current.onReset())

  expect(result.current.email).toBe('jest-user@example.com')
  expect(result.current.status).toBeUndefined()
  expect(onClose).toHaveBeenCalledTimes(1)
})

test('clears the pending close timeout on unmount', async () => {
  downloadTMX.mockResolvedValue()
  const onClose = jest.fn()

  const {result, unmount} = renderHook(() =>
    useExport({type: EXPORT_TYPE.tmx, row, onClose}),
  )

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await Promise.resolve()
  })

  unmount()
  act(() => jest.advanceTimersByTime(2000))

  expect(onClose).not.toHaveBeenCalled()
})
