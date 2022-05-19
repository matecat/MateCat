import {render, screen, act, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import React from 'react'
import Immutable from 'immutable'
import JobContainer from './JobContainer'
import ProjectsStore from '../../stores/ProjectsStore'
import ManageConstants from '../../constants/ManageConstants'

require('../../../../lib/jquery-dateFormat.min.js')
// high order function -> $.format.date (jquery plugin) - workaround
const formatDate = (context, fn) => {
  return function (...args) {
    const params = args.map((argument) =>
      typeof argument === 'string' && /hh/i.test(argument)
        ? argument.toUpperCase()
        : argument,
    )
    return fn.apply(context, params)
  }
}
$.format.date = formatDate($.format, $.format.date)

require('../../../../common')
window.config = {enable_outsource: 1}

const fakeProjectsData = {
  jobWithoutActivity: {
    data: JSON.parse(
      '{"id":9,"password":"59b94d64a7ef","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-02 10:59:28","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":90,"password":"a5b852c4fe52","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"a192d66ec1f5"}],"urls":{"password":"a5b852c4fe52","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-a5b852c4fe52","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=a5b852c4fe52&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=a5b852c4fe52&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/a5b852c4fe52/90.zip"}},{"id":91,"password":"ce560196ca5c","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":91,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1c0eb403b087"}],"urls":{"password":"ce560196ca5c","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip"}},{"id":92,"password":"25c9442ad64c","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":92,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"3f0a9e425baf"}],"urls":{"password":"25c9442ad64c","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip"}},{"id":93,"password":"667611949406","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":93,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"be016cc3fd85"}],"urls":{"password":"667611949406","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      index: 0,
      jobsLenght: 4,
      isChunk: false,
      isChunkOutsourced: false,
      activityLogUrl: '/activityLog/9/59b94d64a7ef',
    },
  },
  jobActivity: {
    data: JSON.parse(
      '{"id":6,"password":"59ad778c68b1","name":"tesla.docx","id_team":1,"id_assignee":1,"create_date":"2021-06-23 14:27:08","fast_analysis_wc":374,"standard_analysis_wc":357,"tm_analysis_wc":"306.40","project_slug":"tesladocx","jobs":[{"id":6,"password":"2a35d508882e","source":"en-US","target":"it-IT","sourceTxt":"English US","targetTxt":"Italian","job_first_segment":"1","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1624451228,"created_at":"2021-06-23T14:27:08+02:00","create_date":"2021-06-23 14:27:08","formatted_create_date":"Jun 23, 14:27","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[],"warnings_count":3,"warning_segments":[1,3,5],"stats":{"id":6,"DRAFT":0,"TRANSLATED":84.2,"APPROVED":72,"REJECTED":0,"TOTAL":156.2,"PROGRESS":156.2,"TOTAL_FORMATTED":"156","PROGRESS_FORMATTED":"156","APPROVED_FORMATTED":"72","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"0","TRANSLATED_FORMATTED":"84","APPROVED_PERC":46.094750320102,"REJECTED_PERC":0,"DRAFT_PERC":0,"TRANSLATED_PERC":53.905249679898,"PROGRESS_PERC":100,"TRANSLATED_PERC_FORMATTED":53.91,"DRAFT_PERC_FORMATTED":0,"APPROVED_PERC_FORMATTED":46.09,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":100,"TODO_FORMATTED":"0","TODO":0,"DOWNLOAD_STATUS":"translated","revises":[{"revision_number":1,"advancement_wc":72}]},"outsource":null,"translator":null,"total_raw_wc":213,"standard_wc":179,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":3,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"9084da7a0d31"}],"urls":{"password":"2a35d508882e","translate_url":"https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-2a35d508882e","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-9084da7a0d31"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/6/2a35d508882e/6.zip"}},{"id":6,"password":"307be438d286","source":"en-US","target":"it-IT","sourceTxt":"English US","targetTxt":"Italian","job_first_segment":"9","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":2,"create_timestamp":1625150353,"created_at":"2021-07-01T16:39:13+02:00","create_date":"2021-07-01 16:39:13","formatted_create_date":"Jul 01, 16:39","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[],"warnings_count":2,"warning_segments":[11,12],"stats":{"id":6,"DRAFT":0,"TRANSLATED":150.2,"APPROVED":0,"REJECTED":0,"TOTAL":150.2,"PROGRESS":150.2,"TOTAL_FORMATTED":"150","PROGRESS_FORMATTED":"150","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"0","TRANSLATED_FORMATTED":"150","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":0,"TRANSLATED_PERC":100,"PROGRESS_PERC":100,"TRANSLATED_PERC_FORMATTED":100,"DRAFT_PERC_FORMATTED":0,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":100,"TODO_FORMATTED":"0","TODO":0,"DOWNLOAD_STATUS":"translated","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":213,"standard_wc":179,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":2,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"e7ffa4998c82"}],"urls":{"password":"307be438d286","translate_url":"https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-307be438d286","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-e7ffa4998c82"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/6/2a35d508882e/6.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      index: 1,
      jobsLenght: 2,
      isChunk: true,
      isChunkOutsourced: false,
      activityLogUrl: '/activityLog/6/59ad778c68b1',
    },
  },
  jobTranslatedOutsourced: {
    data: JSON.parse(
      '{"id":9,"password":"59b94d64a7ef","name":"Test","id_team":1,"id_assignee":1,"create_date":"2021-07-02 10:59:28","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":90,"password":"a5b852c4fe52","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":90,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":{"email":"pierluigi.dicianni@translated.net","added_by":1,"delivery_date":"2021-07-07 11:00:00","delivery_timestamp":1625648400,"source":"en-US","target":"la-XN","id_translator_profile":"1","user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false}},"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"a192d66ec1f5"}],"urls":{"password":"a5b852c4fe52","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/90-a5b852c4fe52","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/90-a192d66ec1f5"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=90&password=a5b852c4fe52&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=90&id_file=&password=a5b852c4fe52&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/90/a5b852c4fe52/90.zip"}},{"id":91,"password":"ce560196ca5c","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":91,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1c0eb403b087"}],"urls":{"password":"ce560196ca5c","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/91-ce560196ca5c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/91-1c0eb403b087"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=91&password=ce560196ca5c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=91&id_file=&password=ce560196ca5c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/91/ce560196ca5c/91.zip"}},{"id":92,"password":"25c9442ad64c","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":92,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"3f0a9e425baf"}],"urls":{"password":"25c9442ad64c","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/92-25c9442ad64c","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/92-3f0a9e425baf"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=92&password=25c9442ad64c&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=92&id_file=&password=25c9442ad64c&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/92/25c9442ad64c/92.zip"}},{"id":93,"password":"667611949406","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"58","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1625216368,"created_at":"2021-07-02T10:59:28+02:00","create_date":"2021-07-02 10:59:28","formatted_create_date":"Jul 02, 10:59","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":93,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"be016cc3fd85"}],"urls":{"password":"667611949406","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/93-667611949406","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/93-be016cc3fd85"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=93&password=667611949406&download_type=all&filename=4","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=93&id_file=&password=667611949406&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/93/667611949406/93.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}',
    ),
    props: {
      index: 0,
      jobsLenght: 4,
      isChunk: false,
      isChunkOutsourced: false,
      activityLogUrl: '/activityLog/9/59b94d64a7ef',
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
const createTranslateUrl = (index, project, job, jobsLenght) => {
  const usePrefix = jobsLenght > 1
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

  // source field
  expect(screen.getByTestId('source-label')).toBeInTheDocument()

  // target field
  expect(screen.getByTestId('target-label')).toBeInTheDocument()

  // words number
  expect(
    screen.getByText(job.get('stats').get('TOTAL_FORMATTED')),
  ).toBeInTheDocument()

  // TM button
  expect(screen.getByTestId('tm-button')).toBeInTheDocument()

  // assign job to translator
  expect(screen.getByText('Assign job to translator')).toBeInTheDocument()

  // buy translation
  expect(screen.getByText('Buy Translation')).toBeInTheDocument()

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

test('Check job without TM button', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobActivity)
  render(<JobContainer {...props} />)

  expect(screen.getByTestId('tm-container')).toBeEmptyDOMElement()
})

test('Job payable: check analisys URL', () => {
  const {project, props} = getFakeProperties(
    fakeProjectsData.jobWithoutActivity,
  )
  render(<JobContainer {...props} />)

  // analysisUrl
  const hrefAttribute = screen.getByText('words').getAttribute('href')

  const correctUrl = getProjectAnalyzeUrl(
    project.get('project_slug'),
    project.get('id'),
    project.get('password'),
  )
  expect(hrefAttribute).toBe(correctUrl)
})

xtest('Check TM onClick callback', async () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  act(() => {
    render(<JobContainer {...props} />)
  })
  // TM function
  const tmCallback = jest.fn()
  act(() => {
    ProjectsStore.addListener(ManageConstants.OPEN_JOB_TM_PANEL, tmCallback)
  })
  await waitFor(() => {
    expect(screen.getByTestId('tm-button')).toBeInTheDocument()
  })
  userEvent.click(screen.getByTestId('tm-button'))
  expect(tmCallback).toHaveBeenCalled()
  act(() => {
    ProjectsStore.removeListener(ManageConstants.OPEN_JOB_TM_PANEL, tmCallback)
  })
})

test('Assign job to translator: check onClick event', () => {
  const {props} = getFakeProperties(fakeProjectsData.jobWithoutActivity)
  render(<JobContainer {...props} />)

  const jobToTranslatorElement = screen.getByText('Assign job to translator')
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

test('Check Open link', () => {
  const {props, project, job} = getFakeProperties(
    fakeProjectsData.jobWithoutActivity,
  )
  render(<JobContainer {...props} />)

  const openElement = screen.getByText(/Open/).getAttribute('href')

  const correctUrl = createTranslateUrl(
    props.index,
    project,
    job,
    props.jobsLenght,
  )
  expect(openElement).toBe(correctUrl)
})
