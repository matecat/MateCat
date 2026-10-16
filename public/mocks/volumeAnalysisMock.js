// Shapes follow the PHP serializers in lib/View/API/App/Json/Analysis/
// (AnalysisProject, AnalysisProjectSummary, AnalysisJob, AnalysisChunk,
// AnalysisFile, AnalysisMatch). Engine `extra` (credentials) is never included.

const MATCH_TYPES = [
  'new',
  'tm_50_74',
  'tm_75_84',
  'tm_85_94',
  'tm_95_99',
  'tm_100',
  'tm_100_public',
  'ice',
  'MT',
  'ice_mt',
  'repetitions',
  'internal',
  'numbers_only',
]

const buildMatches = (values = {}) =>
  MATCH_TYPES.map((type) => ({
    raw: values[type]?.raw ?? 0,
    equivalent: values[type]?.equivalent ?? 0,
    type,
  }))

const buildFile = ({id, name, originalName, raw, equivalent}) => ({
  id,
  id_file_part: null,
  name,
  original_name: originalName,
  total_raw: raw,
  total_equivalent: equivalent,
  matches: buildMatches({
    new: {raw: raw - 20, equivalent: equivalent - 2},
    tm_100: {raw: 20, equivalent: 2},
  }),
  metadata: [],
})

const buildChunk = ({idJob, password, files}) => {
  const raw = files.reduce((sum, file) => sum + file.total_raw, 0)
  const equivalent = files.reduce((sum, file) => sum + file.total_equivalent, 0)
  return {
    password,
    status: 'active',
    engines: {
      tm: {id: 1, name: 'MyMemory', type: 'TM'},
      mt: {id: 1, name: 'MyMemory', type: 'MT'},
    },
    memory_keys: [],
    urls: {
      t: `https://dev.matecat.com/translate/analyzed-project/en-US-it-IT/${idJob}-${password}`,
      r1: `https://dev.matecat.com/revise/analyzed-project/en-US-it-IT/${idJob}-${password}`,
    },
    files,
    summary: buildMatches({
      new: {raw: raw - 20 * files.length, equivalent: equivalent - 2},
      tm_100: {raw: 20 * files.length, equivalent: 2},
    }),
    total_raw: raw,
    total_equivalent: equivalent,
    total_industry: equivalent + 5,
  }
}

const buildJob = ({id, source, sourceName, target, targetName, chunks}) => ({
  id,
  source,
  source_name: sourceName,
  target,
  target_name: targetName,
  chunks,
  total_raw: chunks.reduce((sum, chunk) => sum + chunk.total_raw, 0),
  total_equivalent: chunks.reduce(
    (sum, chunk) => sum + chunk.total_equivalent,
    0,
  ),
  total_industry: chunks.reduce((sum, chunk) => sum + chunk.total_industry, 0),
  outsource_available: true,
  payable_rates: {NO_MATCH: 100, '100%': 30, ICE: 0, MT: 72},
  count_unit: 'words',
})

// Job 101 (en-US > it-IT) is split in two chunks; job 102 (en-US > fr-FR) is not.
export const buildVolumeAnalysis = ({summary = {}, jobs} = {}) => ({
  name: 'Analyzed project',
  status: 'NEW',
  create_date: '2026-10-01 10:00:00',
  subject: 'general',
  workflow_type: 'standard',
  jobs: jobs ?? [
    buildJob({
      id: 101,
      source: 'en-US',
      sourceName: 'English (USA)',
      target: 'it-IT',
      targetName: 'Italian',
      chunks: [
        buildChunk({
          idJob: 101,
          password: 'chunkpass1',
          files: [
            buildFile({
              id: 11,
              name: 'docs/manual.docx',
              originalName: 'manual.docx',
              raw: 120,
              equivalent: 100,
            }),
          ],
        }),
        buildChunk({
          idJob: 101,
          password: 'chunkpass2',
          files: [
            buildFile({
              id: 12,
              name: 'docs/guide.docx',
              originalName: 'guide.docx',
              raw: 80,
              equivalent: 60,
            }),
          ],
        }),
      ],
    }),
    buildJob({
      id: 102,
      source: 'en-US',
      sourceName: 'English (USA)',
      target: 'fr-FR',
      targetName: 'French',
      chunks: [
        buildChunk({
          idJob: 102,
          password: 'frpass',
          files: [
            buildFile({
              id: 13,
              name: 'docs/manual.docx',
              originalName: 'manual.docx',
              raw: 120,
              equivalent: 100,
            }),
          ],
        }),
      ],
    }),
  ],
  summary: {
    in_queue_before: 0,
    total_segments: 40,
    segments_analyzed: 40,
    status: 'DONE',
    total_raw: 320,
    total_industry: 275,
    total_equivalent: 260,
    discount: 19,
    ...summary,
  },
  analyze_url: 'https://dev.matecat.com/analyze/analyzed-project/20-projpass',
})

export const volumeAnalysisBuilders = {buildJob, buildChunk, buildFile}

export const projectMock = {
  id: 20,
  password: 'projpass',
  name: 'Analyzed project',
  id_team: 1,
  id_assignee: 1,
  create_date: '2026-10-01 10:00:00',
  fast_analysis_wc: 320,
  standard_analysis_wc: 320,
  tm_analysis_wc: '260',
  project_slug: 'analyzed-project',
  jobs: [],
  features: '',
  is_cancelled: false,
  is_archived: false,
  remote_file_service: null,
  due_date: null,
  project_info: null,
  analysis: {},
}
