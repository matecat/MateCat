import {render, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import Immutable from 'immutable'
import {rest} from 'msw'

import FilterProjects from './FilterProjects'
import ProjectsStore from '../../../stores/ProjectsStore'
import ManageConstants from '../../../constants/ManageConstants'
import ManageActions from '../../../actions/ManageActions'
import {getProjects} from '../../../api/getProjects'
import {mswServer} from '../../../../../../mocks/mswServer'

window.config = {
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 3,
  basepath: '/',
}

const fakeFilterData = {
  teamWithId_1: {
    data: {
      id: 1,
      name: 'Personal',
      type: 'personal',
      created_at: '2021-06-23T12:51:48+02:00',
      created_by: 1,
      members: [
        {
          id: 1,
          id_team: 1,
          user: {
            uid: 1,
            first_name: 'Pierluigi',
            last_name: 'Di Cianni',
            email: 'pierluigi.dicianni@translated.net',
            has_password: false,
          },
          user_metadata: {
            gplus_picture:
              'https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c',
          },
          projects: 14,
        },
      ],
      pending_invitations: [],
    },
  },
}

const getFakeProperties = (fakeProperties) => {
  const {data} = fakeProperties
  const team = Immutable.fromJS(data)

  return {
    team,
    props: {
      team,
    },
  }
}

const apiGetProjects = {
  noResult: {
    errors: [],
    data: [],
    page: 1,
    pnumber: '0',
    pageStep: 10,
  },
  result: {
    errors: [],
    data: [
      {
        id: 6,
        password: '59ad778c68b1',
        name: 'tesla.docx',
        id_team: 1,
        id_assignee: 1,
        create_date: '2021-06-23 14:27:08',
        fast_analysis_wc: 374,
        standard_analysis_wc: 357,
        tm_analysis_wc: '306.40',
        project_slug: 'tesladocx',
        jobs: [
          {
            id: 6,
            password: '2a35d508882e',
            source: 'en-US',
            target: 'it-IT',
            sourceTxt: 'English US',
            targetTxt: 'Italian',
            job_first_segment: '1',
            status: 'active',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1624451228,
            created_at: '2021-06-23T14:27:08+02:00',
            create_date: '2021-06-23 14:27:08',
            formatted_create_date: 'Jun 23, 14:27',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [],
            warnings_count: 3,
            warning_segments: [1, 3, 5],
            stats: {
              id: 6,
              DRAFT: 0,
              TRANSLATED: 84.2,
              APPROVED: 72,
              REJECTED: 0,
              TOTAL: 156.2,
              PROGRESS: 156.2,
              TOTAL_FORMATTED: '156',
              PROGRESS_FORMATTED: '156',
              APPROVED_FORMATTED: '72',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '0',
              TRANSLATED_FORMATTED: '84',
              APPROVED_PERC: 46.094750320102,
              REJECTED_PERC: 0,
              DRAFT_PERC: 0,
              TRANSLATED_PERC: 53.905249679898,
              PROGRESS_PERC: 100,
              TRANSLATED_PERC_FORMATTED: 53.91,
              DRAFT_PERC_FORMATTED: 0,
              APPROVED_PERC_FORMATTED: 46.09,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 100,
              TODO_FORMATTED: '0',
              TODO: 0,
              DOWNLOAD_STATUS: 'translated',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 72,
                },
                {
                  revision_number: 2,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 213,
            standard_wc: 179,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 3,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: '9084da7a0d31',
              },
              {
                revision_number: 2,
                password: '259b6eb9e62f',
              },
            ],
            urls: {
              password: '2a35d508882e',
              translate_url:
                'https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-2a35d508882e',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-9084da7a0d31',
                },
                {
                  revision_number: 2,
                  url: 'https://dev.matecat.com/revise2/tesla.docx/en-US-it-IT/6-259b6eb9e62f',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/6/2a35d508882e/6.zip',
            },
          },
          {
            id: 6,
            password: '307be438d286',
            source: 'en-US',
            target: 'it-IT',
            sourceTxt: 'English US',
            targetTxt: 'Italian',
            job_first_segment: '9',
            status: 'active',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 2,
            create_timestamp: 1625150353,
            created_at: '2021-07-01T16:39:13+02:00',
            create_date: '2021-07-01 16:39:13',
            formatted_create_date: 'Jul 01, 16:39',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [],
            warnings_count: 2,
            warning_segments: [11, 12],
            stats: {
              id: 6,
              DRAFT: 0,
              TRANSLATED: 150.2,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 150.2,
              PROGRESS: 150.2,
              TOTAL_FORMATTED: '150',
              PROGRESS_FORMATTED: '150',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '0',
              TRANSLATED_FORMATTED: '150',
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
            translator: null,
            total_raw_wc: 213,
            standard_wc: 179,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 2,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: 'e7ffa4998c82',
              },
            ],
            urls: {
              password: '307be438d286',
              translate_url:
                'https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-307be438d286',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-e7ffa4998c82',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/6/2a35d508882e/6.zip',
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
    ],
    page: 1,
    pnumber: '1',
    pageStep: 10,
  },
  archived: {
    errors: [],
    data: [
      {
        id: 9,
        password: '59b94d64a7ef',
        name: 'Test',
        id_team: 1,
        id_assignee: 1,
        create_date: '2021-07-02 10:59:28',
        fast_analysis_wc: 374,
        standard_analysis_wc: 1704,
        tm_analysis_wc: '1427.09',
        project_slug: 'test',
        jobs: [
          {
            id: 90,
            password: 'NWUxMWYjEwZT600',
            source: 'en-US',
            target: 'la-XN',
            sourceTxt: 'English US',
            targetTxt: 'Latin',
            job_first_segment: '58',
            status: 'archived',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1625216368,
            created_at: '2021-07-02T10:59:28+02:00',
            create_date: '2021-07-02 10:59:28',
            formatted_create_date: 'Jul 02, 10:59',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: 'c52da4a03d6aea33f242',
                r: 1,
                w: 1,
                name: 'Test',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 90,
              DRAFT: 362.1,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 362.1,
              PROGRESS: 0,
              TOTAL_FORMATTED: '362',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '362',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '362',
              TODO: 362,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 426,
            standard_wc: 426,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: 'a192d66ec1f5',
              },
            ],
            urls: {
              password: 'NWUxMWYjEwZT600',
              translate_url:
                'https://dev.matecat.com/translate/Test/en-US-la-XN/90-NWUxMWYjEwZT600',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=NWUxMWYjEwZT600&download_type=all&filename=4',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=NWUxMWYjEwZT600&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/90/NWUxMWYjEwZT600/90.zip',
            },
          },
          {
            id: 91,
            password: 'ce560196ca5c',
            source: 'en-US',
            target: 'es-ES',
            sourceTxt: 'English US',
            targetTxt: 'Spanish',
            job_first_segment: '58',
            status: 'archived',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1625216368,
            created_at: '2021-07-02T10:59:28+02:00',
            create_date: '2021-07-02 10:59:28',
            formatted_create_date: 'Jul 02, 10:59',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: 'c52da4a03d6aea33f242',
                r: 1,
                w: 1,
                name: 'Test',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 91,
              DRAFT: 340.8,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 340.8,
              PROGRESS: 0,
              TOTAL_FORMATTED: '341',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '341',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '341',
              TODO: 341,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 426,
            standard_wc: 426,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: '1c0eb403b087',
              },
            ],
            urls: {
              password: 'ce560196ca5c',
              translate_url:
                'https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip',
            },
          },
          {
            id: 92,
            password: '25c9442ad64c',
            source: 'en-US',
            target: 'en-GB',
            sourceTxt: 'English US',
            targetTxt: 'English',
            job_first_segment: '58',
            status: 'archived',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1625216368,
            created_at: '2021-07-02T10:59:28+02:00',
            create_date: '2021-07-02 10:59:28',
            formatted_create_date: 'Jul 02, 10:59',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: 'c52da4a03d6aea33f242',
                r: 1,
                w: 1,
                name: 'Test',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 92,
              DRAFT: 362.1,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 362.1,
              PROGRESS: 0,
              TOTAL_FORMATTED: '362',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '362',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '362',
              TODO: 362,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 426,
            standard_wc: 426,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: '3f0a9e425baf',
              },
            ],
            urls: {
              password: '25c9442ad64c',
              translate_url:
                'https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip',
            },
          },
          {
            id: 93,
            password: '667611949406',
            source: 'en-US',
            target: 'mt-MT',
            sourceTxt: 'English US',
            targetTxt: 'Maltese',
            job_first_segment: '58',
            status: 'archived',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1625216368,
            created_at: '2021-07-02T10:59:28+02:00',
            create_date: '2021-07-02 10:59:28',
            formatted_create_date: 'Jul 02, 10:59',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: 'c52da4a03d6aea33f242',
                r: 1,
                w: 1,
                name: 'Test',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 93,
              DRAFT: 362.1,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 362.1,
              PROGRESS: 0,
              TOTAL_FORMATTED: '362',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '362',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '362',
              TODO: 362,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 426,
            standard_wc: 426,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: 'be016cc3fd85',
              },
            ],
            urls: {
              password: '667611949406',
              translate_url:
                'https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip',
            },
          },
        ],
        features:
          'translated,mmt,translation_versions,review_extended,second_pass_review',
        is_cancelled: false,
        is_archived: true,
        remote_file_service: null,
        due_date: null,
        project_info: null,
      },
    ],
    page: 1,
    pnumber: '1',
    pageStep: 10,
  },
  cancelled: {
    errors: [],
    data: [
      {
        id: 16,
        password: '8b7b186e9931',
        name: 'TestXLIFF',
        id_team: 1,
        id_assignee: 1,
        create_date: '2021-07-14 16:17:57',
        fast_analysis_wc: 4,
        standard_analysis_wc: 8,
        tm_analysis_wc: '6.60',
        project_slug: 'testxliff',
        jobs: [
          {
            id: 108,
            password: 'f03b800ea879',
            source: 'en-US',
            target: 'es-ES',
            sourceTxt: 'English US',
            targetTxt: 'Spanish',
            job_first_segment: '155',
            status: 'cancelled',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1626272277,
            created_at: '2021-07-14T16:17:57+02:00',
            create_date: '2021-07-14 16:17:57',
            formatted_create_date: 'Jul 14, 16:17',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: '***************454c9',
                r: true,
                w: true,
                name: 'TestTM',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 108,
              DRAFT: 3.2,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 3.2,
              PROGRESS: 0,
              TOTAL_FORMATTED: '3',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '3',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '3',
              TODO: 3,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 4,
            standard_wc: 4,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: 'bc514c2212e4',
              },
            ],
            urls: {
              password: 'f03b800ea879',
              translate_url:
                'https://dev.matecat.com/translate/TestXLIFF/en-US-es-ES/108-f03b800ea879',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/TestXLIFF/en-US-es-ES/108-bc514c2212e4',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=108&password=f03b800ea879&download_type=all&filename=11',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=108&id_file=&password=f03b800ea879&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/108/f03b800ea879/108.zip',
            },
          },
          {
            id: 109,
            password: '3d3086540f6e',
            source: 'en-US',
            target: 'en-GB',
            sourceTxt: 'English US',
            targetTxt: 'English',
            job_first_segment: '155',
            status: 'cancelled',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1626272277,
            created_at: '2021-07-14T16:17:57+02:00',
            create_date: '2021-07-14 16:17:57',
            formatted_create_date: 'Jul 14, 16:17',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: '***************454c9',
                r: true,
                w: true,
                name: 'TestTM',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 109,
              DRAFT: 3.4,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 3.4,
              PROGRESS: 0,
              TOTAL_FORMATTED: '3',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '3',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '3',
              TODO: 3,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 4,
            standard_wc: 4,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: 'c2b928f3c6eb',
              },
            ],
            urls: {
              password: '3d3086540f6e',
              translate_url:
                'https://dev.matecat.com/translate/TestXLIFF/en-US-en-GB/109-3d3086540f6e',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/TestXLIFF/en-US-en-GB/109-c2b928f3c6eb',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=109&password=3d3086540f6e&download_type=all&filename=11',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=109&id_file=&password=3d3086540f6e&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/109/3d3086540f6e/109.zip',
            },
          },
        ],
        features:
          'translated,mmt,translation_versions,review_extended,second_pass_review',
        is_cancelled: true,
        is_archived: false,
        remote_file_service: null,
        due_date: null,
        project_info: null,
      },
      {
        id: 13,
        password: '756e431a0a30',
        name: 'Trad2',
        id_team: 1,
        id_assignee: 1,
        create_date: '2021-07-14 11:43:58',
        fast_analysis_wc: 374,
        standard_analysis_wc: 852,
        tm_analysis_wc: '702.90',
        project_slug: 'trad2',
        jobs: [
          {
            id: 104,
            password: '8114722e28eb',
            source: 'en-US',
            target: 'es-ES',
            sourceTxt: 'English US',
            targetTxt: 'Spanish',
            job_first_segment: '134',
            status: 'cancelled',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1626255838,
            created_at: '2021-07-14T11:43:58+02:00',
            create_date: '2021-07-14 11:43:58',
            formatted_create_date: 'Jul 14, 11:43',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: 'c52da4a03d6aea33f242',
                r: 1,
                w: 1,
                name: 'Test',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 104,
              DRAFT: 340.8,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 340.8,
              PROGRESS: 0,
              TOTAL_FORMATTED: '341',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '341',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '341',
              TODO: 341,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 426,
            standard_wc: 426,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: '3e24ee51f218',
              },
            ],
            urls: {
              password: '8114722e28eb',
              translate_url:
                'https://dev.matecat.com/translate/Trad2/en-US-es-ES/104-8114722e28eb',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/Trad2/en-US-es-ES/104-3e24ee51f218',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=104&password=8114722e28eb&download_type=all&filename=8',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=104&id_file=&password=8114722e28eb&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/104/8114722e28eb/104.zip',
            },
          },
          {
            id: 105,
            password: 'd3d64e864c1e',
            source: 'en-US',
            target: 'en-GB',
            sourceTxt: 'English US',
            targetTxt: 'English',
            job_first_segment: '134',
            status: 'cancelled',
            subject: 'general',
            subject_printable: 'General',
            owner: 'pierluigi.dicianni@translated.net',
            open_threads_count: 0,
            create_timestamp: 1626255838,
            created_at: '2021-07-14T11:43:58+02:00',
            create_date: '2021-07-14 11:43:58',
            formatted_create_date: 'Jul 14, 11:43',
            quality_overall: 'excellent',
            pee: 0,
            tte: 0,
            private_tm_key: [
              {
                key: 'c52da4a03d6aea33f242',
                r: 1,
                w: 1,
                name: 'Test',
              },
            ],
            warnings_count: 0,
            warning_segments: [],
            stats: {
              id: 105,
              DRAFT: 362.1,
              TRANSLATED: 0,
              APPROVED: 0,
              REJECTED: 0,
              TOTAL: 362.1,
              PROGRESS: 0,
              TOTAL_FORMATTED: '362',
              PROGRESS_FORMATTED: '0',
              APPROVED_FORMATTED: '0',
              REJECTED_FORMATTED: '0',
              DRAFT_FORMATTED: '362',
              TRANSLATED_FORMATTED: '0',
              APPROVED_PERC: 0,
              REJECTED_PERC: 0,
              DRAFT_PERC: 100,
              TRANSLATED_PERC: 0,
              PROGRESS_PERC: 0,
              TRANSLATED_PERC_FORMATTED: 0,
              DRAFT_PERC_FORMATTED: 100,
              APPROVED_PERC_FORMATTED: 0,
              REJECTED_PERC_FORMATTED: 0,
              PROGRESS_PERC_FORMATTED: 0,
              TODO_FORMATTED: '362',
              TODO: 362,
              DOWNLOAD_STATUS: 'draft',
              revises: [
                {
                  revision_number: 1,
                  advancement_wc: 0,
                },
              ],
            },
            outsource: null,
            translator: null,
            total_raw_wc: 426,
            standard_wc: 426,
            quality_summary: {
              equivalent_class: null,
              quality_overall: 'excellent',
              errors_count: 0,
              revise_issues: {},
            },
            revise_passwords: [
              {
                revision_number: 1,
                password: '979f38d0c6a8',
              },
            ],
            urls: {
              password: 'd3d64e864c1e',
              translate_url:
                'https://dev.matecat.com/translate/Trad2/en-US-en-GB/105-d3d64e864c1e',
              revise_urls: [
                {
                  revision_number: 1,
                  url: 'https://dev.matecat.com/revise/Trad2/en-US-en-GB/105-979f38d0c6a8',
                },
              ],
              original_download_url:
                'https://dev.matecat.com/?action=downloadOriginal&id_job=105&password=d3d64e864c1e&download_type=all&filename=8',
              translation_download_url:
                'https://dev.matecat.com/?action=downloadFile&id_job=105&id_file=&password=d3d64e864c1e&download_type=all',
              xliff_download_url:
                'https://dev.matecat.com/SDLXLIFF/105/d3d64e864c1e/105.zip',
            },
          },
        ],
        features:
          'translated,mmt,translation_versions,review_extended,second_pass_review',
        is_cancelled: true,
        is_archived: false,
        remote_file_service: null,
        due_date: null,
        project_info: null,
      },
    ],
    page: 1,
    pnumber: '2',
    pageStep: 10,
  },
}

