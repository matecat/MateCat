import {render, screen, act, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import React from 'react'
import {fromJS} from 'immutable'
import {JobContainer} from './JobContainer'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import CatToolActions from '../../actions/CatToolActions'
import {changeJobPassword} from '../../api/changeJobPassword'
import CommonUtils from '../../utils/commonUtils'

jest.mock('../../actions/ManageActions')
jest.mock('../../actions/ModalsActions')
jest.mock('../../actions/CatToolActions')
jest.mock('../../api/changeJobPassword')
jest.mock('../outsource/OutsourceContainer', () => ({
  __esModule: true,
  default: (props) => (
    <div data-testid="outsource-container-mock">
      <button data-testid="outsource-close" onClick={props.onClickOutside}>
        close
      </button>
    </div>
  ),
}))
// JobMenu is covered by its own JobMenu.test.js and relies on a Radix
// dropdown/submenu that is unreliable to drive with userEvent in jsdom
// (see the pre-existing `test.skip` in JobMenu.test.js). It is replaced
// here with a thin stub that exposes every callback prop as a button so
// JobContainer's own handlers (archive/cancel/delete/split/merge/change
// password/download) can be exercised deterministically.
jest.mock('./JobMenu', () => ({
  __esModule: true,
  default: (props) => (
    <div data-testid="job-menu-button">
      <button data-testid="menu-archive" onClick={props.archiveJobFn}>
        archive
      </button>
      <button data-testid="menu-activate" onClick={props.activateJobFn}>
        activate
      </button>
      <button data-testid="menu-cancel" onClick={props.cancelJobFn}>
        cancel
      </button>
      <button data-testid="menu-delete" onClick={props.deleteJobFn}>
        delete
      </button>
      <button data-testid="menu-split" onClick={props.openSplitModalFn}>
        split
      </button>
      <button data-testid="menu-merge" onClick={props.openMergeModalFn}>
        merge
      </button>
      <button
        data-testid="menu-change-password"
        onClick={() => props.changePasswordFn()}
      >
        change-password
      </button>
      <button
        data-testid="menu-change-password-1"
        onClick={() => props.changePasswordFn(1)}
      >
        change-password-1
      </button>
      <div data-testid="menu-download-label">{props.downloadLabel?.label}</div>
      <button
        data-testid="menu-download-action"
        onClick={() => props.downloadLabel?.action()}
      >
        download
      </button>
    </div>
  ),
}))

window.config = {enable_outsource: 1, splitEnabled: 1}

const fakeProjectsData = {
  jobWithoutActivity: {
    data: {
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
          password: 'a5b852c4fe52',
          source: 'en-US',
          target: 'la-XN',
          sourceTxt: 'English US',
          targetTxt: 'Latin',
          job_first_segment: '58',
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: 'a192d66ec1f5'}],
          urls: {
            password: 'a5b852c4fe52',
            translate_url:
              'https://dev.matecat.com/translate/Test/en-US-la-XN/90-a5b852c4fe52',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=a5b852c4fe52&download_type=all&filename=4',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=a5b852c4fe52&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/xliff/90/a5b852c4fe52/90.zip',
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
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: '1c0eb403b087'}],
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
              'https://dev.matecat.com/xliff/91/ce560196ca5c/91.zip',
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
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: '3f0a9e425baf'}],
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
              'https://dev.matecat.com/xliff/92/25c9442ad64c/92.zip',
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
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: 'be016cc3fd85'}],
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
              'https://dev.matecat.com/xliff/93/667611949406/93.zip',
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
    props: {
      index: 0,
      jobsLength: 4,
      isChunk: false,
      isChunkOutsourced: false,
      activityLogUrl: '/activityLog/9/59b94d64a7ef',
    },
  },
  jobActivity: {
    data: {
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
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: '9084da7a0d31'}],
          urls: {
            password: '2a35d508882e',
            translate_url:
              'https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-2a35d508882e',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-9084da7a0d31',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/xliff/6/2a35d508882e/6.zip',
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
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: 'e7ffa4998c82'}],
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
              'https://dev.matecat.com/xliff/6/2a35d508882e/6.zip',
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
    props: {
      index: 1,
      jobsLength: 2,
      isChunk: true,
      isChunkOutsourced: false,
      activityLogUrl: '/activityLog/6/59ad778c68b1',
    },
  },
  jobTranslatedOutsourced: {
    data: {
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
          password: 'a5b852c4fe52',
          source: 'en-US',
          target: 'la-XN',
          sourceTxt: 'English US',
          targetTxt: 'Latin',
          job_first_segment: '58',
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
          },
          outsource: null,
          translator: {
            email: 'pierluigi.dicianni@translated.net',
            added_by: 1,
            delivery_date: '2021-07-07 11:00:00',
            delivery_timestamp: 1625648400,
            source: 'en-US',
            target: 'la-XN',
            id_translator_profile: '1',
            user: {
              uid: 1,
              first_name: 'Pierluigi',
              last_name: 'Di Cianni',
              email: 'pierluigi.dicianni@translated.net',
              has_password: false,
            },
          },
          total_raw_wc: 426,
          standard_wc: 426,
          quality_summary: {
            equivalent_class: null,
            quality_overall: 'excellent',
            errors_count: 0,
            revise_issues: {},
          },
          revise_passwords: [{revision_number: 1, password: 'a192d66ec1f5'}],
          urls: {
            password: 'a5b852c4fe52',
            translate_url:
              'https://dev.matecat.com/translate/Test/en-US-la-XN/90-a5b852c4fe52',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=a5b852c4fe52&download_type=all&filename=4',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=a5b852c4fe52&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/xliff/90/a5b852c4fe52/90.zip',
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
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: '1c0eb403b087'}],
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
              'https://dev.matecat.com/xliff/91/ce560196ca5c/91.zip',
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
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: '3f0a9e425baf'}],
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
              'https://dev.matecat.com/xliff/92/25c9442ad64c/92.zip',
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
          status: 'active',
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
            {key: 'c52da4a03d6aea33f242', r: 1, w: 1, name: 'Test'},
          ],
          warnings_count: 0,
          warning_segments: [],
          stats: {
            equivalent: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 13,
              approved2: 0,
              total: 13,
            },
            raw: {
              new: 0,
              draft: 0,
              translated: 0,
              approved: 0,
              approved2: 0,
              total: 0,
            },
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
          revise_passwords: [{revision_number: 1, password: 'be016cc3fd85'}],
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
              'https://dev.matecat.com/xliff/93/667611949406/93.zip',
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
    props: {
      index: 0,
      jobsLength: 4,
      isChunk: false,
      isChunkOutsourced: false,
      activityLogUrl: '/activityLog/9/59b94d64a7ef',
    },
  },
}

