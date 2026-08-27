jest.mock('../../../../../actions/ModalsActions')
jest.mock('../../../../../actions/CatToolActions')
jest.mock('../../../../../api/loadTMX')
jest.mock('../../../../../api/loadGlossaryFile')
jest.mock('../../../../../api/uploadGlossary/uploadGlossary')
jest.mock('../../../../../api/uploadTm')
jest.mock('../../../../../api/checkGlossaryImport')

import {renderHook, act} from '@testing-library/react'
import useImport, {IMPORT_TYPE} from './useImport'
import {loadTMX} from '../../../../../api/loadTMX'
import {loadGlossaryFile} from '../../../../../api/loadGlossaryFile'
import {uploadGlossary} from '../../../../../api/uploadGlossary/uploadGlossary'
import {uploadTm} from '../../../../../api/uploadTm'
import {checkGlossaryImport} from '../../../../../api/checkGlossaryImport'
import ModalsActions from '../../../../../actions/ModalsActions'
import CatToolActions from '../../../../../actions/CatToolActions'

const row = {key: 'the-key', name: 'the-name'}

const changeEvent = (files) => ({target: {files}})
const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

beforeEach(() => {
  jest.clearAllMocks()
  jest.useFakeTimers()
  global.config = {...config, maxTMXFileSize: 1000}
})

afterEach(() => {
  jest.useRealTimers()
})

test('onChangeFiles sets files when within the size limit', () => {
  const file = {name: 'file.tmx', size: 10}
  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  expect(result.current.files).toEqual([file])
})

test('onChangeFiles shows an alert and does not set files when a tmx file exceeds the max size', () => {
  const bigFile = {name: 'file.tmx', size: config.maxTMXFileSize + 1}
  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([bigFile])))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.anything(),
    expect.objectContaining({buttonText: 'OK'}),
    'File too big',
  )
  expect(result.current.files).toEqual([])
})

test('onChangeFiles ignores an empty file list', () => {
  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([])))

  expect(result.current.files).toEqual([])
  expect(result.current.status).toEqual([])
})

test('onSubmit for tmx: uploads the file, polls status and closes once completed', async () => {
  const onClose = jest.fn()
  const file = {name: 'file.tmx', size: 10}
  uploadTm.mockResolvedValue({data: {uuids: [{uuid: 'u1', name: 'file.tmx'}]}})
  loadTMX.mockResolvedValueOnce({
    data: {uuid: 'u1', status: 0, completed: 1, totals: 2},
  })
  loadTMX.mockResolvedValueOnce({
    data: {uuid: 'u1', status: 1, completed: 2, totals: 2},
  })

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  expect(uploadTm).toHaveBeenCalledWith({
    filesToUpload: [file],
    tmKey: row.key,
    keyName: row.name,
  })
  expect(loadTMX).toHaveBeenCalledTimes(1)
  expect(result.current.status[0]).toEqual(
    expect.objectContaining({uuid: 'u1', isCompleted: false, percentage: 50}),
  )

  await act(async () => {
    jest.advanceTimersByTime(1000)
    await flush()
  })

  expect(loadTMX).toHaveBeenCalledTimes(2)
  expect(result.current.status[0]).toEqual(
    expect.objectContaining({uuid: 'u1', isCompleted: true}),
  )

  await act(async () => {
    jest.advanceTimersByTime(2000)
  })

  expect(onClose).toHaveBeenCalledTimes(1)
})

test('getStatus notifies and stores errors when polling rejects', async () => {
  const file = {name: 'file.tmx', size: 10}
  uploadTm.mockResolvedValue({data: {uuids: [{uuid: 'u1', name: 'file.tmx'}]}})
  loadTMX.mockRejectedValue({errors: [{message: 'boom'}]})

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error import', text: 'boom'}),
  )
  expect(result.current.status[0].errors).toEqual([{message: 'boom'}])
})

test('onSubmit for glossary with <=10 languages uploads directly', async () => {
  const file = {name: 'file.xlsx', size: 10}
  checkGlossaryImport.mockResolvedValue({results: [{numberOfLanguages: 5}]})
  uploadGlossary.mockResolvedValue({data: {uuids: [{uuid: 'g1'}]}})
  loadGlossaryFile.mockResolvedValue({
    data: {uuid: 'g1', status: 1, completed: 1, totals: 1},
  })

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.glossary, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  expect(checkGlossaryImport).toHaveBeenCalledWith({
    filesToUpload: [file],
    tmKey: row.key,
    keyName: row.name,
  })
  expect(uploadGlossary).toHaveBeenCalledWith({
    filesToUpload: [file],
    tmKey: row.key,
    keyName: row.name,
  })
  expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
})

test('onSubmit for glossary with more than 10 languages asks for confirmation before uploading', async () => {
  const file = {name: 'file.xlsx', size: 10}
  checkGlossaryImport.mockResolvedValue({results: [{numberOfLanguages: 15}]})
  uploadGlossary.mockResolvedValue({data: {uuids: [{uuid: 'g1'}]}})

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.glossary, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  expect(uploadGlossary).not.toHaveBeenCalled()
  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.anything(),
    expect.objectContaining({successText: 'Upload termbase'}),
  )

  const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
  await act(async () => {
    successCallback()
    await flush()
  })

  expect(uploadGlossary).toHaveBeenCalled()
})

test('onSubmit for glossary shows an error notification when checkGlossaryImport rejects', async () => {
  const file = {name: 'file.xlsx', size: 10}
  checkGlossaryImport.mockRejectedValue({errors: [{message: 'bad file'}]})

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.glossary, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error upload', text: 'bad file'}),
  )
})

test('onSubmit for glossary shows a generic error notification when uploadGlossary rejects without a message', async () => {
  const file = {name: 'file.xlsx', size: 10}
  checkGlossaryImport.mockResolvedValue({results: [{numberOfLanguages: 2}]})
  uploadGlossary.mockRejectedValue({})

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.glossary, row, onClose: jest.fn()}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))

  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error upload', text: 'Error'}),
  )
})

test('onReset clears state and calls onClose when there is no pending upload', () => {
  const onClose = jest.fn()
  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose}),
  )

  act(() => result.current.onChangeFiles(changeEvent([{name: 'f', size: 1}])))
  act(() => result.current.onReset())

  expect(result.current.files).toEqual([])
  expect(result.current.status).toEqual([])
  expect(onClose).toHaveBeenCalledTimes(1)
})

test('onReset does not call onClose while an upload is still tracked', async () => {
  const onClose = jest.fn()
  const file = {name: 'file.tmx', size: 10}
  uploadTm.mockResolvedValue({data: {uuids: [{uuid: 'u1', name: 'file.tmx'}]}})
  loadTMX.mockResolvedValue({
    data: {uuid: 'u1', status: 0, completed: 1, totals: 4},
  })

  const {result} = renderHook(() =>
    useImport({type: IMPORT_TYPE.tmx, row, onClose}),
  )

  act(() => result.current.onChangeFiles(changeEvent([file])))
  await act(async () => {
    result.current.onSubmit({preventDefault: jest.fn()})
    await flush()
  })

  act(() => result.current.onReset())

  expect(onClose).not.toHaveBeenCalled()
})
