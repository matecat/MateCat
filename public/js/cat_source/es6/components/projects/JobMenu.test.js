import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import JobMenu from './JobMenu'
import Immutable from 'immutable'
import {rest} from 'msw'
import {mswServer} from '../../../../../mocks/mswServer'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageActions from '../../actions/ManageActions'
import ManageConstants from '../../constants/ManageConstants'

window.config = {
  splitEnabled: 1,
  hostpath: 'https://dev.matecat.com',
}

const fakeProjectsData = {
  jobWithoutActivity: {
    data: JSON.parse(
      '{"id":9,"password":"59b94d64a7ef","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-02 10:59:28","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":90,"password":"a5b852c4fe52","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"a192d66ec1f5"}],"urls":{"password":"a5b852c4fe52","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-a5b852c4fe52","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=a5b852c4fe52&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=a5b852c4fe52&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/a5b852c4fe52/90.zip"}},{"id":91,"password":"ce560196ca5c","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":91,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1c0eb403b087"}],"urls":{"password":"ce560196ca5c","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip"}},{"id":92,"password":"25c9442ad64c","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":92,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"3f0a9e425baf"}],"urls":{"password":"25c9442ad64c","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip"}},{"id":93,"password":"667611949406","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":93,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"be016cc3fd85"}],"urls":{"password":"667611949406","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      isChunk: false,
      isChunkOutsourced: false,
      jobTMXUrl: '/TMX/90/Y2Q3OTNjE5ZG629',
      exportXliffUrl: '/SDLXLIFF/90/Y2Q3OTNjE5ZG629/test.zip',
      originalUrl:
        '/?action=downloadOriginal&id_job=90 &password=Y2Q3OTNjE5ZG629&download_type=all',
      editingLogUrl: '/editlog/90-Y2Q3OTNjE5ZG629',
      qAReportUrl: '/revise-summary/90-Y2Q3OTNjE5ZG629',
      reviseUrl: '/revise/test/en-US-la-XN/90-0-a192d66ec1f5#58',
    },
  },
  jobSplitted: {
    data: JSON.parse(
      '{"id":9,"password":"59b94d64a7ef","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-02 10:59:28","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":90,"password":"NWUxMWYjEwZT600","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":185.3,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":185.3,"PROGRESS":0,"TOTAL_FORMATTED":"185","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"185","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"185","TODO":185,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":213,"standard_wc":213,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"a192d66ec1f5"}],"urls":{"password":"NWUxMWYjEwZT600","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-NWUxMWYjEwZT600","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=NWUxMWYjEwZT600&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=NWUxMWYjEwZT600&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/NWUxMWYjEwZT600/90.zip"}},{"id":90,"password":"ea2a922840f6","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"66","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625841183,"created_at":"2021-07-09T16:33:03+02:00","create_date":"2021-07-09 16:33:03","formatted_create_date":"Jul 09, 16:33","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":176.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":176.8,"PROGRESS":0,"TOTAL_FORMATTED":"177","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"177","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"177","TODO":177,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":213,"standard_wc":213,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"fc6deb4bee89"}],"urls":{"password":"ea2a922840f6","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-ea2a922840f6","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-fc6deb4bee89"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=NWUxMWYjEwZT600&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=NWUxMWYjEwZT600&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/NWUxMWYjEwZT600/90.zip"}},{"id":91,"password":"ce560196ca5c","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":91,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1c0eb403b087"}],"urls":{"password":"ce560196ca5c","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip"}},{"id":92,"password":"25c9442ad64c","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":92,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"3f0a9e425baf"}],"urls":{"password":"25c9442ad64c","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip"}},{"id":93,"password":"667611949406","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":93,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"be016cc3fd85"}],"urls":{"password":"667611949406","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      isChunk: true,
      isChunkOutsourced: false,
      jobTMXUrl: '/TMX/90/NWUxMWYjEwZT600',
      exportXliffUrl: '/SDLXLIFF/90/NWUxMWYjEwZT600/test.zip',
      originalUrl:
        '/?action=downloadOriginal&id_job=90 &password=NWUxMWYjEwZT600&download_type=all',
      editingLogUrl: '/editlog/90-NWUxMWYjEwZT600',
      qAReportUrl: '/revise-summary/90-NWUxMWYjEwZT600',
      reviseUrl: '/revise/test/en-US-la-XN/90-1-a192d66ec1f5#58',
    },
  },
  jobArchived: {
    data: JSON.parse(
      '{"id":9,"password":"59b94d64a7ef","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-02 10:59:28","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":90,"password":"NWUxMWYjEwZT600","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"58","status":"archived","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"a192d66ec1f5"}],"urls":{"password":"NWUxMWYjEwZT600","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-NWUxMWYjEwZT600","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=NWUxMWYjEwZT600&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=NWUxMWYjEwZT600&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/NWUxMWYjEwZT600/90.zip"}},{"id":91,"password":"ce560196ca5c","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":91,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1c0eb403b087"}],"urls":{"password":"ce560196ca5c","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip"}},{"id":92,"password":"25c9442ad64c","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":92,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"3f0a9e425baf"}],"urls":{"password":"25c9442ad64c","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip"}},{"id":93,"password":"667611949406","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":93,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"be016cc3fd85"}],"urls":{"password":"667611949406","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":true,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      isChunk: false,
      isChunkOutsourced: false,
      jobTMXUrl: '/TMX/90/NWUxMWYjEwZT600',
      exportXliffUrl: '/SDLXLIFF/90/NWUxMWYjEwZT600/test.zip',
      originalUrl:
        '/?action=downloadOriginal&id_job=90 &password=NWUxMWYjEwZT600&download_type=all',
      editingLogUrl: '/editlog/90-NWUxMWYjEwZT600',
      qAReportUrl: '/revise-summary/90-NWUxMWYjEwZT600',
      reviseUrl: '/revise/test/en-US-la-XN/90-1-a192d66ec1f5#58',
    },
  },
  jobCancelled: {
    data: JSON.parse(
      '{"id":9,"password":"59b94d64a7ef","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-02 10:59:28","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":90,"password":"NWUxMWYjEwZT600","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"58","status":"cancelled","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"a192d66ec1f5"}],"urls":{"password":"NWUxMWYjEwZT600","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-NWUxMWYjEwZT600","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=NWUxMWYjEwZT600&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=NWUxMWYjEwZT600&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/NWUxMWYjEwZT600/90.zip"}},{"id":91,"password":"ce560196ca5c","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":91,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1c0eb403b087"}],"urls":{"password":"ce560196ca5c","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip"}},{"id":92,"password":"25c9442ad64c","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":92,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"3f0a9e425baf"}],"urls":{"password":"25c9442ad64c","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip"}},{"id":93,"password":"667611949406","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":93,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"be016cc3fd85"}],"urls":{"password":"667611949406","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":true,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      isChunk: false,
      isChunkOutsourced: false,
      jobTMXUrl: '/TMX/90/NWUxMWYjEwZT600',
      exportXliffUrl: '/SDLXLIFF/90/NWUxMWYjEwZT600/test.zip',
      originalUrl:
        '/?action=downloadOriginal&id_job=90 &password=NWUxMWYjEwZT600&download_type=all',
      editingLogUrl: '/editlog/90-NWUxMWYjEwZT600',
      qAReportUrl: '/revise-summary/90-NWUxMWYjEwZT600',
      reviseUrl: '/revise/test/en-US-la-XN/90-1-a192d66ec1f5#58',
    },
  },
  jobGenerateRevise2: {
    data: JSON.parse(
      '{"id":11,"password":"c19166d0d09b","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-12 10:05:02","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":98,"password":"defe9aad39e3","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":98,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"25a2513040eb"}],"urls":{"password":"defe9aad39e3","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/98-defe9aad39e3","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/98-25a2513040eb"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=98&password=defe9aad39e3&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=98&id_file=&password=defe9aad39e3&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/98/defe9aad39e3/98.zip"}},{"id":99,"password":"278d3f0a255b","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":99,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"7f37feb2f216"}],"urls":{"password":"278d3f0a255b","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/99-278d3f0a255b","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/99-7f37feb2f216"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=99&password=278d3f0a255b&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=99&id_file=&password=278d3f0a255b&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/99/278d3f0a255b/99.zip"}},{"id":100,"password":"b9d1cf9c3a04","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":100,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1236458b8d2d"}],"urls":{"password":"b9d1cf9c3a04","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/100-b9d1cf9c3a04","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/100-1236458b8d2d"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=100&password=b9d1cf9c3a04&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=100&id_file=&password=b9d1cf9c3a04&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/100/b9d1cf9c3a04/100.zip"}},{"id":101,"password":"61b34dd4d39e","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":101,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"b878ee8583d2"}],"urls":{"password":"61b34dd4d39e","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/101-61b34dd4d39e","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/101-b878ee8583d2"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=101&password=61b34dd4d39e&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=101&id_file=&password=61b34dd4d39e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/101/61b34dd4d39e/101.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      isChunk: false,
      isChunkOutsourced: false,
      jobTMXUrl: '/TMX/98/defe9aad39e3',
      exportXliffUrl: '/SDLXLIFF/98/defe9aad39e3/test.zip',
      originalUrl:
        '/?action=downloadOriginal&id_job=98 &password=defe9aad39e3&download_type=all',
      editingLogUrl: '/editlog/98-defe9aad39e3',
      qAReportUrl: '/revise-summary/98-defe9aad39e3',
      reviseUrl: '/revise/test/en-US-la-XN/98-0-25a2513040eb#96',
    },
  },
}