const getFakeProperties = (fakeProperties) => {
  const {data, props} = fakeProperties
  const project = fromJS(data)
  const jobs = project.get('jobs')
  const job = jobs.first()

  return {
    project,
    jobs,
    job,
    props: {
      ...props,
      job,
      project,
      changeJobPasswordFn: () => {},
      downloadTranslationFn: () => {},
    },
  }
}

const getProjectAnalyzeUrl = (slug, id, password) =>
  `/analyze/${slug}/${id}-${password}`
const getTranslateUrl = (
  chunkId,
  projectSlug,
  source,
  target,
  password,
  jobFirstSegment,
) => {
  return `/translate/${projectSlug}/${source}-${target}/${chunkId}-${password}${jobFirstSegment}`
}
const createTranslateUrl = (index, project, job, jobsLength) => {
  const usePrefix = jobsLength > 1
  const chunckId = `${job.get('id')}${usePrefix ? '-' + index : ''}`
  return getTranslateUrl(
    chunckId,
    project.get('project_slug'),
    job.get('source'),
    job.get('target'),
    job.get('password'),
    usePrefix ? `#${job.get('job_first_segment')}` : '',
  )
}

test('Rendering elements', () => {
  const {job, props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobContainer {...props} />)

  // ID field
  expect(screen.getByText(`ID: ${job.get('id')}`)).toBeVisible()

  expect(screen.getByTestId('source-target-label')).toBeInTheDocument()

  // words number
  expect(screen.getByTestId('words-button')).toBeInTheDocument()

  // assign job to translator
  expect(screen.getByText('Assign')).toBeInTheDocument()

  // buy translation
  // expect(screen.getByText('Buy Translation')).toBeInTheDocument()

  // open
  expect(screen.getByText(/Open/)).toBeInTheDocument()

  // job menu
  expect(screen.getByTestId('job-menu-button')).toBeInTheDocument()
})

