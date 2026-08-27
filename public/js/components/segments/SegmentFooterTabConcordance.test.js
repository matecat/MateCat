import SegmentFooterTabConcordance from './SegmentFooterTabConcordance'

describe('SegmentFooterTabConcordance.prototype.copyText', () => {
  afterEach(() => {
    jest.restoreAllMocks()
  })

  test('does not reject when the browser denies clipboard permission', async () => {
    jest
      .spyOn(document, 'getSelection')
      .mockReturnValue({toString: () => 'some concordance text'})
    navigator.clipboard = {
      writeText: jest
        .fn()
        .mockRejectedValue(new DOMException('denied', 'NotAllowedError')),
    }

    await expect(
      SegmentFooterTabConcordance.prototype.copyText({
        preventDefault: jest.fn(),
      }),
    ).resolves.not.toThrow()

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      'some concordance text',
    )
  })
})
