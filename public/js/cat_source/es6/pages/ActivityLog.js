import React, {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper'
import Footer from '../components/footer/Footer'
import SseListener from '../sse/SseListener'
import {mountPage} from './mountPage'
import {ActivityLogTable} from '../components/activityLog/ActivityLogTable'
import {getActivityLog} from '../api/getActivityLog/getActivityLog'
import {getProject} from '../api/getProject/getProject'

const headerMountPoint = document.querySelector('header.upload-page-header')

const [projectId, password] = location.pathname
  .split('/')
  .filter((value) => value && value !== 'activityLog')

export const ActivityLogContext = createContext({})

export const ActivityLog = () => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const [project, setProject] = useState({})
  const [activityLog, setActivityLog] = useState([])

  const activityLogWithoutOrdering = useRef()

  const HeaderPortal = usePortal(headerMountPoint)

  useEffect(() => {
    Promise.all([
      getProject(projectId, password),
      getActivityLog(projectId, password),
    ]).then(([{project}, activityLog]) => {
      setProject(project)

      const mappedActivityLog = Object.values(activityLog).map((log, index) => {
        const {id_job, first_name, last_name, ...restLog} = log
        const {sourceTxt, targetTxt} =
          project.jobs.find(({id}) => id === id_job) ?? {}
        return {
          ...restLog,
          id_job: index,
          languagePair: `${sourceTxt} - ${targetTxt}`,
          userName: `${first_name} ${last_name}`,
        }
      })

      setActivityLog(mappedActivityLog)
      activityLogWithoutOrdering.current = mappedActivityLog
    })
  }, [])

  return (
    <ActivityLogContext.Provider
      value={{activityLog, activityLogWithoutOrdering, setActivityLog}}
    >
      <HeaderPortal>
        <Header
          showModals={false}
          showLinks={true}
          loggedUser={isUserLogged}
          user={isUserLogged ? userInfo.user : undefined}
        />
      </HeaderPortal>
      <div className="activity-log-content">
        <h1>Activity Log</h1>
        <h1>
          Project: {project.id} - {project.name}
        </h1>
        <h2>Project Related Activities:</h2>
        <ActivityLogTable />
      </div>
      <Footer />
      <SseListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
    </ActivityLogContext.Provider>
  )
}

mountPage({
  Component: ActivityLog,
  rootElement: document.getElementsByClassName('activity_log__page')[0],
})
