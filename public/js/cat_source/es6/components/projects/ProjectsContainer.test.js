import {render, screen, waitFor, act} from '@testing-library/react'
import React from 'react'
import Immutable from 'immutable'
import {rest} from 'msw'

import ProjectsContainer from './ProjectsContainer'
import ManageActions from '../../actions/ManageActions'
import {mswServer} from '../../../../../mocks/mswServer'

// create modal div
const modalElement = document.createElement('div')
modalElement.id = 'modal'
document.body.appendChild(modalElement)

require('../../../../common')
require('../../../../login')
window.config = {
  enable_outsource: 1,
  basepath: '/',
}

const fakeProjectsData = {
  projects: {
    data: JSON.parse(
      '[{"id":17,"password":"3d17443dd94c","name":"TestXLIFF","id_team":1,"id_assignee":1,"create_date":"2021-07-14 16:18:43","fast_analysis_wc":4,"standard_analysis_wc":4,"tm_analysis_wc":"3.20","project_slug":"testxliff","jobs":[{"id":110,"password":"9599a9febd1e","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"156","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626272323,"created_at":"2021-07-14T16:18:43+02:00","create_date":"2021-07-14 16:18:43","formatted_create_date":"Today, 16:18","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":110,"DRAFT":3.2,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":3.2,"PROGRESS":0,"TOTAL_FORMATTED":"3","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"3","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"3","TODO":3,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":4,"standard_wc":4,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"5232e90c880b"}],"urls":{"password":"9599a9febd1e","translate_url":"https://dev.matecat.com/translate/TestXLIFF/en-US-es-ES/110-9599a9febd1e","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/TestXLIFF/en-US-es-ES/110-5232e90c880b"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=110&password=9599a9febd1e&download_type=all&filename=12","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=110&id_file=&password=9599a9febd1e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/110/9599a9febd1e/110.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null},{"id":11,"password":"c19166d0d09b","name":"Test Project","id_team":1,"id_assignee":1,"create_date":"2021-07-12 10:05:02","fast_analysis_wc":374,"standard_analysis_wc":1704,"tm_analysis_wc":"1427.09","project_slug":"test","jobs":[{"id":98,"password":"defe9aad39e3","source":"en-US","target":"la-XN","sourceTxt":"English US","targetTxt":"Latin","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":98,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0},{"revision_number":2,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"25a2513040eb"},{"revision_number":2,"password":"6688b6b321de"}],"urls":{"password":"defe9aad39e3","translate_url":"https://dev.matecat.com/translate/Test/en-US-la-XN/98-defe9aad39e3","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-la-XN/98-25a2513040eb"},{"revision_number":2,"url":"https://dev.matecat.com/revise2/Test/en-US-la-XN/98-6688b6b321de"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=98&password=defe9aad39e3&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=98&id_file=&password=defe9aad39e3&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/98/defe9aad39e3/98.zip"}},{"id":99,"password":"278d3f0a255b","source":"en-US","target":"es-ES","sourceTxt":"English US","targetTxt":"Spanish","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":99,"DRAFT":340.8,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":340.8,"PROGRESS":0,"TOTAL_FORMATTED":"341","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"341","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"341","TODO":341,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"7f37feb2f216"}],"urls":{"password":"278d3f0a255b","translate_url":"https://dev.matecat.com/translate/Test/en-US-es-ES/99-278d3f0a255b","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-es-ES/99-7f37feb2f216"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=99&password=278d3f0a255b&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=99&id_file=&password=278d3f0a255b&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/99/278d3f0a255b/99.zip"}},{"id":100,"password":"b9d1cf9c3a04","source":"en-US","target":"en-GB","sourceTxt":"English US","targetTxt":"English","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":100,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"1236458b8d2d"}],"urls":{"password":"b9d1cf9c3a04","translate_url":"https://dev.matecat.com/translate/Test/en-US-en-GB/100-b9d1cf9c3a04","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-en-GB/100-1236458b8d2d"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=100&password=b9d1cf9c3a04&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=100&id_file=&password=b9d1cf9c3a04&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/100/b9d1cf9c3a04/100.zip"}},{"id":101,"password":"61b34dd4d39e","source":"en-US","target":"mt-MT","sourceTxt":"English US","targetTxt":"Maltese","job_first_segment":"96","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1626077103,"created_at":"2021-07-12T10:05:03+02:00","create_date":"2021-07-12 10:05:03","formatted_create_date":"Jul 12, 10:05","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[{"key":"c52da4a03d6aea33f242","r":1,"w":1,"name":"Test"}],"warnings_count":0,"warning_segments":[],"stats":{"id":101,"DRAFT":362.1,"TRANSLATED":0,"APPROVED":0,"REJECTED":0,"TOTAL":362.1,"PROGRESS":0,"TOTAL_FORMATTED":"362","PROGRESS_FORMATTED":"0","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"362","TRANSLATED_FORMATTED":"0","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":100,"TRANSLATED_PERC":0,"PROGRESS_PERC":0,"TRANSLATED_PERC_FORMATTED":0,"DRAFT_PERC_FORMATTED":100,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":0,"TODO_FORMATTED":"362","TODO":362,"DOWNLOAD_STATUS":"draft","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":426,"standard_wc":426,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":0,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"b878ee8583d2"}],"urls":{"password":"61b34dd4d39e","translate_url":"https://dev.matecat.com/translate/Test/en-US-mt-MT/101-61b34dd4d39e","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/Test/en-US-mt-MT/101-b878ee8583d2"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=101&password=61b34dd4d39e&download_type=all&filename=6","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=101&id_file=&password=61b34dd4d39e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/101/61b34dd4d39e/101.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null},{"id":6,"password":"59ad778c68b1","name":"tesla.docx","id_team":1,"id_assignee":1,"create_date":"2021-06-23 14:27:08","fast_analysis_wc":374,"standard_analysis_wc":357,"tm_analysis_wc":"306.40","project_slug":"tesladocx","jobs":[{"id":6,"password":"2a35d508882e","source":"en-US","target":"it-IT","sourceTxt":"English US","targetTxt":"Italian","job_first_segment":"1","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":0,"create_timestamp":1624451228,"created_at":"2021-06-23T14:27:08+02:00","create_date":"2021-06-23 14:27:08","formatted_create_date":"Jun 23, 14:27","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[],"warnings_count":3,"warning_segments":[1,3,5],"stats":{"id":6,"DRAFT":0,"TRANSLATED":84.2,"APPROVED":72,"REJECTED":0,"TOTAL":156.2,"PROGRESS":156.2,"TOTAL_FORMATTED":"156","PROGRESS_FORMATTED":"156","APPROVED_FORMATTED":"72","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"0","TRANSLATED_FORMATTED":"84","APPROVED_PERC":46.094750320102,"REJECTED_PERC":0,"DRAFT_PERC":0,"TRANSLATED_PERC":53.905249679898,"PROGRESS_PERC":100,"TRANSLATED_PERC_FORMATTED":53.91,"DRAFT_PERC_FORMATTED":0,"APPROVED_PERC_FORMATTED":46.09,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":100,"TODO_FORMATTED":"0","TODO":0,"DOWNLOAD_STATUS":"translated","revises":[{"revision_number":1,"advancement_wc":72},{"revision_number":2,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":213,"standard_wc":179,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":3,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"9084da7a0d31"},{"revision_number":2,"password":"259b6eb9e62f"}],"urls":{"password":"2a35d508882e","translate_url":"https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-2a35d508882e","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-9084da7a0d31"},{"revision_number":2,"url":"https://dev.matecat.com/revise2/tesla.docx/en-US-it-IT/6-259b6eb9e62f"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/6/2a35d508882e/6.zip"}},{"id":6,"password":"307be438d286","source":"en-US","target":"it-IT","sourceTxt":"English US","targetTxt":"Italian","job_first_segment":"9","status":"active","subject":"general","subject_printable":"General","owner":"pierluigi.dicianni@translated.net","open_threads_count":2,"create_timestamp":1625150353,"created_at":"2021-07-01T16:39:13+02:00","create_date":"2021-07-01 16:39:13","formatted_create_date":"Jul 01, 16:39","quality_overall":"excellent","pee":0,"tte":0,"private_tm_key":[],"warnings_count":2,"warning_segments":[11,12],"stats":{"id":6,"DRAFT":0,"TRANSLATED":150.2,"APPROVED":0,"REJECTED":0,"TOTAL":150.2,"PROGRESS":150.2,"TOTAL_FORMATTED":"150","PROGRESS_FORMATTED":"150","APPROVED_FORMATTED":"0","REJECTED_FORMATTED":"0","DRAFT_FORMATTED":"0","TRANSLATED_FORMATTED":"150","APPROVED_PERC":0,"REJECTED_PERC":0,"DRAFT_PERC":0,"TRANSLATED_PERC":100,"PROGRESS_PERC":100,"TRANSLATED_PERC_FORMATTED":100,"DRAFT_PERC_FORMATTED":0,"APPROVED_PERC_FORMATTED":0,"REJECTED_PERC_FORMATTED":0,"PROGRESS_PERC_FORMATTED":100,"TODO_FORMATTED":"0","TODO":0,"DOWNLOAD_STATUS":"translated","revises":[{"revision_number":1,"advancement_wc":0}]},"outsource":null,"translator":null,"total_raw_wc":213,"standard_wc":179,"quality_summary":{"equivalent_class":null,"quality_overall":"excellent","errors_count":2,"revise_issues":{}},"revise_passwords":[{"revision_number":1,"password":"e7ffa4998c82"}],"urls":{"password":"307be438d286","translate_url":"https://dev.matecat.com/translate/tesla.docx/en-US-it-IT/6-307be438d286","revise_urls":[{"revision_number":1,"url":"https://dev.matecat.com/revise/tesla.docx/en-US-it-IT/6-e7ffa4998c82"}],"original_download_url":"https://dev.matecat.com/?action=downloadOriginal&id_job=6&password=2a35d508882e&download_type=all&filename=1","translation_download_url":"https://dev.matecat.com/?action=downloadFile&id_job=6&id_file=&password=2a35d508882e&download_type=all","xliff_download_url":"https://dev.matecat.com/SDLXLIFF/6/2a35d508882e/6.zip"}}],"features":"translated,mmt,translation_versions,review_extended,second_pass_review","is_cancelled":false,"is_archived":false,"remote_file_service":null,"due_date":null,"project_info":null}]',
    ),
    dataTeam: JSON.parse(
      '{"id":1,"name":"Personal","type":"personal","created_at":"2021-06-23T12:51:48+02:00","created_by":1,"members":[{"id":1,"id_team":1,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":11}],"pending_invitations":[]}',
    ),
    dataTeams: JSON.parse(
      '[{"id":1,"name":"Personal","type":"personal","created_at":"2021-06-23T12:51:48+02:00","created_by":1,"members":[{"id":1,"id_team":1,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":11}],"pending_invitations":[]},{"id":2,"name":"Test","type":"general","created_at":"2021-07-05T15:40:56+02:00","created_by":1,"members":[{"id":2,"id_team":2,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":0}],"pending_invitations":[]}]',
    ),
    props: {
      selectedUser: 'ALL_MEMBERS_FILTER',
    },
  },
}

