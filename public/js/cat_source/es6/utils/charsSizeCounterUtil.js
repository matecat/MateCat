export const CHARS_SIZE_COUNTER_TYPES = {
  GOOGLE_ADS: 'google_ads',
  EXCLUDE_CJK: 'exclude_cjk',
  ALL_ONE: 'all_one',
}

export const charsSizeCounter = {}
let currentCounterType
Object.defineProperty(charsSizeCounter, 'map', {
  get: () =>
    currentCounterType
      ? counterTypes[currentCounterType]
      : counterTypes[CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS],
  set: (value) => {
    currentCounterType = value
  },
})

// Counter chars size methods
export const getDefaultCharsSize = (value) => value.length * 1
const getUtf8CharsSize = (value) => new Blob([value]).size
const getUft16CharsSize = (value) => value.length * 2
const getCJKMatches = (value, getSize) => {
  const regex =
    /[\u4E00-\u9FCC\u3400-\u4DB5\u{20000}-\u{2A6D6}\u{2B820}-\u{2CEAF}\u{2CEB0}-\u{2EBEF}\u{2B740}-\u{2B81F}\u{2A700}-\u{2B73F}\u30A0-\u30FF\uF900-\uFaff\u{1B000}-\u{1B0FF}\u{1B100}-\u{1B12F}\u{1B130}-\u{1B16F}\uAC00-\uD7AF\uD7B0-\uD7FF\u3000-\u303F\u3040-\u309F]/gu
  let match
  const result = []

  while ((match = regex.exec(value)) !== null) {
    const char = match[0]
    result.push({
      match: char,
      index: match.index,
      length: char.length,
      size: getSize(char),
    })
  }

  return result
}
const getArmenianMatches = (value, getSize) => {
  const regex = /[\u0530-\u058F]/g
  let match
  const result = []

  while ((match = regex.exec(value)) !== null) {
    const char = match[0]
    result.push({
      match: char,
      index: match.index,
      length: char.length,
      size: getSize(char),
    })
  }

  return result
}
const getGeorgianMatches = (value, getSize) => {
  const regex = /[\u10A0-\u10FF\u1C90-\u1CBF\u2D00-\u2D2F]/g
  let match
  const result = []

  while ((match = regex.exec(value)) !== null) {
    const char = match[0]
    result.push({
      match: char,
      index: match.index,
      length: char.length,
      size: getSize(char),
    })
  }

  return result
}
const getSinhalaMatches = (value, getSize) => {
  const regex = /[\u0D80-\u0DFF]/g
  let match
  const result = []

  while ((match = regex.exec(value)) !== null) {
    const char = match[0]
    result.push({
      match: char,
      index: match.index,
      length: char.length,
      size: getSize(char),
    })
  }

  return result
}
const getEmojiMatches = (value, getSize) => {
  const regex =
    /(\u00a9|\u00ae|[\u2000-\u3300]|\ud83c[\ud000-\udfff]|\ud83d[\ud000-\udfff]|\ud83e[\ud000-\udfff])/g
  let match
  const result = []

  while ((match = regex.exec(value)) !== null) {
    const char = match[0]
    result.push({
      match: char,
      index: match.index,
      length: char.length,
      size: getSize(char),
    })
  }

  return result
}
const getLatinCharsMatches = (value, getSize) => {
  const result = []

  for (var i = 0; i < value.length; i++) {
    const char = value[i]
    if (value.charCodeAt(i) <= 255) {
      result.push({
        match: char,
        index: i,
        length: char.length,
        size: getSize(char),
      })
    }
  }
  return result
}
const getFullwidthVariantsMatches = (value, getSize) => {
  const regex = /[\uFF01-\uFF60]/g
  let match
  const result = []

  while ((match = regex.exec(value)) !== null) {
    const char = match[0]
    result.push({
      match: char,
      index: match.index,
      length: char.length,
      size: getSize(char),
    })
  }

  return result
}
//

const counterTypes = {
  [CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS]: {
    default: (value) => getDefaultCharsSize(value),
    custom: [
      (value) => getCJKMatches(value, getUft16CharsSize),
      (value) => getArmenianMatches(value, getUft16CharsSize),
      (value) => getGeorgianMatches(value, getUft16CharsSize),
      (value) => getSinhalaMatches(value, getUft16CharsSize),
      (value) => getEmojiMatches(value, getUft16CharsSize),
      (value) => getFullwidthVariantsMatches(value, getUft16CharsSize),
    ],
  },
  [CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK]: {
    default: (value) => getDefaultCharsSize(value),
    custom: [
      (value) => getCJKMatches(value, getUft16CharsSize),
      (value) => getFullwidthVariantsMatches(value, getUft16CharsSize),
    ],
  },
  [CHARS_SIZE_COUNTER_TYPES.ALL_ONE]: {
    default: (value) => getDefaultCharsSize(value),
  },
}
