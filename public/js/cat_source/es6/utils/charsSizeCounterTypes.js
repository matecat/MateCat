import TEXT_UTILS from './textUtils'

export const CHARS_SIZE_TYPE_GOOGLE_ADS = {
  default: (value) => TEXT_UTILS.getDefaultCharsSize(value),
  custom: [
    (value) => TEXT_UTILS.getCJKMatches(value, TEXT_UTILS.getUft16CharsSize),
    (value) =>
      TEXT_UTILS.getArmenianMatches(value, TEXT_UTILS.getUft16CharsSize),
    (value) =>
      TEXT_UTILS.getGeorgianMatches(value, TEXT_UTILS.getUft16CharsSize),
    (value) =>
      TEXT_UTILS.getSinhalaMatches(value, TEXT_UTILS.getUft16CharsSize),
    (value) => TEXT_UTILS.getEmojiMatches(value, TEXT_UTILS.getUft16CharsSize),
    (value) =>
      TEXT_UTILS.getFullwidthVariantsMatches(
        value,
        TEXT_UTILS.getUft16CharsSize,
      ),
  ],
}

export const CHARS_SIZE_TYPE_EXCLUDE_CJK = {
  default: (value) => TEXT_UTILS.getDefaultCharsSize(value),
  custom: [
    (value) => TEXT_UTILS.getCJKMatches(value, TEXT_UTILS.getUft16CharsSize),
    (value) =>
      TEXT_UTILS.getFullwidthVariantsMatches(
        value,
        TEXT_UTILS.getUft16CharsSize,
      ),
  ],
}

export const CHARS_SIZE_TYPE_ALL_ONE = {
  default: (value) => TEXT_UTILS.getDefaultCharsSize(value),
}
