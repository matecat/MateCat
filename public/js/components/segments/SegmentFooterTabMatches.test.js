import SegmentFooterTabMatches from './SegmentFooterTabMatches'

describe('SegmentFooterTabMatches.prototype.copyText', () => {
  afterEach(() => {
    jest.restoreAllMocks()
  })

  test('does not reject when the browser denies clipboard permission', async () => {
    jest
      .spyOn(document, 'getSelection')
      .mockReturnValue({toString: () => 'some matched text'})
    navigator.clipboard = {
      writeText: jest
        .fn()
        .mockRejectedValue(new DOMException('denied', 'NotAllowedError')),
    }

    await expect(
      SegmentFooterTabMatches.prototype.copyText({preventDefault: jest.fn()}),
    ).resolves.not.toThrow()

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      'some matched text',
    )
  })
})