const getFakeProperties = (fakeProperties) => {
  const {data, props} = fakeProperties
  const project = Immutable.fromJS(data)
  const jobs = project.get('jobs')
  const job = jobs.first()

  return {
    project,
    jobs,
    job,
    props: {
      ...props,
      jobId: job.get('id'),
      review_password: job.get('review_password'),
      status: job.get('status'),
      job,
      project,
      getDownloadLabel: (
        <a className="item">
          <i className="icon-eye icon" /> Draft
        </a>
      ),
      openSplitModalFn: () => {},
      openMergeModalFn: () => {},
      changePasswordFn: () => {},
      archiveJobFn: () => {},
      activateJobFn: () => {},
      cancelJobFn: () => {},
    },
  }
}

const fakeRenderProjects = [
  JSON.parse(`[${JSON.stringify(fakeProjectsData.jobGenerateRevise2.data)}]`),
  JSON.parse(
    '{"id":1,"name":"Personal","type":"personal","created_at":"2021-06-23T12:51:48+02:00","created_by":1,"members":[{"id":1,"id_team":1,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":11}],"pending_invitations":[]}',
  ),
  JSON.parse(
    '[{"id":1,"name":"Personal","type":"personal","created_at":"2021-06-23T12:51:48+02:00","created_by":1,"members":[{"id":1,"id_team":1,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":11}],"pending_invitations":[]},{"id":2,"name":"Test","type":"general","created_at":"2021-07-05T15:40:56+02:00","created_by":1,"members":[{"id":2,"id_team":2,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":0}],"pending_invitations":[]}]',
  ),
]
ManageActions.renderProjects(...fakeRenderProjects)
ManageActions.storeSelectedTeam(fakeRenderProjects[1])