const executeMswServer = (response) => {
  mswServer.use(
    ...[
      rest.post('/', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json(response))
      }),
    ],
  )
}

const getProjectsRequest = ({searchTerm = '', status = 'active'}) => {
  const filterOptions = {
    filter: {pn: searchTerm, status: status},
    currentPage: 1,
  }

  getProjects({
    team: fakeFilterData.teamWithId_1,
    searchFilter: filterOptions,
  }).then((res) => {
    const projects = res.data

    ManageActions.renderProjects(
      projects,
      fakeFilterData.teamWithId_1,
      [],
      false,
      true,
    )
  })
}

const projectsListPromise = () => {
  return new Promise((resolve) => {
    const callback = (projects) => {
      ProjectsStore.removeListener(ManageConstants.RENDER_PROJECTS, callback)
      resolve(projects)
    }
    ProjectsStore.addListener(ManageConstants.RENDER_PROJECTS, callback)
  })
}

const addOnceListenerStoreFilterProjects = (() => {
  let callbackFn
  const add = (callback) =>
    ProjectsStore.addListener(ManageConstants.FILTER_PROJECTS, callback)
  const remove = (callback) =>
    ProjectsStore.removeListener(ManageConstants.FILTER_PROJECTS, callback)

  return (callback) => {
    if (callbackFn) remove(callbackFn)
    add(callback)
    callbackFn = callback
  }
})()

