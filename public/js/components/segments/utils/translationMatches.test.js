jest.mock('lodash', () => ({
  isUndefined: (value) => typeof value === 'undefined',
}))

jest.mock('jquery', () => ({
  trim: (value) => String(value).trim(),
}))

jest.mock('../../../utils/segmentUtils', () => ({
  checkCurrentSegmentTPEnabled: jest.fn(() => false),
  getSegmentContext: jest.fn(() => ({
    contextListBefore: [],
    contextListAfter: [],
  })),
}))

jest.mock('../../../utils/commonUtils', () => ({
  dispatchCustomEvent: jest.fn(),
  levenshteinDistance: jest.fn(() => 100),
}))

jest.mock('../../../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
}))

jest.mock('../../../utils/speech2text', () => ({
  enabled: jest.fn(() => false),
  isContributionToBeAllowed: jest.fn(() => true),
}))

jest.mock('./DraftMatecatUtils', () => ({
  removeTagsFromText: jest.fn((text) => text),
}))

jest.mock('../../../actions/segmentClassActions', () => ({
  addClassToSegment: jest.fn(),
}))

jest.mock('../../../actions/segmentDispatchActions', () => ({
  replaceEditAreaTextContent: jest.fn(),
  setHeaderPercentage: jest.fn(),
  modifiedTranslation: jest.fn(),
  setSegmentContributions: jest.fn(),
  setChoosenSuggestion: jest.fn(),
}))

jest.mock('../../../actions/segmentQaActions', () => ({
  getSegmentsQa: jest.fn(),
  startSegmentQACheck: jest.fn(),
}))

jest.mock('../../../actions/tagProjectionActions', () => ({
  disableTPOnSegment: jest.fn(),
}))

jest.mock('../../../stores/SegmentStore', () => ({
  getSegmentByIdToJS: jest.fn(),
  getNextSegment: jest.fn(),
  lastTranslatedSegmentId: null,
}))

jest.mock('../../../api/getContributions', () => ({
  getContributions: jest.fn(() => Promise.resolve()),
}))

jest.mock('../../../api/deleteContribution', () => ({
  deleteContribution: jest.fn(() => Promise.resolve()),
}))

jest.mock('../../../constants/Constants', () => ({
  SEGMENTS_STATUS: {
    UNTRANSLATED: 'NEW',
  },
}))

jest.mock('../../../actions/CatToolActions', () => ({
  processErrors: jest.fn(),
}))

jest.mock('../../../api/laraAuth', () => ({
  laraAuthJob: jest.fn(() => Promise.resolve({token: 'token-1'})),
}))

jest.mock('../../../api/laraTranslate', () => ({
  laraTranslate: jest.fn(() =>
    Promise.resolve({
      translation: [{translatable: true, text: 'translated text'}],
    }),
  ),
}))

jest.mock('../../../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(() => ({
    project: {
      mt_extra: {},
    },
  })),
}))

jest.mock('./DraftMatecatUtils/tagUtils', () => ({
  decodeTagsToUnicodeChar: jest.fn((text) => text),
  encodeTagsFromUnicodeChar: jest.fn((text) => text),
}))

import TranslationMatches from './translationMatches'
import SegmentStore from '../../../stores/SegmentStore'
import SegmentUtils from '../../../utils/segmentUtils'
import CatToolStore from '../../../stores/CatToolStore'
import {getContributions} from '../../../api/getContributions'
import {laraAuthJob} from '../../../api/laraAuth'
import {laraTranslate} from '../../../api/laraTranslate'

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
  await Promise.resolve()
}

