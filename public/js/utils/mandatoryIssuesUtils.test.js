import {isIssueMandatoryForCurrentRevision} from './mandatoryIssuesUtils'
import CatToolStore from '../stores/CatToolStore'

jest.mock('../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(),
}))

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {revisionNumber: 1}
})

describe('isIssueMandatoryForCurrentRevision', () => {
  test('returns true when the current revision is listed', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      job: {mandatory_issues: ['r1', 'r2']},
    })
    expect(isIssueMandatoryForCurrentRevision()).toBe(true)
  })

  test('returns false when the current revision is not listed', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      job: {mandatory_issues: ['r2']},
    })
    expect(isIssueMandatoryForCurrentRevision()).toBe(false)
  })

  test('returns false for an empty array, meaning no round requires an issue', () => {
    CatToolStore.getJobMetadata.mockReturnValue({job: {mandatory_issues: []}})
    expect(isIssueMandatoryForCurrentRevision()).toBe(false)
  })

  test('honours the revision number when resolving the key', () => {
    global.config.revisionNumber = 2
    CatToolStore.getJobMetadata.mockReturnValue({
      job: {mandatory_issues: ['r2']},
    })
    expect(isIssueMandatoryForCurrentRevision()).toBe(true)
  })

  test('defaults to mandatory when the job has no stored value', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      job: {mandatory_issues: undefined},
    })
    expect(isIssueMandatoryForCurrentRevision()).toBe(true)
  })

  test('defaults to mandatory, without throwing, when job metadata failed to load', () => {
    CatToolStore.getJobMetadata.mockReturnValue(undefined)
    expect(() => isIssueMandatoryForCurrentRevision()).not.toThrow()
    expect(isIssueMandatoryForCurrentRevision()).toBe(true)
  })

  test('defaults to mandatory when the stored value is not an array', () => {
    CatToolStore.getJobMetadata.mockReturnValue({
      job: {mandatory_issues: 'r2'},
    })
    expect(isIssueMandatoryForCurrentRevision()).toBe(true)
  })
})