test('Rendering elements', () => {
  const {props} = getFakeProperties(fakeFilterData.teamWithId_1)
  act(() => {
    render(<FilterProjects {...props} />)
  })
  expect(screen.getByTestId('input-search-projects')).toBeInTheDocument()
  expect(screen.getByTestId('status-filter')).toBeInTheDocument()
})

test('Searching with no result', async () => {
  executeMswServer(apiGetProjects.noResult)

  const {props} = getFakeProperties(fakeFilterData.teamWithId_1)
  act(() => {
    render(<FilterProjects {...props} />)
  })
  const searchTerm = 'my project'

  addOnceListenerStoreFilterProjects(() => getProjectsRequest({searchTerm}))

  const input = screen.getByTestId('input-search-projects')
  act(() => {
    userEvent.type(input, searchTerm)
  })
  const projects = await projectsListPromise()

  expect(projects.size).toBe(0)
})

test('Searching result', async () => {
  executeMswServer(apiGetProjects.result)

  const {props} = getFakeProperties(fakeFilterData.teamWithId_1)
  act(() => {
    render(<FilterProjects {...props} />)
  })
  const searchTerm = 'tesla'

  addOnceListenerStoreFilterProjects(() => getProjectsRequest({searchTerm}))

  const input = screen.getByTestId('input-search-projects')
  act(() => {
    userEvent.type(input, searchTerm)
  })
  const projects = await projectsListPromise()

  expect(projects.size).toBe(1)
  expect(projects.first().get('name')).toBe('tesla.docx')
})

