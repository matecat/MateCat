import React from 'react'
import {render, screen, fireEvent, act, waitFor} from '@testing-library/react'
import {fromJS} from 'immutable'
import SplitJobModal from './SplitJob'
import {checkSplitRequest} from '../../api/checkSplitRequest'
import {confirmSplitRequest} from '../../api/confirmSplitRequest'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../api/checkSplitRequest', () => ({
  checkSplitRequest: jest.fn(),
}))

jest.mock('../../api/confirmSplitRequest', () => ({
  confirmSplitRequest: jest.fn(),
}))

jest.mock('../../actions/ModalsActions', () => ({
  __esModule: true,
  default: {
    onCloseModal: jest.fn(),
  },
}))

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
    confirmSplitRequest.mockRejectedValue([{message: 'Split failed'}])

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
    // confirmSplitRequest rejects with the fetch Response itself on a non-ok status.
    confirmSplitRequest.mockRejectedValue({ok: false, status: 500})

    const confirm = await renderUntilConfirm()

    await act(async () => {
      fireEvent.click(confirm)
    })

    await waitFor(() => expect(confirm).toBeEnabled())
    expect(confirmSplitRequest).toHaveBeenCalledTimes(1)
  })
})
