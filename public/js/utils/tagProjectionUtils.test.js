jest.mock('../stores/UserStore', () => ({
  getUserMetadata: jest.fn(),
}))

import UserStore from '../stores/UserStore'
import {
  checkTPSupportedLanguage,
  checkTPEnabled,
  checkCurrentSegmentTPEnabled,
} from './tagProjectionUtils'

beforeEach(() => {
  global.config = {
    ...global.config,
    source_code: 'it-IT',
    target_code: 'en-GB',
    tag_projection_languages: {'it-en': true},
    isReview: false,
  }
})

test('checkTPSupportedLanguage returns true when language pair is supported', () => {
  expect(checkTPSupportedLanguage()).toBe(true)
})

test('checkTPSupportedLanguage returns false when language pair is not supported', () => {
  global.config.tag_projection_languages = {'fr-de': true}

  expect(checkTPSupportedLanguage()).toBe(false)
})

test('checkTPEnabled returns true when supported, guess_tags is 1, and not a review', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})

  expect(checkTPEnabled()).toBe(true)
})

test('checkTPEnabled returns false when guess_tags is not 1', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 0})

  expect(checkTPEnabled()).toBe(false)
})

test('checkTPEnabled returns false when config.isReview is true', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})
  global.config.isReview = true

  expect(checkTPEnabled()).toBe(false)
})

test('checkCurrentSegmentTPEnabled returns false when no segment is passed', () => {
  expect(checkCurrentSegmentTPEnabled(undefined)).toBe(false)
})

test('checkCurrentSegmentTPEnabled returns false when TP is not enabled', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 0})

  expect(checkCurrentSegmentTPEnabled({segment: 'hello', tagged: false})).toBe(
    false,
  )
})

test('checkCurrentSegmentTPEnabled returns true for an untagged segment with original tags', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})

  const segment = {
    segment: '<g id="1" data-original-content="X">hello</g>',
    tagged: false,
  }

  expect(checkCurrentSegmentTPEnabled(segment)).toBe(true)
})

test('checkCurrentSegmentTPEnabled returns false when segment is already tagged', () => {
  UserStore.getUserMetadata.mockReturnValue({guess_tags: 1})

  const segment = {
    segment: '<g id="1" data-original-content="X">hello</g>',
    tagged: true,
  }

  expect(checkCurrentSegmentTPEnabled(segment)).toBe(false)
})