const getRevise2Url = (project, job) => {
  const name = project.get('name')
  const source = job.get('source')
  const target = job.get('target')
  const id = job.get('id')
  const revisePasswords = job.get('revise_passwords')
  return `${
    window.config.hostpath
  }/revise2/${name}/${source}-${target}/${id}-${revisePasswords
    .get(1)
    .get('password')}`
}

test('Rendering elements', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobMenu {...props} />)

  expect(screen.getByText('Change Password')).toBeInTheDocument()
  expect(screen.getByText('Split')).toBeInTheDocument()
  expect(screen.getByTestId('revise-item')).toHaveTextContent('Revise')
  expect(screen.getByText('Generate Revise 2')).toBeInTheDocument()
  expect(screen.getByText('QA Report')).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByText('Download Original')).toBeInTheDocument()
  expect(screen.getByText('Export XLIFF')).toBeInTheDocument()
  expect(screen.getByText('Export TMX')).toBeInTheDocument()
  expect(screen.getByText('Archive job')).toBeInTheDocument()
  expect(screen.getByText('Cancel job')).toBeInTheDocument()
})

test('Items are enabled', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobMenu {...props} />)

  expect(screen.getByText('Change Password')).toBeEnabled()
  expect(screen.getByText('Split')).toBeEnabled()
  expect(screen.getByTestId('revise-item')).toBeEnabled()
  expect(screen.getByText('Generate Revise 2')).toBeEnabled()
  expect(screen.getByText('QA Report')).toBeEnabled()
  expect(screen.getByText('Draft')).toBeEnabled()
  expect(screen.getByText('Download Original')).toBeEnabled()
  expect(screen.getByText('Export XLIFF')).toBeEnabled()
  expect(screen.getByText('Export TMX')).toBeEnabled()
  expect(screen.getByText('Archive job')).toBeEnabled()
  expect(screen.getByText('Cancel job')).toBeEnabled()
})

