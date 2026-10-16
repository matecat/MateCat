import React, {useId} from 'react'
import PropTypes from 'prop-types'
import styles from './AnalysisProblemCard.module.scss'

export const ANALYSIS_PROBLEM = {
  NOT_ANALYZED: 'notAnalyzed',
  EMPTY: 'empty',
  JOB_NOT_FOUND: 'jobNotFound',
  LOAD_ERROR: 'loadError',
}

const MESSAGES = {
  [ANALYSIS_PROBLEM.NOT_ANALYZED]: {
    title: 'We could not analyze this project',
    text: 'Something went wrong while analyzing it, so there are no word counts to show. Contact support and include the project ID so we can look into it.',
  },
  [ANALYSIS_PROBLEM.EMPTY]: {
    title: 'There is no text to translate in these files',
    text: 'The files were imported, but none of them contains translatable text. Check the files and the file import settings, or contact support if you think this is a mistake.',
  },
  [ANALYSIS_PROBLEM.JOB_NOT_FOUND]: {
    title: 'This job is not in the analysis',
    text: 'The link may be out of date: the job may have been split, merged or deleted. Ask the project owner for a new link.',
  },
  [ANALYSIS_PROBLEM.LOAD_ERROR]: {
    title: 'We could not load the analysis',
    text: 'Reload the page to try again. If it keeps failing, contact support.',
  },
}

/**
 * Why there is no analysis to show, and who to ask.
 */
export const AnalysisProblemCard = ({problem}) => {
  const titleId = useId()
  const {title, text} =
    MESSAGES[problem] ?? MESSAGES[ANALYSIS_PROBLEM.LOAD_ERROR]
  const supportMail = config.support_mail ?? ''

  return (
    <section className={styles.card} role="alert" aria-labelledby={titleId}>
      <h2 id={titleId} className={styles.title}>
        {title}
      </h2>
      <p className={styles.text}>{text}</p>
      {supportMail.includes('@') ? (
        <a className={styles.contact} href={`mailto:${supportMail}`}>
          Contact support
        </a>
      ) : (
        supportMail && <p className={styles.text}>{supportMail}</p>
      )}
    </section>
  )
}

AnalysisProblemCard.propTypes = {
  problem: PropTypes.oneOf(Object.values(ANALYSIS_PROBLEM)).isRequired,
}