const getFakeProperties = (fakeProperties) => {
  const {data, dataTeam, dataTeams, props} = fakeProperties
  const projects = Immutable.fromJS(data)
  const team = Immutable.fromJS(dataTeam)
  const teams = Immutable.fromJS(dataTeams)

  return {
    projects,
    team,
    teams,
    props: {
      ...props,
      team,
      teams,
      downloadTranslationFn: () => {},
      changeJobPasswordFn: () => {},
    },
  }
}

const apiActivityMockResponse = {
  17: {
    activity: [
      {
        id: 398,
        action: 'Access to the Translate page',
        email: 'pierluigi.dicianni@translated.net',
        event_date: '2021-07-14T16:31:27+02:00',
        first_name: 'Pierluigi',
        id_job: 110,
        id_project: 17,
        ip: '172.18.0.1',
        last_name: 'Di Cianni',
        uid: 1,
      },
      {
        id: 388,
        action: 'Access to the Analyze page',
        email: 'pierluigi.dicianni@translated.net',
        event_date: '2021-07-14T16:18:44+02:00',
        first_name: 'Pierluigi',
        id_job: 0,
        id_project: 17,
        ip: '172.18.0.1',
        last_name: 'Di Cianni',
        uid: 1,
      },
    ],
  },
  11: {
    activity: [
      {
        id: 341,
        action: 'Access to the Analyze page',
        email: 'pierluigi.dicianni@translated.net',
        event_date: '2021-07-13T10:58:58+02:00',
        first_name: 'Pierluigi',
        id_job: 0,
        id_project: 11,
        ip: '172.18.0.1',
        last_name: 'Di Cianni',
        uid: 1,
      },
      {
        id: 323,
        action: 'Access to the Revise page',
        email: 'pierluigi.dicianni@translated.net',
        event_date: '2021-07-12T10:10:19+02:00',
        first_name: 'Pierluigi',
        id_job: 98,
        id_project: 11,
        ip: '172.18.0.1',
        last_name: 'Di Cianni',
        uid: 1,
      },
    ],
  },
  6: {
    activity: [
      {
        id: 312,
        action: 'Access to the Revise page',
        email: 'pierluigi.dicianni@translated.net',
        event_date: '2021-07-12T09:59:11+02:00',
        first_name: 'Pierluigi',
        id_job: 6,
        id_project: 6,
        ip: '172.18.0.1',
        last_name: 'Di Cianni',
        uid: 1,
      },
      {
        id: 202,
        action: 'Access to the Analyze page',
        email: 'pierluigi.dicianni@translated.net',
        event_date: '2021-07-02T10:56:20+02:00',
        first_name: 'Pierluigi',
        id_job: 0,
        id_project: 6,
        ip: '172.18.0.1',
        last_name: 'Di Cianni',
        uid: 1,
      },
    ],
  },
}

