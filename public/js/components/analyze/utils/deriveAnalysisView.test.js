import {
  buildVolumeAnalysis,
  volumeAnalysisBuilders,
} from '../../../../mocks/volumeAnalysisMock'
import {ANALYSIS_STATUS} from '../../../constants/Constants'
import {
  ANALYSIS_LAYOUT,
  ANALYSIS_PHASE,
  deriveAnalysisView,
} from './deriveAnalysisView'

const {buildJob, buildChunk, buildFile} = volumeAnalysisBuilders

const file = (id) =>
  buildFile({
    id,
    name: `folder/file-${id}.docx`,
    originalName: `file-${id}.docx`,
    raw: 50,
    equivalent: 40,
  })

const job = (id, target, passwords) =>
  buildJob({
    id,
    source: 'en-US',
    sourceName: 'English (USA)',
    target,
    targetName: target,
    chunks: passwords.map((password, i) =>
      buildChunk({idJob: id, password, files: [file(id * 10 + i)]}),
    ),
  })

const withStatus = (status, summary = {}, jobs) =>
  buildVolumeAnalysis({summary: {status, ...summary}, jobs})

describe('deriveAnalysisView phase', () => {
  test.each([
    [ANALYSIS_STATUS.NEW, 0, ANALYSIS_PHASE.COUNTING],
    [ANALYSIS_STATUS.BUSY, 0, ANALYSIS_PHASE.COUNTING],
    ['', 0, ANALYSIS_PHASE.COUNTING],
    [undefined, 0, ANALYSIS_PHASE.COUNTING],
    [ANALYSIS_STATUS.NEW, 3, ANALYSIS_PHASE.QUEUE],
    [ANALYSIS_STATUS.BUSY, 3, ANALYSIS_PHASE.QUEUE],
    [ANALYSIS_STATUS.FAST_OK, 3, ANALYSIS_PHASE.QUEUE],
    [ANALYSIS_STATUS.FAST_OK, 0, ANALYSIS_PHASE.ANALYZING],
    [ANALYSIS_STATUS.DONE, 0, ANALYSIS_PHASE.DONE],
    [ANALYSIS_STATUS.NOT_TO_ANALYZE, 0, ANALYSIS_PHASE.NOT_ANALYZED],
    [ANALYSIS_STATUS.EMPTY, 0, ANALYSIS_PHASE.EMPTY],
  ])('status %p with %p in queue is %p', (status, inQueue, phase) => {
    const view = deriveAnalysisView({
      analysis: withStatus(status, {in_queue_before: inQueue}),
    })
    expect(view.phase).toBe(phase)
  })

  test('a missing analysis is counting with nothing to show', () => {
    const view = deriveAnalysisView({analysis: undefined})
    expect(view.phase).toBe(ANALYSIS_PHASE.COUNTING)
    expect(view.jobs).toEqual([])
    expect(view.layout).toBe(ANALYSIS_LAYOUT.TREE)
    expect(view.progress).toEqual({
      segmentsAnalyzed: 0,
      totalSegments: 0,
      percent: 0,
      inQueueBefore: 0,
    })
  })
})

describe('deriveAnalysisView known and actionsEnabled', () => {
  test.each([
    [
      ANALYSIS_STATUS.NEW,
      0,
      {raw: false, weighted: false, liveWeighted: false},
      false,
    ],
    [
      ANALYSIS_STATUS.BUSY,
      2,
      {raw: true, weighted: false, liveWeighted: false},
      false,
    ],
    [
      ANALYSIS_STATUS.FAST_OK,
      0,
      {raw: true, weighted: false, liveWeighted: true},
      false,
    ],
    [
      ANALYSIS_STATUS.DONE,
      0,
      {raw: true, weighted: true, liveWeighted: false},
      true,
    ],
    [
      ANALYSIS_STATUS.NOT_TO_ANALYZE,
      0,
      {raw: false, weighted: false, liveWeighted: false},
      false,
    ],
    [
      ANALYSIS_STATUS.EMPTY,
      0,
      {raw: false, weighted: false, liveWeighted: false},
      false,
    ],
  ])('status %p with %p in queue', (status, inQueue, known, actions) => {
    const view = deriveAnalysisView({
      analysis: withStatus(status, {in_queue_before: inQueue}),
    })
    expect(view.known).toEqual(known)
    expect(view.actionsEnabled).toBe(actions)
  })
})

