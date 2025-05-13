import React, {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import Footer from '../components/footer/Footer'
import SocketListener from '../sse/SocketListener'
import {mountPage} from './mountPage'
import {
  ACTIVITY_LOG_COLUMNS,
  ActivityLogTable,
} from '../components/activityLog/ActivityLogTable'
import {getActivityLog} from '../api/getActivityLog/getActivityLog'
import {getProject} from '../api/getProject/getProject'
import {FilterColumn} from '../components/activityLog/FilterColumn'

const headerMountPoint = document.querySelector('header.upload-page-header')

const [projectId, password] = location.pathname
  .split('/')
  .filter((value) => value && value !== 'activityLog')

export const ActivityLogContext = createContext({})

export const ActivityLog = () => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const [project, setProject] = useState({})
  const [activityLog, setActivityLog] = useState([])
  const [filterByColumn, setFilterByColumn] = useState(
    ACTIVITY_LOG_COLUMNS.map(({id, label}) => ({id, label, query: ''})).find(
      ({id}) => id === 'email',
    ),
  )

  const activityLogWithoutOrdering = useRef()

  const HeaderPortal = usePortal(headerMountPoint)

  useEffect(() => {
    Promise.all([
      getProject(projectId, password),
      getActivityLog(projectId, password),
    ]).then(([{project}, activityLog]) => {
      setProject(project)

      const mappedActivityLog = Object.values(activityLog).map((log) => {
        const {id_job, first_name, last_name, ...restLog} = log
        const {sourceTxt, targetTxt} =
          project.jobs.find(({id}) => id === id_job) ?? {}

        return {
          ...restLog,
          id_job,
          languagePair: `${sourceTxt ? sourceTxt : ''} - ${targetTxt ? targetTxt : ''}`,
          userName: `${first_name} ${last_name}`,
        }
      })

      setActivityLog(mappedActivityLog)
      activityLogWithoutOrdering.current = mappedActivityLog
    })
  }, [])

  return (
    <ActivityLogContext.Provider
      value={{
        activityLog,
        activityLogWithoutOrdering,
        setActivityLog,
        filterByColumn,
        setFilterByColumn,
      }}
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
        <h1>
          Activity Log project: {project.id} - {project.name}
        </h1>
        <FilterColumn />
        <ActivityLogTable />
      </div>
      <Footer />
      <SocketListener
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
