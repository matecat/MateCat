import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
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

  await waitFor(() => expect(screen.getByText('Confirm')).toBeInTheDocument())
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

  await waitFor(() => screen.getByText('Confirm'))
  fireEvent.click(screen.getByText('Confirm'))

  await waitFor(() => expect(callback).toHaveBeenCalledTimes(1))
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('shows an error and disables further checks when confirmSplitRequest fails', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  confirmSplitRequest.mockRejectedValue([{message: 'confirm failed'}])
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() => screen.getByText('Confirm'))
  fireEvent.click(screen.getByText('Confirm'))

  await waitFor(() =>
    expect(screen.getByText('confirm failed')).toBeInTheDocument(),
  )
  expect(screen.getByText('Check split')).toBeInTheDocument()
})

test('shows the "too few segments" message and disables split when the API returns code -7', async () => {
  checkSplitRequest.mockRejectedValue({errors: [{code: -7, message: 'nope'}]})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() =>
    expect(
      screen.getByText('Split unsuccessful: the job has too few segments.'),
    ).toBeInTheDocument(),
  )
  expect(screen.getByText('Check split')).toBeDisabled()
})

test('shows a generic error message for other API errors', async () => {
  checkSplitRequest.mockRejectedValue({
    errors: [{code: -1, message: 'generic failure'}],
  })
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() =>
    expect(screen.getByText('generic failure')).toBeInTheDocument(),
  )
  expect(screen.getByText('Check split')).not.toBeDisabled()
})

test('editing a chunk word count shows the words-exceeding difference and re-enables Check split', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() => screen.getByText('Confirm'))

  const inputs = screen.getAllByRole('textbox')
  fireEvent.change(inputs[0], {target: {value: '80'}})

  expect(screen.getByText('Check split')).toBeInTheDocument()
  expect(screen.getByText('Words exceeding')).toBeInTheDocument()
  expect(container.querySelector('.diff-w')).toHaveTextContent('30')
})

test('changing the split-number select recalculates the chunk count', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  const {container} = render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() => screen.getByText('Confirm'))

  const select = container.querySelector('select.splitselect')
  fireEvent.change(select, {target: {value: '4'}})

  expect(screen.getByText('Chunk 4')).toBeInTheDocument()
  expect(screen.getByText('Check split')).toBeInTheDocument()
})

test('clicking "Check split" re-issues the split request', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() => screen.getByText('Confirm'))
  const inputs = screen.getAllByRole('textbox')
  fireEvent.change(inputs[0], {target: {value: '80'}})

  checkSplitRequest.mockClear()
  checkSplitRequest.mockResolvedValue({data: successData})
  fireEvent.click(screen.getByText('Check split'))

  await waitFor(() => expect(checkSplitRequest).toHaveBeenCalledTimes(1))
})

test('shows an error when "Check split" fails', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() => screen.getByText('Confirm'))
  const inputs = screen.getAllByRole('textbox')
  fireEvent.change(inputs[0], {target: {value: '80'}})

  checkSplitRequest.mockReset()
  checkSplitRequest.mockRejectedValue({errors: [{message: 'check failed'}]})
  fireEvent.click(screen.getByText('Check split'))

  await waitFor(() =>
    expect(screen.getByText('check failed')).toBeInTheDocument(),
  )
})

test('clicking Cancel closes the modal', async () => {
  checkSplitRequest.mockResolvedValue({data: successData})
  render(
    <SplitJobModal job={baseJob} project={baseProject} callback={jest.fn()} />,
  )

  await waitFor(() => screen.getByText('Confirm'))
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

  await waitFor(() => screen.getByText('Confirm'))
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

  await waitFor(() => screen.getByText('Confirm'))
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
