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
import CommonUtils from '../../../utils/commonUtils'
import OfflineUtils from '../../../utils/offlineUtils'
import Speech2Text from '../../../utils/speech2text'
import DraftMatecatUtils from './DraftMatecatUtils'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolActions from '../../../actions/CatToolActions'
import {getContributions} from '../../../api/getContributions'
import {deleteContribution} from '../../../api/deleteContribution'
import {laraAuthJob} from '../../../api/laraAuth'
import {laraTranslate} from '../../../api/laraTranslate'
import {addClassToSegment} from '../../../actions/segmentClassActions'
import {
  replaceEditAreaTextContent,
  setHeaderPercentage,
  modifiedTranslation,
  setSegmentContributions,
  setChoosenSuggestion,
} from '../../../actions/segmentDispatchActions'
import {getSegmentsQa, startSegmentQACheck} from '../../../actions/segmentQaActions'
import {disableTPOnSegment} from '../../../actions/tagProjectionActions'

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

    getContributionSpy.mockRestore()
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

  test('getPercentageClass returns an empty string for unrecognized match values', () => {
    expect(
      TranslationMatches.getPercentageClass({match: 'unexpected', ICE: false}),
    ).toBe('')
  })

  describe('copySuggestionInEditarea', () => {
    test('does nothing when translation matches are disabled', () => {
      global.config.translation_matches_enabled = false
      const segment = {
        sid: 1,
        id_file: 10,
        translation: '',
        contributions: {matches: [{translation: 'hi', match: '100%'}]},
      }

      TranslationMatches.copySuggestionInEditarea(segment, 1)

      expect(replaceEditAreaTextContent).not.toHaveBeenCalled()
    })

    test('copies the given translation into the edit area and updates the header', () => {
      const segment = {
        sid: 1,
        id_file: 10,
        translation: 'old',
        contributions: {
          matches: [
            {translation: 'match translation', match: '100%', created_by: 'MT'},
          ],
        },
      }

      TranslationMatches.copySuggestionInEditarea(segment, 1, 'new translation')

      expect(replaceEditAreaTextContent).toHaveBeenCalledWith(
        1,
        'new translation',
      )
      expect(setHeaderPercentage).toHaveBeenCalledWith(
        1,
        10,
        segment.contributions.matches[0],
        'per-green',
        'MT',
      )
      expect(startSegmentQACheck).toHaveBeenCalled()
      expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
        'contribution:copied',
        {translation: 'new translation', segment},
      )
      expect(modifiedTranslation).toHaveBeenCalledWith(1, true)
    })

    test('falls back to the matched contribution translation when none is provided', () => {
      const segment = {
        sid: 1,
        id_file: 10,
        translation: '',
        contributions: {matches: [{translation: 'fallback translation'}]},
      }

      TranslationMatches.copySuggestionInEditarea(segment, 1)

      expect(replaceEditAreaTextContent).toHaveBeenCalledWith(
        1,
        'fallback translation',
      )
    })

    test('skips all side effects when the resolved translation is blank', () => {
      const segment = {
        sid: 1,
        id_file: 10,
        translation: '',
        contributions: {matches: [{translation: '   '}]},
      }

      TranslationMatches.copySuggestionInEditarea(segment, 1)

      expect(replaceEditAreaTextContent).not.toHaveBeenCalled()
    })
  })

  describe('renderContributions', () => {
    test('returns true immediately when there is no data', () => {
      expect(TranslationMatches.renderContributions(null, 1)).toBe(true)
      expect(setSegmentContributions).not.toHaveBeenCalled()
    })

    test('does nothing when the segment cannot be found', () => {
      TranslationMatches.renderContributions({matches: [], errors: []}, 999)

      expect(setSegmentContributions).not.toHaveBeenCalled()
    })

    test('stores contributions, applies suggestions and marks the segment as loaded', () => {
      const useSuggestionSpy = jest
        .spyOn(TranslationMatches, 'useSuggestionInEditArea')
        .mockImplementation(() => {})

      TranslationMatches.renderContributions(
        {matches: [{translation: 'x'}], errors: []},
        1,
      )

      expect(setSegmentContributions).toHaveBeenCalledWith(
        1,
        [{translation: 'x'}],
        [],
      )
      expect(useSuggestionSpy).toHaveBeenCalledWith(1)
      expect(addClassToSegment).toHaveBeenCalledWith(1, 'loaded')

      useSuggestionSpy.mockRestore()
    })
  })

  describe('useSuggestionInEditArea', () => {
    const makeSegment = (overrides = {}) => ({
      sid: 1,
      translation: '',
      opened: true,
      contributions: {matches: [{translation: 'suggested', match: '100%'}]},
      ...overrides,
    })

    test('does nothing when there are no contribution matches', () => {
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({contributions: {matches: []}}),
      )

      TranslationMatches.useSuggestionInEditArea(1)

      expect(setChoosenSuggestion).not.toHaveBeenCalled()
    })

    test('does nothing when the top match reports an error', () => {
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({
          contributions: {matches: [{translation: 'x', match: '100%', error: 'boom'}]},
        }),
      )

      TranslationMatches.useSuggestionInEditArea(1)

      expect(setChoosenSuggestion).not.toHaveBeenCalled()
    })

    test('does nothing when the match percentage is too low', () => {
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({
          contributions: {matches: [{translation: 'x', match: '50%'}]},
        }),
      )

      TranslationMatches.useSuggestionInEditArea(1)

      expect(setChoosenSuggestion).not.toHaveBeenCalled()
    })

    test('does nothing when the edit area is not empty', () => {
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({translation: 'already typed'}),
      )

      TranslationMatches.useSuggestionInEditArea(1)

      expect(setChoosenSuggestion).not.toHaveBeenCalled()
    })

    test('disables tag projection instead of stripping tags for a 100% match', () => {
      SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
      SegmentStore.getSegmentByIdToJS.mockReturnValue(makeSegment())

      TranslationMatches.useSuggestionInEditArea(1)

      expect(setChoosenSuggestion).toHaveBeenCalledWith(1, 1)
      expect(disableTPOnSegment).toHaveBeenCalled()
      expect(DraftMatecatUtils.removeTagsFromText).not.toHaveBeenCalled()
    })

    test('strips tags instead of disabling tag projection for a non-100% match', () => {
      SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({
          contributions: {matches: [{translation: 'suggested', match: '80%'}]},
        }),
      )

      TranslationMatches.useSuggestionInEditArea(1)

      expect(DraftMatecatUtils.removeTagsFromText).toHaveBeenCalledWith(
        'suggested',
      )
      expect(disableTPOnSegment).not.toHaveBeenCalled()
    })

    test('leaves the translation untouched when tag projection is disabled', () => {
      SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(false)
      SegmentStore.getSegmentByIdToJS.mockReturnValue(makeSegment())

      TranslationMatches.useSuggestionInEditArea(1)

      expect(DraftMatecatUtils.removeTagsFromText).not.toHaveBeenCalled()
      expect(disableTPOnSegment).not.toHaveBeenCalled()
    })

    test('does not auto-copy the suggestion when the segment is not opened', () => {
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({opened: false}),
      )
      const copySpy = jest
        .spyOn(TranslationMatches, 'copySuggestionInEditarea')
        .mockImplementation(() => {})

      TranslationMatches.useSuggestionInEditArea(1)

      expect(copySpy).not.toHaveBeenCalled()
      copySpy.mockRestore()
    })

    test('does not auto-copy the suggestion when auto-copy is disabled', () => {
      global.config.translation_matches_enabled = false
      SegmentStore.getSegmentByIdToJS.mockReturnValue(makeSegment())
      const copySpy = jest
        .spyOn(TranslationMatches, 'copySuggestionInEditarea')
        .mockImplementation(() => {})

      TranslationMatches.useSuggestionInEditArea(1)

      expect(copySpy).not.toHaveBeenCalled()
      copySpy.mockRestore()
    })

    test('auto-copies the suggestion when speech2text is disabled', () => {
      Speech2Text.enabled.mockReturnValue(false)
      SegmentStore.getSegmentByIdToJS.mockReturnValue(makeSegment())
      const copySpy = jest
        .spyOn(TranslationMatches, 'copySuggestionInEditarea')
        .mockImplementation(() => {})

      TranslationMatches.useSuggestionInEditArea(1)

      expect(copySpy).toHaveBeenCalledWith(
        expect.objectContaining({sid: 1}),
        1,
        'suggested',
      )
      copySpy.mockRestore()
    })

    test('auto-copies the suggestion when speech2text allows this contribution', () => {
      Speech2Text.enabled.mockReturnValue(true)
      Speech2Text.isContributionToBeAllowed.mockReturnValue(true)
      SegmentStore.getSegmentByIdToJS.mockReturnValue(makeSegment())
      const copySpy = jest
        .spyOn(TranslationMatches, 'copySuggestionInEditarea')
        .mockImplementation(() => {})

      TranslationMatches.useSuggestionInEditArea(1)

      expect(copySpy).toHaveBeenCalled()
      copySpy.mockRestore()
    })

    test('does not auto-copy when speech2text disallows this contribution', () => {
      Speech2Text.enabled.mockReturnValue(true)
      Speech2Text.isContributionToBeAllowed.mockReturnValue(false)
      SegmentStore.getSegmentByIdToJS.mockReturnValue(makeSegment())
      const copySpy = jest
        .spyOn(TranslationMatches, 'copySuggestionInEditarea')
        .mockImplementation(() => {})

      TranslationMatches.useSuggestionInEditArea(1)

      expect(copySpy).not.toHaveBeenCalled()
      copySpy.mockRestore()
    })

    test('recognizes the MT and ICE_MT string match values', () => {
      SegmentStore.getSegmentByIdToJS.mockReturnValue(
        makeSegment({
          contributions: {matches: [{translation: 'suggested', match: 'MT'}]},
        }),
      )

      TranslationMatches.useSuggestionInEditArea(1)

      expect(setChoosenSuggestion).toHaveBeenCalledWith(1, 1)
    })
  })

  describe('getContributionsWithPrefetch fill-up loop', () => {
    test('fills remaining prefetch slots with subsequent segments when untranslated ones run out', () => {
      SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => ({sid}))
      SegmentStore.getNextSegment
        .mockReturnValueOnce({sid: 2}) // initial nextSegment
        .mockReturnValueOnce(undefined) // no more untranslated segments
        .mockReturnValueOnce({sid: 3}) // fill-up loop
        .mockReturnValueOnce({sid: 4}) // fill-up loop

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
      expect(getContributionSpy.mock.calls.map(([arg]) => arg.sid)).toEqual([
        1, 2, 3, 4,
      ])

      getContributionSpy.mockRestore()
    })
  })

  describe('getContribution additional branches', () => {
    test('resolves immediately when the segment does not exist', async () => {
      await expect(
        TranslationMatches.getContribution({
          sid: 999,
          crossLanguageSettings: null,
          force: false,
        }),
      ).resolves.toBeUndefined()

      expect(getContributions).not.toHaveBeenCalled()
    })

    test('marks the segment as loaded and runs QA checks when translation matches are disabled', async () => {
      global.config.translation_matches_enabled = false

      await TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })

      expect(addClassToSegment).toHaveBeenCalledWith(1, 'loaded')
      expect(getSegmentsQa).toHaveBeenCalled()
      expect(getContributions).not.toHaveBeenCalled()
    })

    test('reuses existing contributions without scheduling a suggestion when not applicable', () => {
      jest.useFakeTimers()
      SegmentStore.getSegmentByIdToJS.mockReturnValue({
        sid: 1,
        original_sid: 101,
        segment: 'source text',
        contributions: {matches: [{translation: 'x'}]},
        status: 'TRANSLATED',
        translation: 'already there',
        opened: true,
      })
      const useSuggestionSpy = jest
        .spyOn(TranslationMatches, 'useSuggestionInEditArea')
        .mockImplementation(() => {})

      TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })
      jest.runOnlyPendingTimers()

      expect(getContributions).not.toHaveBeenCalled()
      expect(useSuggestionSpy).not.toHaveBeenCalled()

      jest.useRealTimers()
      useSuggestionSpy.mockRestore()
    })

    test('schedules a suggestion update when reusing contributions for a new, untranslated, open segment', () => {
      jest.useFakeTimers()
      SegmentStore.getSegmentByIdToJS.mockReturnValue({
        sid: 1,
        original_sid: 101,
        segment: 'source text',
        contributions: {matches: [{translation: 'x'}]},
        status: 'NEW',
        translation: '',
        opened: true,
      })
      const useSuggestionSpy = jest
        .spyOn(TranslationMatches, 'useSuggestionInEditArea')
        .mockImplementation(() => {})

      TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })
      jest.runOnlyPendingTimers()

      expect(useSuggestionSpy).toHaveBeenCalledWith(1)

      jest.useRealTimers()
      useSuggestionSpy.mockRestore()
    })

    test('forces a refetch when the segment is similar to the last translated one, even with existing matches', async () => {
      SegmentStore.lastTranslatedSegmentId = 5
      SegmentStore.getSegmentByIdToJS.mockImplementation((sid) => {
        if (sid === 5) return {sid: 5, segment: 'source text'}
        if (sid === 1) {
          return {
            sid: 1,
            original_sid: 101,
            segment: 'source text',
            contributions: {matches: [{translation: 'x'}]},
            status: 'TRANSLATED',
            translation: 'already there',
            opened: true,
          }
        }
        return undefined
      })
      CommonUtils.levenshteinDistance.mockReturnValue(0)
      global.config.active_engine = {engine_type: 'DeepL'}

      await TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })

      expect(getContributions).toHaveBeenCalledWith(
        expect.objectContaining({idSegment: 101}),
      )
    })

    test('retries after a delay when the client id is not yet available', () => {
      jest.useFakeTimers()
      const getContributionSpy = jest.spyOn(TranslationMatches, 'getContribution')
      delete global.config.id_client

      TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
        fastFetch: false,
      })

      expect(getContributions).not.toHaveBeenCalled()
      jest.advanceTimersByTime(3000)

      expect(getContributionSpy).toHaveBeenCalledTimes(2)

      jest.useRealTimers()
      getContributionSpy.mockRestore()
    })

    test('does not start a duplicate request for a segment already awaiting contributions', async () => {
      TranslationMatches.segmentsWaitingForContributions = [101]
      global.config.active_engine = {engine_type: 'DeepL'}

      await TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })

      expect(getContributions).not.toHaveBeenCalled()
    })

    test('fetches contributions directly when the active engine is not Lara', async () => {
      global.config.active_engine = {engine_type: 'DeepL'}

      await TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })

      expect(laraAuthJob).not.toHaveBeenCalled()
      expect(getContributions).toHaveBeenCalledWith(
        expect.objectContaining({idSegment: 101, translation: null}),
      )
    })

    test('skips the Lara flow on fast fetch even when Lara prosa is allowed', async () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        project: {mt_extra: {lara_style_guideline_id: 'style-1'}},
      })

      await TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
        fastFetch: true,
      })

      expect(laraAuthJob).not.toHaveBeenCalled()
      expect(getContributions).toHaveBeenCalled()
    })

    test('falls back to classic contributions when Lara translate fails', async () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        project: {mt_extra: {lara_style_guideline_id: 'style-1'}},
      })
      laraTranslate.mockRejectedValueOnce(new Error('translate failed'))

      TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })
      await flushPromises()

      expect(getContributions).toHaveBeenCalledWith(
        expect.objectContaining({idSegment: 101, translation: null}),
      )
    })

    test('reports processing errors when the contribution request itself fails', async () => {
      global.config.active_engine = {engine_type: 'DeepL'}
      getContributions.mockRejectedValueOnce(new Error('network error'))

      TranslationMatches.getContribution({
        sid: 1,
        crossLanguageSettings: null,
        force: false,
      })
      await flushPromises()

      expect(CatToolActions.processErrors).toHaveBeenCalled()
      expect(setSegmentContributions).toHaveBeenCalledWith(
        101,
        [],
        expect.any(Error),
      )
    })
  })

  describe('processContributions', () => {
    test('does nothing when translation matches are disabled', () => {
      global.config.translation_matches_enabled = false

      TranslationMatches.processContributions({matches: []}, 1)

      expect(setSegmentContributions).not.toHaveBeenCalled()
    })

    test('does nothing when there is no data', () => {
      TranslationMatches.processContributions(null, 1)

      expect(setSegmentContributions).not.toHaveBeenCalled()
    })

    test('filters out matches missing a segment or translation before rendering', () => {
      const renderSpy = jest
        .spyOn(TranslationMatches, 'renderContributions')
        .mockImplementation(() => {})
      const data = {
        matches: [
          {segment: 's1', translation: 't1'},
          {segment: '', translation: 't2'},
          {segment: 's3', translation: ''},
        ],
        errors: [],
      }

      TranslationMatches.processContributions(data, 1)

      expect(renderSpy).toHaveBeenCalledWith(
        {matches: [{segment: 's1', translation: 't1'}], errors: []},
        1,
      )
      renderSpy.mockRestore()
    })
  })

  test('autoCopySuggestionEnabled reflects the translation_matches_enabled config flag', () => {
    global.config.translation_matches_enabled = true
    expect(TranslationMatches.autoCopySuggestionEnabled()).toBe(true)

    global.config.translation_matches_enabled = false
    expect(TranslationMatches.autoCopySuggestionEnabled()).toBe(false)
  })

  test('renderContributionErrors stores empty matches with the given errors', () => {
    const errors = [{message: 'oops'}]

    TranslationMatches.renderContributionErrors(errors, 55)

    expect(setSegmentContributions).toHaveBeenCalledWith(55, [], errors)
  })

  describe('setDeleteSuggestion', () => {
    test('deletes the contribution', async () => {
      await TranslationMatches.setDeleteSuggestion('src', 'tgt', 5, 1)

      expect(deleteContribution).toHaveBeenCalledWith({
        source: 'src',
        target: 'tgt',
        id: 5,
        sid: 1,
      })
    })

    test('notifies offline failure when the delete request fails', async () => {
      deleteContribution.mockRejectedValueOnce(new Error('network'))

      await TranslationMatches.setDeleteSuggestion('src', 'tgt', 5, 1)

      expect(OfflineUtils.failedConnection).toHaveBeenCalled()
    })
  })
})
