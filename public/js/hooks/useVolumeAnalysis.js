import {useEffect, useState} from 'react'
import {getJobVolumeAnalysis} from '../api/getJobVolumeAnalysis'
import {getProject, getProjectByToken} from '../api/getProject'
import {getVolumeAnalysis} from '../api/getVolumeAnalysis'
import {ANALYSIS_STATUS} from '../constants/Constants'

const POLLING_TIME = 1000
const SEGMENTS_THRESHOLD = 50000

const FINISHED_STATUSES = [ANALYSIS_STATUS.DONE, ANALYSIS_STATUS.NOT_TO_ANALYZE]
const STOP_STATUSES = [...FINISHED_STATUSES, ANALYSIS_STATUS.EMPTY]

export const getPollingInterval = (totalSegments) =>
  totalSegments > SEGMENTS_THRESHOLD ? totalSegments / 20 : POLLING_TIME

// The job page shows only the chunk the translator opened; chunkPosition
// (1-based) and chunkCount keep its place in the job ("Chunk 2 of 6")
const filterJobAnalysis = (analysis, idJob, jobPassword) => {
  const isOwnChunk = (chunk) => chunk.password === jobPassword
  const job = analysis.jobs.find(
    (job) => String(job.id) === String(idJob) && job.chunks.some(isOwnChunk),
  )
  if (!job) return {...analysis, jobs: []}
  const chunkIndex = job.chunks.findIndex(isOwnChunk)
  return {
    ...analysis,
    jobs: [
      {
        ...job,
        chunks: [job.chunks[chunkIndex]],
        chunkPosition: chunkIndex + 1,
        chunkCount: job.chunks.length,
      },
    ],
  }
}

/**
 * Loads the volume analysis and the project of the analyze page. In project
 * mode it polls until the analysis is over, then refreshes the project once.
 * The job page is reachable only once the analysis is done, so it never polls.
 *
 * @param {object} [options]
 * @param {boolean} [options.jobAnalysis]
 * @param {number|string} [options.idProject]
 * @param {string} [options.password] project password
 * @param {number|string} [options.idJob]
 * @param {string} [options.jobPassword] chunk password, job mode only
 * @param {string} [options.projectAccessToken] job mode only
 * @returns {{project: object|undefined, analysis: object|undefined, loading: boolean, error: *|null}}
 */
const useVolumeAnalysis = ({
  jobAnalysis = !!config.jobAnalysis,
  idProject = config.id_project,
  password = config.password,
  idJob = config.id_job,
  jobPassword = config.job_password,
  projectAccessToken = config.project_access_token,
} = {}) => {
  const [project, setProject] = useState()
  const [analysis, setAnalysis] = useState()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true
    let timer

    setLoading(true)
    setError(null)

    const fail = (reason) => {
      if (!active) return
      setError(reason)
      setLoading(false)
    }

    const schedulePoll = ({summary}) => {
      if (STOP_STATUSES.includes(summary?.status)) return
      timer = setTimeout(poll, getPollingInterval(summary?.total_segments))
    }

    // The analysis is complete and usable: a failed refresh keeps the last project
    const refreshProject = () =>
      getProject(idProject, password)
        .then((response) => {
          if (active && response.project) setProject(response.project)
        })
        .catch(() => {})

    const poll = () => {
      getVolumeAnalysis(idProject, password)
        .then((response) => {
          if (!active) return
          setAnalysis(response)
          if (FINISHED_STATUSES.includes(response.summary?.status)) {
            return refreshProject()
          }
          schedulePoll(response)
        })
        .catch(fail)
    }

    const initialLoad = jobAnalysis
      ? getJobVolumeAnalysis(idProject, idJob, jobPassword).then(
          async (response) => ({
            analysis: filterJobAnalysis(response, idJob, jobPassword),
            project: (await getProjectByToken(idProject, projectAccessToken))
              .project,
          }),
        )
      : getVolumeAnalysis(idProject, password).then(async (response) => ({
          analysis: response,
          project: (await getProject(idProject, password)).project,
        }))

    initialLoad
      .then(({analysis, project}) => {
        if (!active) return
        setProject(project)
        setAnalysis(analysis)
        setLoading(false)
        if (!jobAnalysis) schedulePoll(analysis)
      })
      .catch(fail)

    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [jobAnalysis, idProject, password, idJob, jobPassword, projectAccessToken])

  return {project, analysis, loading, error}
}

export default useVolumeAnalysis