describe('deriveAnalysisView layout', () => {
  test('several languages is a tree', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, [
        job(1, 'it-IT', ['a']),
        job(2, 'fr-FR', ['b']),
      ]),
    })
    expect(view.layout).toBe(ANALYSIS_LAYOUT.TREE)
  })

  test('one language split in chunks is a tree', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, [
        job(1, 'it-IT', ['a', 'b']),
      ]),
    })
    expect(view.layout).toBe(ANALYSIS_LAYOUT.TREE)
    expect(view.jobs[0].isSplit).toBe(true)
  })

  test('one language not split is single', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, [job(1, 'it-IT', ['a'])]),
    })
    expect(view.layout).toBe(ANALYSIS_LAYOUT.SINGLE)
    expect(view.jobs[0].isSplit).toBe(false)
  })

  test('the job page is job, whatever the jobs look like', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE),
      jobAnalysis: true,
    })
    expect(view.layout).toBe(ANALYSIS_LAYOUT.JOB)
  })

  test.each([ANALYSIS_STATUS.NOT_TO_ANALYZE, ANALYSIS_STATUS.EMPTY])(
    '%p is empty, also on the job page',
    (status) => {
      expect(deriveAnalysisView({analysis: withStatus(status)}).layout).toBe(
        ANALYSIS_LAYOUT.EMPTY,
      )
      expect(
        deriveAnalysisView({analysis: withStatus(status), jobAnalysis: true})
          .layout,
      ).toBe(ANALYSIS_LAYOUT.EMPTY)
    },
  )

  test('a job page with no jobs is empty', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, []),
      jobAnalysis: true,
    })
    expect(view.layout).toBe(ANALYSIS_LAYOUT.EMPTY)
    expect(view.jobs).toEqual([])
  })

  test('a job page that is still counting is job', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.NEW),
      jobAnalysis: true,
    })
    expect(view.layout).toBe(ANALYSIS_LAYOUT.JOB)
  })
})

describe('deriveAnalysisView progress and totals', () => {
  test('percent is floored', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.FAST_OK, {
        segments_analyzed: 2,
        total_segments: 3,
        in_queue_before: 0,
      }),
    })
    expect(view.progress).toEqual({
      segmentsAnalyzed: 2,
      totalSegments: 3,
      percent: 66,
      inQueueBefore: 0,
    })
  })

  test.each([
    [12, 10, 100],
    [-1, 10, 0],
  ])('percent of %p out of %p is clamped to %p', (analyzed, total, percent) => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.FAST_OK, {
        segments_analyzed: analyzed,
        total_segments: total,
      }),
    })
    expect(view.progress.percent).toBe(percent)
  })

  test('queue position is reported', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.NEW, {
        segments_analyzed: 0,
        total_segments: 10,
        in_queue_before: 7,
      }),
    })
    expect(view.progress).toEqual({
      segmentsAnalyzed: 0,
      totalSegments: 10,
      percent: 0,
      inQueueBefore: 7,
    })
  })

  test('project totals come from the summary', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE),
    })
    expect(view.totals).toEqual({
      raw: 320,
      industry: 275,
      equivalent: 260,
      discount: 19,
    })
  })
})

