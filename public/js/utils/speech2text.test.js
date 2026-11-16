jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegment: jest.fn(),
  getCurrentSegmentId: jest.fn(),
}))
jest.mock('../actions/segmentDispatchActions', () => ({
  replaceEditAreaTextContent: jest.fn(),
  modifiedTranslation: jest.fn(),
  activateTab: jest.fn(),
}))
jest.mock('../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
}))
jest.mock('../stores/UserStore', () => ({
  getUserMetadata: jest.fn(),
}))

import Speech2Text from './speech2text'
import SegmentStore from '../stores/SegmentStore'
import {activateTab} from '../actions/segmentDispatchActions'

const buildMicrophone = () => ({
  hasClass: jest.fn(() => false),
  addClass: jest.fn(),
  removeClass: jest.fn(),
})

const buildSegment = (overrides = {}) => ({
  sid: 1,
  translation: 'hello',
  status: 'DRAFT',
  autopropagated_from: 0,
  suggestion_match: null,
  ...overrides,
})

beforeEach(() => {
  jest.clearAllMocks()
  Speech2Text.recognizing = false
  Speech2Text.isStopingRecognition = false
  Speech2Text.finalTranscript = ''
  Speech2Text.microphone = null
  Speech2Text.recognition = null
})

test('startSpeechRecognition swallows InvalidStateError from recognition.start() and still shows matches', () => {
  const microphone = buildMicrophone()
  Speech2Text.microphone = microphone
  const invalidStateError = Object.assign(new Error('already started'), {
    name: 'InvalidStateError',
  })
  Speech2Text.recognition = {
    start: jest.fn(() => {
      throw invalidStateError
    }),
  }
  SegmentStore.getCurrentSegment.mockReturnValue(buildSegment())
  SegmentStore.getCurrentSegmentId.mockReturnValue(1)

  expect(() => Speech2Text.startSpeechRecognition(microphone)).not.toThrow()

  expect(Speech2Text.recognition.start).toHaveBeenCalled()
  expect(activateTab).toHaveBeenCalledWith(1, 'matches')
})

test('startSpeechRecognition rethrows errors other than InvalidStateError from recognition.start()', () => {
  const microphone = buildMicrophone()
  Speech2Text.microphone = microphone
  const genericError = new Error('boom')
  Speech2Text.recognition = {
    start: jest.fn(() => {
      throw genericError
    }),
  }
  SegmentStore.getCurrentSegment.mockReturnValue(buildSegment())
  SegmentStore.getCurrentSegmentId.mockReturnValue(1)

  expect(() => Speech2Text.startSpeechRecognition(microphone)).toThrow(
    genericError,
  )

  expect(activateTab).not.toHaveBeenCalled()
})
