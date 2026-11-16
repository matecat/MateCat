import React from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {
  createDownloadProgressStore,
  DownloadProgress,
  formatBytes,
} from './DownloadProgress'

test('says the files are being prepared until the first byte arrives', () => {
  const store = createDownloadProgressStore()
  render(<DownloadProgress store={store} onCancel={jest.fn()} />)

  expect(screen.getByText('Preparing your files')).toBeInTheDocument()
  expect(screen.getByRole('progressbar')).not.toHaveAttribute('aria-valuenow')
})

test('shows how much of the file has arrived once it is being received', () => {
  const store = createDownloadProgressStore()
  render(<DownloadProgress store={store} onCancel={jest.fn()} />)

  act(() =>
    store.update({
      receiving: true,
      loaded: 5 * 1024 * 1024,
      total: 20 * 1024 * 1024,
    }),
  )

  expect(screen.getByText('Downloading 5.0 MB of 20.0 MB')).toBeInTheDocument()
  expect(screen.getByRole('progressbar')).toHaveValue(25)
  expect(screen.queryByText(/Keep this page open/)).not.toBeInTheDocument()
})

test('shows only the received size when the total is unknown', () => {
  const store = createDownloadProgressStore()
  render(<DownloadProgress store={store} onCancel={jest.fn()} />)

  act(() => store.update({receiving: true, loaded: 3 * 1024 * 1024, total: 0}))

  expect(screen.getByText('Downloading 3.0 MB')).toBeInTheDocument()
  expect(screen.getByRole('progressbar')).not.toHaveAttribute('aria-valuenow')
})

test('cancels the download', async () => {
  const onCancel = jest.fn()
  render(
    <DownloadProgress
      store={createDownloadProgressStore()}
      onCancel={onCancel}
    />,
  )

  await userEvent.click(screen.getByRole('button', {name: 'Cancel download'}))

  expect(onCancel).toHaveBeenCalled()
})

test('formats sizes', () => {
  expect(formatBytes(100)).toBe('1 KB')
  expect(formatBytes(1536 * 1024)).toBe('1.5 MB')
  expect(formatBytes(3 * 1024 * 1024 * 1024)).toBe('3.00 GB')
})
