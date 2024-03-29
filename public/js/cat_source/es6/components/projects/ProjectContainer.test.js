import {render, screen, waitFor} from '@testing-library/react'
import React from 'react'
import {createRoot} from 'react-dom/client'
import ProjectContainer from './ProjectContainer'
import Immutable from 'immutable'
import {http, HttpResponse} from 'msw'

import {mswServer} from '../../../../../mocks/mswServer'

// create modal div
const modalElement = document.createElement('div')
modalElement.id = 'modal'
document.body.appendChild(modalElement)
const mountPoint = createRoot(modalElement)
afterAll(() => mountPoint.unmount())

require('../../../../common')
global.config = {
  enable_outsource: 1,
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: 2,
}

const fakeProjectsData = {
  project: {
    data: {
      id: 11,
      password: 'c19166d0d09b',
      name: 'Test Project',
      id_team: 1,
      id_assignee: 1,
      create_date: '2021-07-12 10:05:02',
      fast_analysis_wc: 374,
      standard_analysis_wc: 1704,
      tm_analysis_wc: '1427.09',
      project_slug: 'test',
      jobs: [
        {
          id: 98,
          password: 'defe9aad39e3',
          source: 'en-US',
          target: 'la-XN',
          sourceTxt: 'English US',
          targetTxt: 'Latin',
          job_first_segment: '96',
          status: 'active',
          subject: 'general',
          subject_printable: 'General',
          owner: 'pierluigi.dicianni@translated.net',
          open_threads_count: 0,
          create_timestamp: 1626077103,
          created_at: '2021-07-12T10:05:03+02:00',
          create_date: '2021-07-12 10:05:03',
          formatted_create_date: 'Jul 12, 10:05',
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
          revise_passwords: [{revision_number: 1, password: '25a2513040eb'}],
          urls: {
            password: 'defe9aad39e3',
            translate_url:
              'https://dev.matecat.com/translate/Test/en-US-la-XN/98-defe9aad39e3',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/Test/en-US-la-XN/98-25a2513040eb',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=98&password=defe9aad39e3&download_type=all&filename=6',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=98&id_file=&password=defe9aad39e3&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/SDLXLIFF/98/defe9aad39e3/98.zip',
          },
        },
        {
          id: 99,
          password: '278d3f0a255b',
          source: 'en-US',
          target: 'es-ES',
          sourceTxt: 'English US',
          targetTxt: 'Spanish',
          job_first_segment: '96',
          status: 'active',
          subject: 'general',
          subject_printable: 'General',
          owner: 'pierluigi.dicianni@translated.net',
          open_threads_count: 0,
          create_timestamp: 1626077103,
          created_at: '2021-07-12T10:05:03+02:00',
          create_date: '2021-07-12 10:05:03',
          formatted_create_date: 'Jul 12, 10:05',
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
          revise_passwords: [{revision_number: 1, password: '7f37feb2f216'}],
          urls: {
            password: '278d3f0a255b',
            translate_url:
              'https://dev.matecat.com/translate/Test/en-US-es-ES/99-278d3f0a255b',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/Test/en-US-es-ES/99-7f37feb2f216',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=99&password=278d3f0a255b&download_type=all&filename=6',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=99&id_file=&password=278d3f0a255b&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/SDLXLIFF/99/278d3f0a255b/99.zip',
          },
        },
        {
          id: 100,
          password: 'b9d1cf9c3a04',
          source: 'en-US',
          target: 'en-GB',
          sourceTxt: 'English US',
          targetTxt: 'English',
          job_first_segment: '96',
          status: 'active',
          subject: 'general',
          subject_printable: 'General',
          owner: 'pierluigi.dicianni@translated.net',
          open_threads_count: 0,
          create_timestamp: 1626077103,
          created_at: '2021-07-12T10:05:03+02:00',
          create_date: '2021-07-12 10:05:03',
          formatted_create_date: 'Jul 12, 10:05',
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
          revise_passwords: [{revision_number: 1, password: '1236458b8d2d'}],
          urls: {
            password: 'b9d1cf9c3a04',
            translate_url:
              'https://dev.matecat.com/translate/Test/en-US-en-GB/100-b9d1cf9c3a04',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/Test/en-US-en-GB/100-1236458b8d2d',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=100&password=b9d1cf9c3a04&download_type=all&filename=6',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=100&id_file=&password=b9d1cf9c3a04&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/SDLXLIFF/100/b9d1cf9c3a04/100.zip',
          },
        },
        {
          id: 101,
          password: '61b34dd4d39e',
          source: 'en-US',
          target: 'mt-MT',
          sourceTxt: 'English US',
          targetTxt: 'Maltese',
          job_first_segment: '96',
          status: 'active',
          subject: 'general',
          subject_printable: 'General',
          owner: 'pierluigi.dicianni@translated.net',
          open_threads_count: 0,
          create_timestamp: 1626077103,
          created_at: '2021-07-12T10:05:03+02:00',
          create_date: '2021-07-12 10:05:03',
          formatted_create_date: 'Jul 12, 10:05',
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
          revise_passwords: [{revision_number: 1, password: 'b878ee8583d2'}],
          urls: {
            password: '61b34dd4d39e',
            translate_url:
              'https://dev.matecat.com/translate/Test/en-US-mt-MT/101-61b34dd4d39e',
            revise_urls: [
              {
                revision_number: 1,
                url: 'https://dev.matecat.com/revise/Test/en-US-mt-MT/101-b878ee8583d2',
              },
            ],
            original_download_url:
              'https://dev.matecat.com/?action=downloadOriginal&id_job=101&password=61b34dd4d39e&download_type=all&filename=6',
            translation_download_url:
              'https://dev.matecat.com/?action=downloadFile&id_job=101&id_file=&password=61b34dd4d39e&download_type=all',
            xliff_download_url:
              'https://dev.matecat.com/SDLXLIFF/101/61b34dd4d39e/101.zip',
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
  const project = Immutable.fromJS(data)
  const team = Immutable.fromJS(dataTeam)
  const teams = Immutable.fromJS(dataTeams)

  return {
    project,
    team,
    teams,
    props: {
      ...props,
      project,
      team,
      teams,
      downloadTranslationFn: () => {},
      changeJobPasswordFn: () => {},
    },
  }
}

const apiActivityMockResponse = {
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
}

const executeMswServer = () => {
  mswServer.use(
    ...[
      http.get(
        config.basepath + 'api/v2/activity/project/:id/:password/last',
        () => {
          return HttpResponse.json(apiActivityMockResponse)
        },
      ),
    ],
  )
}

const getActivityLogUrl = (projectId, password) =>
  `/activityLog/${projectId}/${password}`
const createActivityLogUrl = (project) => {
  return getActivityLogUrl(project.get('id'), project.get('password'))
}

test('Rendering elements', async () => {
  executeMswServer()
  const {props, project, teams, team} = getFakeProperties(
    fakeProjectsData.project,
  )
  render(<ProjectContainer {...props} />)

  expect(screen.getByText(`(${project.get('id')})`)).toBeInTheDocument()
  const projectName = screen.getByTestId('project-name').textContent
  expect(projectName).toBe(project.get('name'))

  await waitFor(() => {
    expect(screen.getByTestId('last-action-activity')).toBeInTheDocument()

    const {action, event_date, first_name} = apiActivityMockResponse.activity[0]
    const lastActionContent = `Last action: ${action} on ${new Date(
      event_date,
    ).toDateString()}`
    expect(screen.getByText(lastActionContent)).toBeInTheDocument()

    expect(screen.getByText(`by ${first_name}`)).toBeInTheDocument()

    const href = screen.getByTestId('last-action-activity').getAttribute('href')
    expect(href).toBe(createActivityLogUrl(project))

    // check teams menu items
    teams.map((team) => {
      const elements = screen.getAllByText(team.get('name'))
      elements.forEach((element) => expect(element).toBeInTheDocument())
    })
    team.get('members').map((member) => {
      const userInfo = member.get('user')
      const elements = screen.getAllByText(
        userInfo.get('first_name') + ' ' + userInfo.get('last_name'),
      )
      elements.forEach((element) => expect(element).toBeInTheDocument())
    })

    // check project menu items
    expect(screen.getByText('Activity Log')).toBeInTheDocument()
    expect(screen.getByText('Archive project')).toBeInTheDocument()
    expect(screen.getByText('Cancel project')).toBeInTheDocument()

    // check items list
    const jobs = project.get('jobs')
    jobs.map((job) =>
      expect(screen.getByTestId(job.get('id'))).toBeInTheDocument(),
    )
  })
})
