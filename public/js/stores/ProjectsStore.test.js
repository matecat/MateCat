import {fromJS} from 'immutable'
import ProjectsStore from './ProjectsStore'

describe('ProjectsStore.removeJob', () => {
  afterEach(() => {
    ProjectsStore.projects = fromJS([])
  })

  test('does not throw when the project is no longer in the store', () => {
    ProjectsStore.projects = fromJS([])
    const project = fromJS({id: 1, jobs: [{id: 10}]})
    const job = fromJS({id: 10})

    expect(() => ProjectsStore.removeJob(project, job)).not.toThrow()
    expect(ProjectsStore.projects.size).toBe(0)
  })

  test('removes only the matching job, leaving the rest of the project', () => {
    ProjectsStore.projects = fromJS([{id: 1, jobs: [{id: 10}, {id: 20}]}])
    const project = fromJS({id: 1, jobs: [{id: 10}, {id: 20}]})
    const job = fromJS({id: 10})

    ProjectsStore.removeJob(project, job)

    const remainingJobs = ProjectsStore.projects.get(0).get('jobs')
    expect(remainingJobs.size).toBe(1)
    expect(remainingJobs.get(0).get('id')).toBe(20)
  })
})
