import CommonUtils from './commonUtils'

describe('CommonUtils.DetectTripleClick', () => {
  afterEach(() => {
    jest.restoreAllMocks()
  })

  const tripleClick = (target) => {
    for (let i = 0; i < 3; i++) {
      target.dispatchEvent(
        new MouseEvent('mousedown', {clientX: 10, bubbles: true}),
      )
    }
  }

  test('does not throw when the selection lands on a node without getBoundingClientRect (e.g. a browser-extension-injected node)', () => {
    const target = document.createElement('div')
    const callback = jest.fn()
    // eslint-disable-next-line no-new
    new CommonUtils.DetectTripleClick(target, callback)

    jest.spyOn(window, 'getSelection').mockReturnValue({
      focusNode: {parentNode: {}},
    })

    expect(() => tripleClick(target)).not.toThrow()
    expect(callback).not.toHaveBeenCalled()
  })

  test('calls the callback when the click lands within the selection bounds', () => {
    const target = document.createElement('div')
    const callback = jest.fn()
    // eslint-disable-next-line no-new
    new CommonUtils.DetectTripleClick(target, callback)

    jest.spyOn(window, 'getSelection').mockReturnValue({
      rangeCount: 1,
      getRangeAt: () => ({
        cloneRange: () => ({
          getBoundingClientRect: () => ({left: 0, right: 20}),
        }),
      }),
      focusNode: {
        parentNode: {
          getBoundingClientRect: () => ({x: 0, width: 20}),
        },
      },
    })

    tripleClick(target)

    expect(callback).toHaveBeenCalled()
  })
})
