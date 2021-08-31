import {screen, waitForElementToBeRemoved} from '@testing-library/react'
import {rest} from 'msw'
import {unmountComponentAtNode} from 'react-dom'

import {mswServer} from '../mocks/mswServer'

test('renders properly', async () => {
  mswServer.use(
    ...[
      rest.get('*/api/app/user', (req, res, ctx) => {
        return res(
          ctx.status(401),
          ctx.json({
            errors: [
              {
                code: 401,
                message: 'Invalid Login.',
              },
            ],
            data: [],
          }),
        )
      }),
      rest.get(
        '*/api/v2/projects/:project_id/:job_password',
        (req, res, ctx) => {
          return res(
            ctx.status(200),
            ctx.json({
              project: {
                id: 3870270,
                password: 'bd0ab3349a51',
                name: 'test',
                create_date: '2021-04-13 15:01:44',
                fast_analysis_wc: 0,
                standard_analysis_wc: 413,
                tm_analysis_wc: '0.00',
                project_slug: 'test',
                jobs: [
                  {
                    id: 3882183,
                    password: 'a9cdacccd095',
                    source: 'fr-CH',
                    target: 'de-DE',
                    sourceTxt: 'French Swiss',
                    targetTxt: 'German',
                    job_first_segment: '1865425308',
                    status: 'active',
                    subject: 'general',
                    subject_printable: 'General',
                    open_threads_count: 0,
                    create_timestamp: 1618318904,
                    created_at: '2021-04-13T15:01:44+02:00',
                    create_date: '2021-04-13 15:01:44',
                    formatted_create_date: 'Apr 13, 15:01',
                    quality_overall: 'excellent',
                    pee: 0,
                    tte: 0,
                    warnings_count: 0,
                    warning_segments: [],
                    stats: {
                      id: 3882183,
                      DRAFT: 0,
                      TRANSLATED: 413,
                      APPROVED: 0,
                      REJECTED: 0,
                      TOTAL: 413,
                      PROGRESS: 413,
                      TOTAL_FORMATTED: '413',
                      PROGRESS_FORMATTED: '413',
                      APPROVED_FORMATTED: '0',
                      REJECTED_FORMATTED: '0',
                      DRAFT_FORMATTED: '0',
                      TRANSLATED_FORMATTED: '413',
                      APPROVED_PERC: 0,
                      REJECTED_PERC: 0,
                      DRAFT_PERC: 0,
                      TRANSLATED_PERC: 100,
                      PROGRESS_PERC: 100,
                      TRANSLATED_PERC_FORMATTED: 100,
                      DRAFT_PERC_FORMATTED: 0,
                      APPROVED_PERC_FORMATTED: 0,
                      REJECTED_PERC_FORMATTED: 0,
                      PROGRESS_PERC_FORMATTED: 100,
                      TODO_FORMATTED: '0',
                      TODO: 0,
                      DOWNLOAD_STATUS: 'translated',
                      revises: [
                        {
                          revision_number: 1,
                          advancement_wc: 0,
                        },
                      ],
                    },
                    outsource: null,
                    total_raw_wc: 413,
                    standard_wc: 0,
                    quality_summary: {
                      equivalent_class: null,
                      quality_overall: 'excellent',
                      errors_count: 0,
                      revise_issues: {},
                    },
                    revise_passwords: [
                      {
                        revision_number: 1,
                        password: 'd94b6ee2af48',
                      },
                    ],
                    urls: {
                      password: 'a9cdacccd095',
                      translate_url:
                        'https://www.matecat.com/translate/test/fr-CH-de-DE/3882183-a9cdacccd095',
                      revise_urls: [
                        {
                          revision_number: 1,
                          url: 'https://www.matecat.com/revise/test/fr-CH-de-DE/3882183-d94b6ee2af48',
                        },
                      ],
                      original_download_url:
                        'https://www.matecat.com/?action=downloadOriginal&id_job=3882183&password=a9cdacccd095&download_type=all&filename=6068860',
                      translation_download_url:
                        'https://www.matecat.com/?action=downloadFile&id_job=3882183&id_file=&password=a9cdacccd095&download_type=all',
                      xliff_download_url:
                        'https://www.matecat.com/SDLXLIFF/3882183/a9cdacccd095/3882183.zip',
                    },
                  },
                ],
                features:
                  'translated,mmt,translation_versions,review_extended,second_pass_review',
                is_cancelled: false,
                is_archived: false,
                remote_file_service: null,
                due_date: null,
                project_info: null,
              },
            }),
          )
        },
      ),
      rest.post('*/', (req, res, ctx) => {
        const queryParams = req.url.searchParams
        const action = queryParams.get('action')

        if (action != 'getVolumeAnalysis') {
          throw new Error('msw :: branch not mocked, yet.')
        }

        return res(
          ctx.status(200),
          ctx.json({
            errors: [],
            data: {
              jobs: {
                3882183: {
                  chunks: {
                    a9cdacccd095: {
                      6068860: {
                        TOTAL_PAYABLE: [0, '0'],
                        REPETITIONS: [0, '0'],
                        MT: [0, '0'],
                        NEW: [0, '0'],
                        TM_100: [0, '0'],
                        TM_100_PUBLIC: [0, '0'],
                        TM_75_99: [0, '0'],
                        TM_75_84: [0, '0'],
                        TM_85_94: [0, '0'],
                        TM_95_99: [0, '0'],
                        TM_50_74: [0, '0'],
                        INTERNAL_MATCHES: [0, '0'],
                        ICE: [413, '413'],
                        NUMBERS_ONLY: [0, '0'],
                        FILENAME: 'ProtonMail Static Website_french (1).xliff',
                      },
                    },
                  },
                  totals: {
                    a9cdacccd095: {
                      TOTAL_PAYABLE: [0, '0'],
                      REPETITIONS: [0, '0'],
                      MT: [0, '0'],
                      NEW: [0, '0'],
                      TM_100: [0, '0'],
                      TM_100_PUBLIC: [0, '0'],
                      TM_75_99: [0, '0'],
                      TM_75_84: [0, '0'],
                      TM_85_94: [0, '0'],
                      TM_95_99: [0, '0'],
                      TM_50_74: [0, '0'],
                      INTERNAL_MATCHES: [0, '0'],
                      ICE: [413, '413'],
                      NUMBERS_ONLY: [0, '0'],
                      eq_word_count: [0, '0'],
                      standard_word_count: [0, '0'],
                      raw_word_count: [413, '413'],
                    },
                  },
                },
              },
              summary: {
                IN_QUEUE_BEFORE: null,
                IN_QUEUE_BEFORE_PRINT: '0',
                STATUS: 'DONE',
                TOTAL_SEGMENTS: 42,
                SEGMENTS_ANALYZED: 42,
                TOTAL_SEGMENTS_PRINT: '42',
                SEGMENTS_ANALYZED_PRINT: '42',
                TOTAL_FAST_WC: 0,
                TOTAL_TM_WC: 0,
                TOTAL_FAST_WC_PRINT: '0',
                TOTAL_STANDARD_WC: 0,
                TOTAL_STANDARD_WC_PRINT: '0',
                TOTAL_TM_WC_PRINT: '0',
                STANDARD_WC_TIME: '0',
                FAST_WC_TIME: '0',
                TM_WC_TIME: '0',
                STANDARD_WC_UNIT: 'day',
                TM_WC_UNIT: 'day',
                FAST_WC_UNIT: 'day',
                USAGE_FEE: '0.00',
                PRICE_PER_WORD: '0.030',
                DISCOUNT: '0',
                NAME: 'test',
                TOTAL_RAW_WC: 413,
                TOTAL_PAYABLE: 0,
                PAYABLE_WC_TIME: '0',
                PAYABLE_WC_UNIT: 'day',
                DISCOUNT_WC: '0',
                TOTAL_RAW_WC_PRINT: '413',
                TOTAL_PAYABLE_PRINT: '0',
              },
            },
          }),
        )
      }),
    ],
  )

  global.config = {
    basepath: 'fake_basepath/',
    status: 'fake_project_status',
    isLoggedIn: false,
    id_project: 'project_id_123',
    password: 'job_password_123',
    ajaxDomainsNumber: 3000,
  }

  require('./common')
  require('./user_store')
  require('./login')
  require('./cat_source/es6/ajax_utils/projectsAjax')

  const elHeader = document.createElement('header')
  const elAnalyzeContainer = document.createElement('div')
  elAnalyzeContainer.id = 'analyze-container'

  const elModal = document.createElement('div')
  elModal.id = 'modal'

  document.body.appendChild(elHeader)
  document.body.appendChild(elAnalyzeContainer)
  document.body.appendChild(elModal)

  await import('./analyze')

  UI.init()

  const elLoadingText = screen.getByText('Loading Volume Analysis')
  expect(elLoadingText).toBeVisible()

  await waitForElementToBeRemoved(() =>
    screen.getByText('Loading Volume Analysis'),
  )

  expect(screen.getByRole('heading', {name: 'Volume Analysis'})).toBeVisible()
  expect(
    screen.getByRole('heading', {name: /Saving on word count/}),
  ).toBeVisible()

  expect(screen.getByRole('heading', {name: 'Show Details'})).toBeVisible()

  unmountComponentAtNode(elHeader)
  unmountComponentAtNode(elAnalyzeContainer)
  unmountComponentAtNode(elModal)
})
