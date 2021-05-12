import {screen, waitFor} from '@testing-library/react'
import {rest} from 'msw'

import {mswServer} from '../../../../../mocks/mswServer'

test('renders properly', async () => {
  mswServer.use(
    rest.get(
      '*/app/jobs/:job_id/:password/quality-report/segments',
      (req, res, ctx) => {
        return res(ctx.status(500))
      },
    ),
    rest.get('*api/v3/jobs/:job_id/:password', (req, res, ctx) => {
      return res(
        ctx.status(200),
        ctx.json({
          id: 3963209,
          chunks: [
            {
              id: 3963209,
              password: '0e8275ef5870',
              source: 'it-IT',
              target: 'en-GB',
              sourceTxt: 'Italian',
              targetTxt: 'English',
              status: 'active',
              subject: 'general',
              subject_printable: 'General',
              owner: 'luca.barone@translated.net',
              time_to_edit: {
                total: 0,
                t: 0,
                r1: 0,
                r2: 0,
              },
              total_time_to_edit: 0,
              avg_post_editing_effort: 0,
              open_threads_count: 0,
              created_at: '2021-05-05T10:11:03+02:00',
              pee: 0,
              private_tm_key: [],
              warnings_count: 0,
              warning_segments: [],
              stats: {
                draft: 8981.5,
                translated: 0,
                approved: 0,
                rejected: 0,
                total: 8981.5,
                revises: [
                  {
                    revision_number: 1,
                    advancement_wc: 0,
                  },
                ],
              },
              outsource: null,
              translator: null,
              total_raw_wc: 18573,
              standard_wc: 10333,
              quality_summary: [
                {
                  revision_number: 1,
                  feedback: null,
                  model_version: 3649590292,
                  equivalent_class: null,
                  is_pass: true,
                  quality_overall: 'excellent',
                  errors_count: 0,
                  revise_issues: [],
                  score: 0,
                  categories: [
                    {
                      label: 'Style (readability, consistent style and tone)',
                      id: 8821835,
                      severities: [
                        {
                          label: 'Neutral',
                          dqf_id: 0,
                          penalty: 0,
                        },
                        {
                          label: 'Minor',
                          dqf_id: 2,
                          penalty: 0.5,
                        },
                        {
                          label: 'Major',
                          dqf_id: 2,
                          penalty: 2,
                        },
                      ],
                      options: {
                        code: 'STY',
                        dqf_id: 4,
                      },
                      subcategories: [],
                    },
                    {
                      label: 'Tag issues (mismatches, whitespaces)',
                      id: 8821836,
                      severities: [
                        {
                          label: 'Neutral',
                          dqf_id: 0,
                          penalty: 0,
                        },
                        {
                          label: 'Minor',
                          dqf_id: 2,
                          penalty: 0.5,
                        },
                        {
                          label: 'Major',
                          dqf_id: 2,
                          penalty: 2,
                        },
                      ],
                      options: {
                        code: 'TAG',
                        dqf_id: 5,
                      },
                      subcategories: [],
                    },
                    {
                      label:
                        'Translation errors (mistranslation, additions or omissions)',
                      id: 8821837,
                      severities: [
                        {
                          label: 'Neutral',
                          dqf_id: 0,
                          penalty: 0,
                        },
                        {
                          label: 'Minor',
                          dqf_id: 2,
                          penalty: 0.5,
                        },
                        {
                          label: 'Major',
                          dqf_id: 2,
                          penalty: 2,
                        },
                      ],
                      options: {
                        code: 'TER',
                        dqf_id: 1,
                      },
                      subcategories: [],
                    },
                    {
                      label: 'Terminology and translation consistency',
                      id: 8821838,
                      severities: [
                        {
                          label: 'Neutral',
                          dqf_id: 0,
                          penalty: 0,
                        },
                        {
                          label: 'Minor',
                          dqf_id: 2,
                          penalty: 0.5,
                        },
                        {
                          label: 'Major',
                          dqf_id: 2,
                          penalty: 2,
                        },
                      ],
                      options: {
                        code: 'TRM',
                        dqf_id: 3,
                      },
                      subcategories: [],
                    },
                    {
                      label:
                        'Language quality (grammar, punctuation, spelling)',
                      id: 8821839,
                      severities: [
                        {
                          label: 'Neutral',
                          dqf_id: 0,
                          penalty: 0,
                        },
                        {
                          label: 'Minor',
                          dqf_id: 2,
                          penalty: 0.5,
                        },
                        {
                          label: 'Major',
                          dqf_id: 2,
                          penalty: 2,
                        },
                      ],
                      options: {
                        code: 'LQ',
                        dqf_id: 2,
                      },
                      subcategories: [],
                    },
                  ],
                  total_issues_weight: 0,
                  total_reviewed_words_count: 0,
                  passfail: {
                    type: 'points_per_thousand',
                    options: {
                      limit: 20,
                    },
                  },
                  total_time_to_edit: 0,
                },
              ],
              revise_passwords: [
                {
                  revision_number: 1,
                  password: 'ba30b7f1e459',
                },
              ],
              urls: {
                password: '0e8275ef5870',
                translate_url:
                  'https://www.matecat.com/translate/offer-requests_5.json/it-IT-en-GB/3963209-0e8275ef5870',
                revise_urls: [
                  {
                    revision_number: 1,
                    url:
                      'https://www.matecat.com/revise/offer-requests_5.json/it-IT-en-GB/3963209-ba30b7f1e459',
                  },
                ],
                original_download_url:
                  'https://www.matecat.com/?action=downloadOriginal&id_job=3963209&password=0e8275ef5870&download_type=all&filename=6325962',
                translation_download_url:
                  'https://www.matecat.com/?action=downloadFile&id_job=3963209&id_file=&password=0e8275ef5870&download_type=all',
                xliff_download_url:
                  'https://www.matecat.com/SDLXLIFF/3963209/0e8275ef5870/3963209.zip',
              },
            },
          ],
        }),
      )
    }),
  )

  global.config = {
    id_job: 123,
    password: 'fake-password',
  }

  require('../../../../common')
  require('../../../../login')
  require('../../react-libs')
  require('../../components')

  {
    const header = document.createElement('header')

    const content = document.createElement('div')
    content.id = 'qr-root'

    const modal = document.createElement('div')
    modal.id = 'modal'

    document.body.appendChild(header)
    document.body.appendChild(content)
    document.body.appendChild(modal)
  }

  await import('./QualityReport')

  expect(screen.getByText('Loading')).toBeVisible()

  await waitFor(
    () => {
      expect(screen.getByText('QR Job summary')).toBeVisible()
    },
    {timeout: 5000},
  )
})