test('Click on archived status', async () => {
  executeMswServer(apiGetProjects.archived)

  const {props} = getFakeProperties(fakeFilterData.teamWithId_1)
  act(() => {
    render(<FilterProjects {...props} />)
  })
  const status = 'archived'
  addOnceListenerStoreFilterProjects(() => getProjectsRequest({status}))
  act(() => {
    userEvent.click(screen.getByTestId('item-archived'))
  })
  const projects = await projectsListPromise()

  expect(projects.size).toBe(1)
  expect(projects.first().get('name')).toBe('Test')
  expect(projects.first().get('is_archived')).toBeTruthy()
})

test('Click on cancelled status', async () => {
  executeMswServer(apiGetProjects.cancelled)

  const {props} = getFakeProperties(fakeFilterData.teamWithId_1)
  act(() => {
    render(<FilterProjects {...props} />)
  })
  const status = 'cancelled'
  addOnceListenerStoreFilterProjects(() => getProjectsRequest({status}))
  act(() => {
    userEvent.click(screen.getByTestId('item-archived'))
  })
  const projects = await projectsListPromise()

  expect(projects.size).toBe(2)
  expect(projects.first().get('name')).toBe('TestXLIFF')
  expect(projects.first().get('is_cancelled')).toBeTruthy()
})