describe('translationMatches', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    TranslationMatches.segmentsWaitingForContributions = []

    global.config = {
      translation_matches_enabled: true,
      active_engine: {engine_type: 'Lara'},
      source_code: 'en-US',
      target_code: 'it-IT',
      id_job: 12,
      password: 'pw',
      id_client: 99,
    }

    SegmentUtils.getSegmentContext.mockReturnValue({
      contextListBefore: ['ctx before'],
      contextListAfter: ['ctx after'],
    })

    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => {
      if (sid === 1) {
        return {
          sid: 1,
          original_sid: 101,
          segment: 'source text',
          contributions: {matches: []},
          status: 'NEW',
          translation: '',
          opened: true,
        }
      }
      return undefined
    })
  })

  test('calls Lara translate with style guide and requests contributions with prosa model', async () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {
        mt_extra: {
          lara_style_guideline_id: 'style-1',
          lara_glossaries: ['gl-1'],
        },
      },
    })

    TranslationMatches.getContribution({
      sid: 1,
      crossLanguageSettings: {primary: 'en', secondary: 'it'},
      force: false,
      fastFetch: false,
    })

    await flushPromises()

    expect(laraAuthJob).toHaveBeenCalledWith({
      idJob: 12,
      password: 'pw',
      reasoning: false,
    })
    expect(laraTranslate).toHaveBeenCalledWith(
      expect.objectContaining({
        token: 'token-1',
        source: 'source text',
        sid: 101,
        jobId: 12,
        styleguideId: 'style-1',
        glossaries: ['gl-1'],
      }),
    )

    expect(getContributions).toHaveBeenCalledWith(
      expect.objectContaining({
        idSegment: 101,
        translation: 'translated text',
        laraModel: 'prosa',
      }),
    )

    expect(TranslationMatches.segmentsWaitingForContributions).toEqual([])
  })

  test('falls back to classic contributions when Lara auth fails', async () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {
        mt_extra: {
          lara_style_guideline_id: 'style-2',
        },
      },
    })

    laraAuthJob.mockRejectedValueOnce(new Error('auth failed'))

    TranslationMatches.getContribution({
      sid: 1,
      crossLanguageSettings: null,
      force: false,
      fastFetch: false,
    })

    await flushPromises()

    expect(laraTranslate).not.toHaveBeenCalled()
    expect(getContributions).toHaveBeenCalledWith(
      expect.objectContaining({
        idSegment: 101,
        translation: null,
      }),
    )
    expect(getContributions.mock.calls[0][0]).not.toHaveProperty('laraModel')
  })

  test('prefetch requests current and next segments with fastFetch only on first request', () => {
    SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => ({sid}))

    SegmentStore.getNextSegment
      .mockReturnValueOnce({sid: 2})
      .mockReturnValueOnce({sid: 3})
      .mockReturnValueOnce({sid: 4})

    const getContributionSpy = jest
      .spyOn(TranslationMatches, 'getContribution')
      .mockImplementation(() => Promise.resolve())

    TranslationMatches.getContributionsWithPrefetch({
      sid: 1,
      crossLanguageSettings: null,
      force: true,
      prefetch: 3,
    })

    expect(getContributionSpy).toHaveBeenCalledTimes(4)
    expect(getContributionSpy).toHaveBeenNthCalledWith(
      1,
      expect.objectContaining({sid: 1, fastFetch: true}),
    )
    expect(getContributionSpy).toHaveBeenNthCalledWith(
      2,
      expect.objectContaining({sid: 2, fastFetch: false}),
    )
    expect(getContributionSpy).toHaveBeenNthCalledWith(
      3,
      expect.objectContaining({sid: 3, fastFetch: false}),
    )
    expect(getContributionSpy).toHaveBeenNthCalledWith(
      4,
      expect.objectContaining({sid: 4, fastFetch: false}),
    )
  })

  test('returns expected percentage classes and percent text', () => {
    expect(
      TranslationMatches.getPercentageClass({match: '100%', ICE: false}),
    ).toBe('per-green')
    expect(
      TranslationMatches.getPercentageClass({match: '100%', ICE: true}),
    ).toBe('per-blue')
    expect(TranslationMatches.getPercentageClass({match: '75%-84%'})).toBe(
      'per-orange',
    )
    expect(TranslationMatches.getPercentageClass({match: 'MT'})).toBe(
      'per-yellow',
    )
    expect(TranslationMatches.getPercentageClass({match: 'ICE_MT'})).toBe(
      'per-green',
    )

    expect(TranslationMatches.getNumericMatchBaseOrMTString('75%-84%')).toBe(75)
    expect(TranslationMatches.getNumericMatchBaseOrMTString('MT')).toBe('MT')

    expect(
      TranslationMatches.getPercentTextForMatch({match: '100%', ICE: true}),
    ).toBe('101%')
    expect(
      TranslationMatches.getPercentTextForMatch({match: 'ICE_MT', ICE: false}),
    ).toBe('TQMT')
  })
})
