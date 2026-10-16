import React, {useId} from 'react'
import PropTypes from 'prop-types'
import {ANALYSIS_WORKFLOW_TYPES, UNIT_COUNT} from '../../../constants/Constants'
import {FileList} from '../shared/FileList'
import styles from './FilesCard.module.scss'

export const getFilesTitle = (count) =>
  count === 1 ? 'File' : `Files (${count.toLocaleString('en-US')})`

/**
 * The files of one job or chunk with their counts, once the analysis is
 * complete.
 */
export const FilesCard = ({
  files,
  payableRates,
  workflowType,
  countUnit = UNIT_COUNT.WORDS,
  done,
  reloadWhenDone = false,
}) => {
  const titleId = useId()
  const unit = countUnit === UNIT_COUNT.CHARACTERS ? 'characters' : 'words'
  return (
    <section className={styles.card} aria-labelledby={titleId}>
      <h2 id={titleId} className={styles.title}>
        {getFilesTitle(files.length)}
      </h2>
      {done && workflowType ? (
        <FileList
          files={files}
          payableRates={payableRates}
          workflowType={workflowType}
          countUnit={countUnit}
        />
      ) : (
        <p className={styles.message}>
          {reloadWhenDone
            ? `The analysis is still running. Reload the page once it is complete to see the ${unit} per file.`
            : `The ${unit} per file appear when the analysis is complete.`}
        </p>
      )}
    </section>
  )
}

FilesCard.propTypes = {
  files: PropTypes.array.isRequired,
  payableRates: PropTypes.object,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES)),
  countUnit: PropTypes.oneOf(Object.values(UNIT_COUNT)),
  // The analysis is complete
  done: PropTypes.bool,
  // A page that does not refresh itself (the job page): ask for a reload
  reloadWhenDone: PropTypes.bool,
}