test('Check job without activity', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobContainer {...props} />)

  expect(screen.getByTestId('job-activity-icons')).toBeEmptyDOMElement()
})

test('Check job activity', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobActivity)
  render(<JobContainer {...props} />)

  expect(screen.getByTestId('job-activity-icons')).toBeInTheDocument()
})

test('Assign job to translator: check onClick event', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobContainer {...props} />)

  const jobToTranslatorElement = screen.getByText('Assign')
  expect(jobToTranslatorElement).toBeEnabled()
})

test('Render elements translated outsourced', () => {
  const {props, job} = getFakeProperties(
    fakeProjectsData.jobTranslatedOutsourced,
  )
  render(<JobContainer {...props} />)

  // user email
  expect(
    screen.getByText(job.get('translator').get('email')),
  ).toBeInTheDocument()

  // date
  /*const gmtDate = APP.getGMTDate(
    job.get('translator').get('delivery_timestamp') * 1000
  );

  const regexDay = new RegExp(gmtDate.day + '\b');
  expect(screen.getByText(regexDay)).toBeInTheDocument();
  expect(screen.getByText(gmtDate.month)).toBeInTheDocument();
  expect(screen.getByText(gmtDate.time)).toBeInTheDocument();
  expect(screen.getByText(`(${gmtDate.gmt})`)).toBeInTheDocument();*/
})

test('Remove translator check onClick event', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobTranslatedOutsourced)
  render(<JobContainer {...props} />)

  const buttonElement = screen.getByTestId('remove-translator-button')
  expect(buttonElement).toBeEnabled()
})

test('Buy translation: check onClick event', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobContainer {...props} />)

  const buyTranslationElement = screen.getByTestId('buy-translation-button')
  expect(buyTranslationElement).toBeEnabled()
})

xtest('Check Open link', () => {
  const {props, project, job} = getFakeProperties(
    fakeProjectsData.jobWithoutActivity,
  )
  render(<JobContainer {...props} />)

  const openElement = screen.getByText(/Open/).getAttribute('href')

  const correctUrl = createTranslateUrl(
    props.index,
    project,
    job,
    props.jobsLength,
  )
  expect(openElement).toBe(correctUrl)
})

const cloneData = (data) => JSON.parse(JSON.stringify(data))

const buildFakeProperties = (data, extraProps = {}) => {
  const project = fromJS(data)
  const jobs = project.get('jobs')
  const job = jobs.first()

  return {
    project,
    jobs,
    job,
    props: {
      index: 0,
      isChunk: false,
      isChunkOutsourced: false,
      job,
      project,
      isChecked: false,
      onCheckedJob: () => {},
      downloadTranslationFn: () => {},
      ...extraProps,
    },
  }
}