describe('deriveAnalysisView jobs', () => {
  test('normalizes jobs, chunks and files', () => {
    const analysis = withStatus(ANALYSIS_STATUS.DONE)
    const view = deriveAnalysisView({analysis})
    const [rawJob] = analysis.jobs
    const [rawChunk] = rawJob.chunks
    const [rawFile] = rawChunk.files

    expect(view.jobs).toHaveLength(2)
    const [italian, french] = view.jobs

    expect(italian).toEqual({
      id: 101,
      source: 'en-US',
      target: 'it-IT',
      sourceName: 'English (USA)',
      targetName: 'Italian',
      countUnit: 'words',
      payableRates: rawJob.payable_rates,
      outsourceAvailable: true,
      outsourceInfo: null,
      totals: {
        raw: rawJob.total_raw,
        industry: rawJob.total_industry,
        equivalent: rawJob.total_equivalent,
      },
      isSplit: true,
      chunkCount: 2,
      chunks: expect.any(Array),
    })
    expect(french.isSplit).toBe(false)

    expect(italian.chunks.map((chunk) => chunk.index)).toEqual([1, 2])
    expect(italian.chunks[0]).toEqual({
      index: 1,
      count: 2,
      password: 'chunkpass1',
      urls: rawChunk.urls,
      totals: {
        raw: rawChunk.total_raw,
        industry: rawChunk.total_industry,
        equivalent: rawChunk.total_equivalent,
      },
      summary: rawChunk.summary,
      files: [
        {
          id: 11,
          name: 'docs/manual.docx',
          totals: {
            raw: rawFile.total_raw,
            industry: null,
            equivalent: rawFile.total_equivalent,
          },
          matches: rawFile.matches,
        },
      ],
    })
  })

  test('the job page keeps the chunk position and count from the hook', () => {
    const [splitJob] = withStatus(ANALYSIS_STATUS.DONE, {}, [
      job(5, 'es-ES', ['a', 'b', 'c']),
    ]).jobs
    // What useVolumeAnalysis returns for the translator of chunk 2 of 3
    const analysis = withStatus(ANALYSIS_STATUS.DONE, {}, [
      {
        ...splitJob,
        chunks: [splitJob.chunks[1]],
        chunkPosition: 2,
        chunkCount: 3,
      },
    ])
    const view = deriveAnalysisView({analysis, jobAnalysis: true})
    const [viewJob] = view.jobs

    expect(view.layout).toBe(ANALYSIS_LAYOUT.JOB)
    expect(viewJob.isSplit).toBe(true)
    expect(viewJob.chunkCount).toBe(3)
    expect(viewJob.chunks).toHaveLength(1)
    expect(viewJob.chunks[0]).toMatchObject({index: 2, count: 3, password: 'b'})
  })

  test('the job page of an unsplit job is chunk 1 of 1', () => {
    const [singleJob] = withStatus(ANALYSIS_STATUS.DONE, {}, [
      job(6, 'es-ES', ['a']),
    ]).jobs
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, [
        {...singleJob, chunkPosition: 1, chunkCount: 1},
      ]),
      jobAnalysis: true,
    })
    expect(view.jobs[0].isSplit).toBe(false)
    expect(view.jobs[0].chunks[0]).toMatchObject({index: 1, count: 1})
  })

  test('project mode ignores chunkPosition and chunkCount', () => {
    const [splitJob] = withStatus(ANALYSIS_STATUS.DONE, {}, [
      job(7, 'es-ES', ['a', 'b']),
    ]).jobs
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, [
        {...splitJob, chunkPosition: 5, chunkCount: 9},
      ]),
    })
    expect(view.jobs[0].chunkCount).toBe(2)
    expect(view.jobs[0].chunks.map((chunk) => chunk.index)).toEqual([1, 2])
  })

  test('file name uses name, never original_name', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE, {}, [job(4, 'de-DE', ['a'])]),
    })
    const [viewFile] = view.jobs[0].chunks[0].files
    expect(viewFile.name).toBe('folder/file-40.docx')
    expect(viewFile).not.toHaveProperty('original_name')
  })

  test('keeps engine data out of the view', () => {
    const view = deriveAnalysisView({
      analysis: withStatus(ANALYSIS_STATUS.DONE),
    })
    expect(view.jobs[0].chunks[0]).not.toHaveProperty('engines')
  })

  test('does not mutate the analysis', () => {
    const analysis = withStatus(ANALYSIS_STATUS.DONE)
    const snapshot = JSON.parse(JSON.stringify(analysis))
    deriveAnalysisView({analysis})
    expect(analysis).toEqual(snapshot)
  })
})
