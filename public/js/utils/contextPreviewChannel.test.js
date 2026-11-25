import ContextPreviewChannel from './contextPreviewChannel'

beforeEach(() => {
  global.config = {...global.config, password: 'pw'}
  ContextPreviewChannel.close()
})

test('open creates a BroadcastChannel scoped to the current password', () => {
  ContextPreviewChannel.open()

  expect(ContextPreviewChannel._channel).toBeInstanceOf(BroadcastChannel)
  expect(ContextPreviewChannel._channel.channel).toBe(
    'matecat-context-preview-pw',
  )
})

test('open is a no-op when the channel is already open', () => {
  ContextPreviewChannel.open()
  const channel = ContextPreviewChannel._channel

  ContextPreviewChannel.open()

  expect(ContextPreviewChannel._channel).toBe(channel)
})

test('sendMessage opens the channel automatically and posts the message', () => {
  const postMessageSpy = jest.spyOn(BroadcastChannel.prototype, 'postMessage')

  ContextPreviewChannel.sendMessage({type: 'highlight', sid: 1})

  expect(ContextPreviewChannel._channel).not.toBeNull()
  expect(postMessageSpy).toHaveBeenCalledWith({type: 'highlight', sid: 1})

  ContextPreviewChannel.sendMessage({type: 'requestSegments'})
  expect(postMessageSpy).toHaveBeenCalledWith({type: 'requestSegments'})
})

test('onMessage opens the channel, registers the listener, dispatches messages, and unsubscribes', () => {
  const received = []
  const off = ContextPreviewChannel.onMessage((msg) => received.push(msg))

  expect(ContextPreviewChannel._channel).not.toBeNull()
  expect(ContextPreviewChannel._listeners.size).toBe(1)

  ContextPreviewChannel._channel.onmessage({
    data: {type: 'segmentClicked', sid: 5},
  })
  expect(received).toEqual([{type: 'segmentClicked', sid: 5}])

  off()
  expect(ContextPreviewChannel._listeners.size).toBe(0)
})

test('the internal message handler catches and logs listener errors', () => {
  const consoleErrorSpy = jest
    .spyOn(console, 'error')
    .mockImplementation(() => {})

  ContextPreviewChannel.onMessage(() => {
    throw new Error('boom')
  })

  expect(() =>
    ContextPreviewChannel._channel.onmessage({data: {}}),
  ).not.toThrow()
  expect(consoleErrorSpy).toHaveBeenCalledWith(
    '[ContextPreviewChannel] Listener error:',
    expect.any(Error),
  )

  consoleErrorSpy.mockRestore()
})

test('close closes the channel and clears listeners', () => {
  const closeSpy = jest.spyOn(BroadcastChannel.prototype, 'close')
  ContextPreviewChannel.onMessage(() => {})

  ContextPreviewChannel.close()

  expect(closeSpy).toHaveBeenCalled()
  expect(ContextPreviewChannel._channel).toBeNull()
  expect(ContextPreviewChannel._listeners.size).toBe(0)
})