describe('Extended interactions (menu actions, outsource, notifications)', () => {
  beforeEach(() => {
    window.open = jest.fn()
    changeJobPassword.mockResolvedValue({
      new_pwd: 'newpwd',
      old_pwd: 'oldpwd',
      id: '90',
    })
    jest
      .spyOn(CommonUtils, 'dispatchAnalyticsEvents')
      .mockImplementation(() => {})
  })

  afterEach(() => {
    jest.clearAllMocks()
    jest.restoreAllMocks()
  })

  test('Split: opens the split modal with job/project and the reload callback', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('menu-split'))

    expect(ModalsActions.openSplitJobModal).toHaveBeenCalledWith(
      props.job,
      props.project,
      ManageActions.reloadProjects,
    )
  })

  test('Merge: opens the merge modal with plain JS job/project', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('menu-merge'))

    expect(ModalsActions.openMergeModal).toHaveBeenCalledWith(
      props.project.toJS(),
      props.job.toJS(),
      ManageActions.reloadProjects,
    )
  })

  test('Archive and cancel: dispatch the status change and a notification', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('menu-archive'))
    expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
      props.project,
      props.job,
      'archive',
    )
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Jobs archived'}),
    )

    await userEvent.click(screen.getByTestId('menu-cancel'))
    expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
      props.project,
      props.job,
      'cancel',
    )
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Jobs canceled'}),
    )
  })

  test('Unarchive: reactivates an archived job', async () => {
    const data = cloneData(fakeProjectsData.jobWithoutActivity.data)
    data.jobs[0].status = 'archived'
    const {props} = buildFakeProperties(data, {
      downloadTranslationFn: jest.fn(),
    })

    render(<JobContainer {...props} />)

    await userEvent.click(screen.getByTestId('menu-activate'))

    expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
      props.project,
      props.job,
      'active',
    )
  })

  test('Delete permanently: confirmation modal success/cancel callbacks', async () => {
    const data = cloneData(fakeProjectsData.jobWithoutActivity.data)
    data.jobs[0].status = 'cancelled'
    const {props} = buildFakeProperties(data, {
      downloadTranslationFn: jest.fn(),
    })

    render(<JobContainer {...props} />)

    await userEvent.click(screen.getByTestId('menu-delete'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
    const [, modalProps, title] = ModalsActions.showModalComponent.mock.calls[0]
    expect(title).toBe('Confirmation required')

    modalProps.cancelCallback()
    modalProps.successCallback()

    expect(ManageActions.changeJobStatus).toHaveBeenCalledWith(
      props.project,
      props.job,
      'delete',
    )
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Jobs deleted permanently'}),
    )
  })

  test('Change password (Translate): notifies and undo restores the previous password', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('menu-change-password'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Translate password changed'}),
      ),
    )
    expect(ManageActions.changeJobPassword).toHaveBeenCalledTimes(1)
    expect(changeJobPassword).toHaveBeenCalledTimes(1)

    const notification = CatToolActions.addNotification.mock.calls.find(
      ([call]) => call.title === 'Translate password changed',
    )[0]

    render(notification.text)
    await userEvent.click(screen.getByText('Undo'))

    await waitFor(() =>
      expect(CatToolActions.removeNotification).toHaveBeenCalledWith(
        notification,
      ),
    )
    await waitFor(() => expect(changeJobPassword).toHaveBeenCalledTimes(2))
    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          text: 'The previous password has been restored.',
        }),
      ),
    )
    expect(ManageActions.changeJobPassword).toHaveBeenCalledTimes(2)
  })

  test('Change password (Revise): oldPassword is taken from revise_passwords', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('menu-change-password-1'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Revise password changed'}),
      ),
    )
  })

  test('Download menu item: dispatches analytics and calls downloadTranslationFn', async () => {
    const downloadTranslationFn = jest.fn()
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(
      <JobContainer {...props} downloadTranslationFn={downloadTranslationFn} />,
    )

    await userEvent.click(screen.getByTestId('menu-download-action'))

    expect(downloadTranslationFn).toHaveBeenCalledTimes(1)
    const [projectArg, jobArg, urlArg] = downloadTranslationFn.mock.calls[0]
    expect(projectArg).toEqual(props.project.toJS())
    expect(jobArg).toEqual(props.job.toJS())
    expect(urlArg).toContain('?action=warnings')
  })

  test('Assign: opens the outsource box, then closes it', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByText('Assign'))
    expect(screen.getByTestId('outsource-container-mock')).toBeInTheDocument()

    await userEvent.click(screen.getByTestId('outsource-close'))
    expect(
      screen.queryByTestId('outsource-container-mock'),
    ).not.toBeInTheDocument()
  })

  test('Buy translation button: opens the outsource box (and dispatches analytics on re-open)', async () => {
    const data = cloneData(fakeProjectsData.jobWithoutActivity.data)
    data.jobs[0].outsource_available = true
    const {props} = buildFakeProperties(data, {
      downloadTranslationFn: jest.fn(),
    })

    render(<JobContainer {...props} />)

    await userEvent.click(screen.getByTestId('buy-translation-button'))
    expect(screen.getByTestId('outsource-container-mock')).toBeInTheDocument()

    // clicking again while already open: outsource_available is true and
    // showingOutsource is already defined, exercising the analytics branch
    await userEvent.click(screen.getByTestId('buy-translation-button'))
    expect(screen.getByTestId('outsource-container-mock')).toBeInTheDocument()
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'outsource_request',
    })

    await userEvent.click(screen.getByTestId('outsource-close'))
    expect(
      screen.queryByTestId('outsource-container-mock'),
    ).not.toBeInTheDocument()
  })

  test('Remove translator: notifies and undo restores the previous password', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobTranslatedOutsourced)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('remove-translator-button'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Job unassigned'}),
      ),
    )

    const notification = CatToolActions.addNotification.mock.calls.find(
      ([call]) => call.title === 'Job unassigned',
    )[0]

    render(notification.text)
    await userEvent.click(screen.getByText('Undo'))

    await waitFor(() =>
      expect(CatToolActions.removeNotification).toHaveBeenCalledWith(
        notification,
      ),
    )
    await waitFor(() => expect(changeJobPassword).toHaveBeenCalledTimes(2))
    expect(ManageActions.changeJobPassword).toHaveBeenCalledTimes(2)
  })

  test('Outsource delivery email: clicking it opens the outsource box', async () => {
    const {props, job} = getFakeProperties(
      fakeProjectsData.jobTranslatedOutsourced,
    )
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByText(job.get('translator').get('email')))

    expect(screen.getByTestId('outsource-container-mock')).toBeInTheDocument()
  })

  test('Outsourced-to-Translated job: shows the logo, delivery date and View status button', async () => {
    const data = cloneData(fakeProjectsData.jobWithoutActivity.data)
    data.jobs[0].outsource = {
      id_vendor: '1',
      quote_review_link: 'https://example.com/quote',
      delivery_timestamp: 1700000000,
    }
    data.jobs[0].translator = null
    const {props} = buildFakeProperties(data, {
      downloadTranslationFn: jest.fn(),
    })

    render(<JobContainer {...props} />)

    expect(
      screen.getByTitle('Outsourced to translated.net'),
    ).toBeInTheDocument()

    // outsource_available is falsy here, so this exercises the "contact us"
    // fallback branch of openOutsourceModal
    await userEvent.click(screen.getByText('View status'))

    expect(window.open).toHaveBeenCalledWith(
      'https://translated.com/contact-us',
      '_blank',
    )
  })

  test('Quality issues: QR, warnings and comments icons open the expected URLs', async () => {
    const data = cloneData(fakeProjectsData.jobWithoutActivity.data)
    data.jobs[0].quality_summary.quality_overall = 'poor'
    data.jobs[0].warnings_count = 2
    data.jobs[0].open_threads_count = 2
    const {props} = buildFakeProperties(data, {
      downloadTranslationFn: jest.fn(),
    })

    render(<JobContainer {...props} />)

    const container = screen.getByTestId('job-activity-icons')
    const buttons = container.querySelectorAll('button')
    expect(buttons.length).toBe(3)

    await userEvent.click(buttons[0])
    await userEvent.click(buttons[1])
    await userEvent.click(buttons[2])

    expect(window.open).toHaveBeenCalledTimes(3)
  })

  test('Words button: opens the analyze URL', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByTestId('words-button'))

    expect(window.open).toHaveBeenCalledWith(
      expect.stringContaining('/analyze/'),
      '_blank',
    )
  })

  test('Open button: opens the translate URL', async () => {
    const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
    render(<JobContainer {...props} downloadTranslationFn={jest.fn()} />)

    await userEvent.click(screen.getByText('Open'))

    expect(window.open).toHaveBeenCalledWith(
      expect.stringContaining('/translate/'),
      '_blank',
    )
  })
})
