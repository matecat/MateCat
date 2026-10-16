import React, {createContext, useContext} from 'react'
import PropTypes from 'prop-types'
import styles from './AnalysisPage.module.scss'

export const ANALYSIS_PAGE_TITLE = {
  PROJECT: 'Project analysis',
  JOB: 'Job analysis',
}

// For the page's scroll container (#analyze-container): see the SCSS
export const SCROLL_CONTAINER_CLASS = styles.scrollContainer

// A message for the top of whichever analysis page is on screen, e.g. a
// refresh that failed while the last numbers stay visible
export const AnalysisNoticeContext = createContext(null)

/**
 * The frame of every analysis page: the page heading, the page notice if
 * any, and the spacing that #analyze-container no longer has, so sticky rows
 * stick flush to it.
 */
export const AnalysisPage = ({title, children}) => {
  const notice = useContext(AnalysisNoticeContext)
  return (
    <div className={styles.page}>
      <h1 className={styles.title}>{title}</h1>
      {notice && (
        <p className={styles.notice} role="status">
          {notice}
        </p>
      )}
      {children}
    </div>
  )
}

AnalysisPage.propTypes = {
  title: PropTypes.string.isRequired,
  children: PropTypes.node,
}
