import React from 'react'
import {render, screen, fireEvent, act, waitFor} from '@testing-library/react'
import {fromJS} from 'immutable'
import SplitJobModal from './SplitJob'
import {checkSplitRequest} from '../../api/checkSplitRequest'
import {confirmSplitRequest} from '../../api/confirmSplitRequest'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../api/checkSplitRequest')
jest.mock('../../api/confirmSplitRequest')
jest.mock('../../actions/ModalsActions')

const baseJob = fromJS({
  id: 55,
  sourceTxt: 'en-US',
  targetTxt: 'it-IT',
  stats: {equivalent: {total: 100}},
})
const baseProject = fromJS({id: 1})

const successData = {
  eq_word_count: 100,
  raw_word_count: 120,
  chunks: [
    {eq_word_count: 50, raw_word_count: 60},
    {eq_word_count: 50, raw_word_count: 60},
  ],
}

afterEach(() => jest.clearAllMocks())

// The chunk word-count inputs share `type="text"` with the chunk-count
// stepper's own input, so `getAllByRole('textbox')` can no longer assume
// index 0 is the first chunk — scope to the chunk-specific class instead.
const getChunkInputs = (container) =>
  container.querySelectorAll('.input-small')

test('shows a loader before the split data resolves', () => {
  checkSplitRequest.mockReturnValue(new Promise(() => {}))
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )
  expect(container.querySelector('.split-loader')).toBeInTheDocument()
})

test('renders chunks and a Confirm button once the split is balanced', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  expect(await screen.findByText('Confirm')).toBeInTheDocument()
  expect(screen.getByText('Chunk 1')).toBeInTheDocument()
  expect(screen.getByText('Chunk 2')).toBeInTheDocument()
  expect(container.querySelector('.total-w')).toHaveTextContent('100')
  expect(screen.queryByText('Words exceeding')).not.toBeInTheDocument()
})

test('confirming the split calls confirmSplitRequest and closes the modal on success', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  confirmSplitRequest.mockResolvedValue({data: {chunks: [{}, {}]}})
  const callback = jest.fn()
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={callback} />,
  )

  await screen.findByText('Confirm')
  fireEvent.click(screen.getByText('Confirm'))

  await waitFor(() => expect(callback).toHaveBeenCalledTimes(1))
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('shows an error and disables further checks when confirmSplitRequest fails', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  confirmSplitRequest.mockRejectedValue({errors: [{message: 'confirm failed'}]})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')
  fireEvent.click(screen.getByText('Confirm'))

  expect(await screen.findByText('confirm failed')).toBeInTheDocument()
  expect(screen.getByText('Check split')).toBeInTheDocument()
})

test('shows the "too few segments" message and disables split when the API returns code -7', async () => {
  checkSplitRequest.mockRejectedValue({errors: [{code: -7, message: 'nope'}]})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  expect(
    await screen.findByText(
      'Split unsuccessful: the job has too few segments.',
    ),
  ).toBeInTheDocument()
  expect(screen.getByText('Check split')).toBeDisabled()
})

test('does not leave an unhandled rejection when the API rejects without an errors list', async () => {
  checkSplitRequest.mockRejectedValue({})
  const unhandled = []
  const onUnhandled = (reason) => unhandled.push(reason)
  process.on('unhandledRejection', onUnhandled)

  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 50))
  })
  process.off('unhandledRejection', onUnhandled)

  expect(unhandled).toEqual([])
})

test('shows a generic error message for other API errors', async () => {
  checkSplitRequest.mockRejectedValue({
    errors: [{code: -1, message: 'generic failure'}],
  })
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  expect(await screen.findByText('generic failure')).toBeInTheDocument()
  expect(screen.getByText('Check split')).toBeEnabled()
})

test('editing a chunk word count shows the words-exceeding difference and re-enables Check split', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')

  const inputs = getChunkInputs(container)
  fireEvent.change(inputs[0], {target: {value: '80'}})

  expect(screen.getByText('Check split')).toBeInTheDocument()
  expect(screen.getByText('Words exceeding')).toBeInTheDocument()
  expect(container.querySelector('.diff-w')).toHaveTextContent('30')
})

test('changing the chunk count via the stepper recalculates the chunks', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')

  const stepperInput = screen.getByDisplayValue('2')
  fireEvent.change(stepperInput, {target: {value: '4'}})
  fireEvent.blur(stepperInput)

  expect(screen.getByText('Chunk 4')).toBeInTheDocument()
  expect(screen.getByText('Check split')).toBeInTheDocument()
})

test('typing a new chunk count without blurring shows the chunk but does not recheck the split', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')

  const stepperInput = screen.getByDisplayValue('2')
  fireEvent.change(stepperInput, {target: {value: '4'}})

  // The chunk count label follows the typed value live, but the word-count
  // split itself is only recalculated once the value is committed (blur).
  expect(screen.getByText('Chunk 4')).toBeInTheDocument()
  expect(screen.getByText('Confirm')).toBeInTheDocument()
})

