export const JOB_STATUS = {
  ACTIVE: 'active',
  ARCHIVED: 'archived',
  CANCELLED: 'cancelled',
}

export const JOB_WORD_CONT_TYPE = {
  RAW: 'raw',
  EQUIVALENT: 'equivalent',
}

export const REVISE_STEP_NUMBER = {
  REVISE1: 1,
  REVISE2: 2,
}

export const SEGMENTS_STATUS = {
  APPROVED: 'APPROVED',
  APPROVED2: 'APPROVED2',
  NEW: 'NEW',
  DRAFT: 'DRAFT',
  TRANSLATED: 'TRANSLATED',
  UNTRANSLATED: 'UNTRANSLATED',
  UNAPPROVED: 'UNAPPROVED',
}

export const ANALYSIS_STATUS = {
  NEW: 'NEW',
  BUSY: 'BUSY',
  EMPTY: 'EMPTY',
  DONE: 'DONE',
  NOT_TO_ANALYZE: 'NOT_TO_ANALYZE',
}

export const UNIT_COUNT = {
  WORDS: 'words',
  CHARACTERS: 'characters',
}

export const NUM_CONTRIBUTION_RESULTS = 3
export const NUM_CONCORDANCE_RESULTS = 10

export const EMAIL_PATTERN =
  /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/

export const METADATA_KEY = 'cross_language_matches'

export const ANALYSIS_BUCKETS_LABELS = {
  MT: 'Machine Translation',
  new: 'New',
  repetitions: 'Repetitions',
  internal: 'Internal     75-99%',
  tm_50_74: 'TM Partial 50-74%',
  tm_75_84: 'TM Partial 75-84%',
  tm_85_94: 'TM Partial 85-94%',
  tm_95_99: 'TM Partial 95-99%',
  tm_100: 'TM 100%',
  tm_100_public: 'Public TM 100%',
  ice: 'TM 100% in context',
  ice_mt: 'Top Quality Machine Translation',
  top_quality_mt: 'Premium Machine Translation',
  higher_quality_mt: 'Enhanced Machine Translation',
  standard_quality_mt: 'Baseline Machine Translation',
  numbers_only: 'Numbers Only',
}

export const ANALYSIS_WORKFLOW_TYPES = {
  STANDARD: 'standard',
  MTQE: 'mtqe',
}