// TODO: Da verificare errore sulla libreria semantic
test.skip('Change password dropdown menu', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobMenu {...props} />)

  userEvent.click(screen.getByText('Change Password'))
  expect(screen.getByTestId('change-password-submenu')).toBeVisible()
})

test('Check items href link', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobMenu {...props} />)

  const reviseHref = screen.getByTestId('revise-item').getAttribute('href')
  expect(reviseHref).toBe(props.reviseUrl)

  const qaReportHref = screen.getByText('QA Report').getAttribute('href')
  expect(qaReportHref).toBe(props.qAReportUrl)

  const downloadOriginaletHref = screen
    .getByText('Download Original')
    .getAttribute('href')
  expect(downloadOriginaletHref).toBe(props.originalUrl)

  const exportXLIFFtHref = screen.getByText('Export XLIFF').getAttribute('href')
  expect(exportXLIFFtHref).toBe(props.exportXliffUrl)

  const exportTMXtHref = screen.getByText('Export TMX').getAttribute('href')
  expect(exportTMXtHref).toBe(props.jobTMXUrl)
})

test('Splitted job: check Merge item', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobSplitted)
  render(<JobMenu {...props} />)

  expect(screen.getByText('Merge')).toBeInTheDocument()
})

test('Archived job: check Unarchive job item', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobArchived)
  render(<JobMenu {...props} />)

  expect(screen.getByText('Unarchive job')).toBeInTheDocument()
})

test('Cancelled job: check Resume job item', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobCancelled)
  render(<JobMenu {...props} />)

  expect(screen.getByText('Resume job')).toBeInTheDocument()
})

test('Generate revise 2: onClick flow', async () => {
  mswServer.use(
    ...[
      rest.post(
        '/plugins/second_pass_review/project/:id/:password/reviews',
        (req, res, ctx) => {
          return res(
            ctx.status(200),
            ctx.json({
              chunk_review: {
                id: 164,
                id_job: 98,
                review_password: '6688b6b321de',
              },
            }),
          )
        },
      ),
    ],
  )

  // updated props
  const updatedData = {
    data: {},
    props: {...fakeProjectsData.jobGenerateRevise2.props},
  }

  const onUpdate = (projects) => {
    updatedData.data = JSON.parse(JSON.stringify(projects.first().toJS()))
    const {props} = getFakeProperties(updatedData)
    render(<JobMenu {...props} />)

    ProjectsStore.removeListener(ManageConstants.UPDATE_PROJECTS, onUpdate)
  }

  ProjectsStore.addListener(ManageConstants.UPDATE_PROJECTS, onUpdate)

  // first render
  const {props} = getFakeProperties(fakeProjectsData.jobGenerateRevise2)
  render(<JobMenu {...props} />)

  userEvent.click(screen.getByText('Generate Revise 2'))

  await waitFor(() => {
    expect(screen.getByText('Revise 2')).toBeInTheDocument()
    const href = screen.getByText('Revise 2').getAttribute('href')
    const {project, job} = getFakeProperties(updatedData)
    expect(href).toBe(getRevise2Url(project, job))
  })
})