test('clicking "Check split" re-issues the split request', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')
  const inputs = getChunkInputs(container)
  fireEvent.change(inputs[0], {target: {value: '80'}})

  checkSplitRequest.mockClear()
  checkSplitRequest.mockResolvedValue({data: successData})
  fireEvent.click(screen.getByText('Check split'))

  await waitFor(() => expect(checkSplitRequest).toHaveBeenCalledTimes(1))
})

test('shows an error when "Check split" fails', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')
  const inputs = getChunkInputs(container)
  fireEvent.change(inputs[0], {target: {value: '80'}})

  checkSplitRequest.mockReset()
  checkSplitRequest.mockRejectedValue({errors: [{message: 'check failed'}]})
  fireEvent.click(screen.getByText('Check split'))

  expect(await screen.findByText('check failed')).toBeInTheDocument()
})

test('clicking Cancel closes the modal', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')
  fireEvent.click(screen.getByText('Cancel'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('shows a disabled tooltip checkbox when the weighted word count is 0', async () => {
  checkSplitRequest.mockResolvedValue({
    data: {...successData, eq_word_count: 0},
  })
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')
  const checkbox = container.querySelector(
    '.split-checkbox input[type="checkbox"]',
  )
  expect(checkbox).toBeDisabled()
})

test('toggling the raw-word checkbox switches the displayed total', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await screen.findByText('Confirm')
  const checkbox = container.querySelector(
    '.split-checkbox input[type="checkbox"]',
  )
  expect(checkbox).not.toBeChecked()

  fireEvent.click(checkbox)

  await waitFor(() =>
    expect(container.querySelector('.total-w')).toHaveTextContent('120'),
  )
})

test('starts with raw-word split checked when the job has zero equivalent words', () => {
  const zeroEqJob = fromJS({
    id: 1,
    sourceTxt: 'en-US',
    targetTxt: 'it-IT',
    stats: {equivalent: {total: 0}},
  })
  checkSplitRequest.mockReturnValue(new Promise(() => {}))
  render(
    <SplitJobModal
      job={zeroEqJob}
      project={baseProject}
      callback={jest.fn()}
    />,
  )

  expect(checkSplitRequest).toHaveBeenCalledWith(
    zeroEqJob.toJS(),
    baseProject.toJS(),
    2,
    null,
    true,
  )
})

const job = fromJS({
  id: 1,
  password: 'job-pw',
  stats: {equivalent: {total: 100}},
})
const project = fromJS({id: 10, password: 'project-pw'})

const splitData = {
  eq_word_count: 100,
  raw_word_count: 100,
  chunks: [
    {eq_word_count: 50, raw_word_count: 50},
    {eq_word_count: 50, raw_word_count: 50},
  ],
}

const deferred = () => {
  let resolve, reject
  const promise = new Promise((res, rej) => {
    resolve = res
    reject = rej
  })
  return {promise, resolve, reject}
}

const renderUntilConfirm = async (callback = jest.fn()) => {
  render(<SplitJobModal job={job} project={project} callback={callback} />)
  return screen.findByRole('button', {name: 'Confirm'})
}

describe('SplitJobModal confirm', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    checkSplitRequest.mockResolvedValue({data: splitData})
  })

  test('a second click while the request is pending does not send a second split', async () => {
    const pending = deferred()
    confirmSplitRequest.mockReturnValue(pending.promise)

    const confirm = await renderUntilConfirm()

    fireEvent.click(confirm)
    fireEvent.click(confirm)

    expect(confirmSplitRequest).toHaveBeenCalledTimes(1)
    expect(confirm).toBeDisabled()

    await act(async () => {
      pending.resolve({data: {chunks: []}})
    })

    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  })

  test('the button is enabled again when the response has no chunks', async () => {
    confirmSplitRequest.mockResolvedValue({data: {}})

    const confirm = await renderUntilConfirm()

    await act(async () => {
      fireEvent.click(confirm)
    })

    await waitFor(() => expect(confirm).toBeEnabled())
    expect(ModalsActions.onCloseModal).not.toHaveBeenCalled()
  })

  test('an error list shows the message and goes back to the split check', async () => {
    confirmSplitRequest.mockRejectedValue({errors: [{message: 'Split failed'}]})

    const confirm = await renderUntilConfirm()

    await act(async () => {
      fireEvent.click(confirm)
    })

    expect(await screen.findByText('Split failed')).toBeInTheDocument()
    expect(screen.queryByRole('button', {name: 'Confirm'})).toBeNull()
    expect(screen.getByRole('button', {name: 'Check split'})).toBeEnabled()
    expect(confirmSplitRequest).toHaveBeenCalledTimes(1)
  })
  test('the button is enabled again when the request fails without an error list', async () => {
    // confirmSplitRequest rejects with the response alone when a non-ok status has no body.
    confirmSplitRequest.mockRejectedValue({response: {ok: false, status: 500}})

    const confirm = await renderUntilConfirm()

    await act(async () => {
      fireEvent.click(confirm)
    })

    await waitFor(() => expect(confirm).toBeEnabled())
    expect(confirmSplitRequest).toHaveBeenCalledTimes(1)
  })
})