const executeMswServer = () => {
  mswServer.use(
    ...[
      rest.get(
        '/api/v2/activity/project/:id/:password/last',
        (req, res, ctx) => {
          const {id} = req.params
          return res(ctx.status(200), ctx.json(apiActivityMockResponse[id]))
        },
      ),
    ],
  )
}

test('Rendering elements', async () => {
  executeMswServer()
  const {props, projects} = getFakeProperties(fakeProjectsData.projects)

  render(<ProjectsContainer {...props} />)

  // set ProjectStore state
  const {data, dataTeam, dataTeams} = fakeProjectsData.projects
  act(() => {
    ManageActions.renderProjects(data, dataTeam, dataTeams)
    ManageActions.storeSelectedTeam(dataTeam)
  })

  await waitFor(() => {
    projects.map((project) => {
      expect(screen.getByText(project.get('name'))).toBeInTheDocument()
    })
  })
})

test('No projects found with team type personal', () => {
  const {props} = getFakeProperties(fakeProjectsData.projects)

  render(<ProjectsContainer {...props} />)

  expect(screen.getByText('Create Project')).toBeInTheDocument()
  expect(screen.getByText('Welcome to your Personal area')).toBeInTheDocument()
})

test('No projects found with team type general', () => {
  const {props} = getFakeProperties(fakeProjectsData.projects)

  const {dataTeam} = fakeProjectsData.projects
  const dataTeamCopy = {...dataTeam, type: 'general'}
  const team = Immutable.fromJS(dataTeamCopy)

  render(<ProjectsContainer {...{...props, team}} />)

  expect(screen.getByText(`Welcome to ${team.get('name')}`)).toBeInTheDocument()
  expect(screen.getByText('Create Project')).toBeInTheDocument()
  expect(screen.getByText('Add member')).toBeInTheDocument()
})
