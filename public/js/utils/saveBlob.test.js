import {saveBlob} from './saveBlob'

beforeEach(() => {
  jest.useFakeTimers()
  URL.createObjectURL = jest.fn(() => 'blob:https://dev.matecat.com/1')
  URL.revokeObjectURL = jest.fn()
})

afterEach(() => {
  jest.useRealTimers()
})

test('saves the blob with the given filename without opening a window', () => {
  const open = jest.spyOn(window, 'open').mockImplementation(() => null)
  let clicked
  jest
    .spyOn(HTMLAnchorElement.prototype, 'click')
    .mockImplementation(function () {
      clicked = {
        href: this.href,
        download: this.download,
        target: this.target,
      }
    })

  saveBlob(new Blob(['x']), 'job_12.zip')

  expect(clicked).toEqual({
    href: 'blob:https://dev.matecat.com/1',
    download: 'job_12.zip',
    target: '',
  })
  expect(open).not.toHaveBeenCalled()
  expect(document.querySelector('a')).toBeNull()
})

test('keeps the blob URL alive while the browser starts writing the file', () => {
  jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

  saveBlob(new Blob(['x']), 'job_12.zip')

  expect(URL.revokeObjectURL).not.toHaveBeenCalled()
  jest.advanceTimersByTime(60 * 1000)
  expect(URL.revokeObjectURL).toHaveBeenCalledWith(
    'blob:https://dev.matecat.com/1',
  )
})
