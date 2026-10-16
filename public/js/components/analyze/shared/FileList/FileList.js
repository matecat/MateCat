import React, {Fragment, useId, useState} from 'react'
import PropTypes from 'prop-types'
import {
  ANALYSIS_WORKFLOW_TYPES,
  UNIT_COUNT,
} from '../../../../constants/Constants'
import {formatCount, getBuckets} from '../../utils/buckets'
import styles from './FileList.module.scss'

// Above this many files the list gets a search box
export const FILE_SEARCH_THRESHOLD = 8

export const splitFileName = (name = '') => {
  const index = Math.max(name.lastIndexOf('/'), name.lastIndexOf('\\'))
  return index < 0
    ? {baseName: name, folder: ''}
    : {baseName: name.slice(index + 1), folder: name.slice(0, index)}
}

/**
 * The files of a chunk with their raw and weighted counts. Each file expands
 * to its non-empty analysis buckets.
 */
export const FileList = ({
  files,
  payableRates,
  workflowType,
  countUnit = UNIT_COUNT.WORDS,
}) => {
  const [query, setQuery] = useState('')
  const [expanded, setExpanded] = useState(() => new Set())
  const idPrefix = useId()

  const searchable = files.length > FILE_SEARCH_THRESHOLD
  const unit = countUnit === UNIT_COUNT.CHARACTERS ? 'characters' : 'words'
  const needle = query.trim().toLowerCase()
  const visibleFiles =
    searchable && needle
      ? files.filter((file) => (file.name ?? '').toLowerCase().includes(needle))
      : files

  const toggle = (id) =>
    setExpanded((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })

  return (
    <div className={styles.component}>
      {searchable && (
        <div className={styles.searchBox}>
          <label htmlFor={`${idPrefix}-search`}>Find a file</label>
          <input
            id={`${idPrefix}-search`}
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
          <p className={styles.matchCount} aria-live="polite">
            {`${visibleFiles.length} of ${files.length} files match`}
          </p>
        </div>
      )}
      <div className={styles.scroll}>
        <table className={styles.table}>
          <thead>
            <tr>
              <th scope="col">File</th>
              <th scope="col">Raw</th>
              <th scope="col">Weighted</th>
            </tr>
          </thead>
          <tbody>
            {visibleFiles.length === 0 && (
              <tr>
                <td colSpan={3} className={styles.noMatch}>
                  No files match
                </td>
              </tr>
            )}
            {visibleFiles.map((file) => {
              const {baseName, folder} = splitFileName(file.name)
              const isOpen = expanded.has(file.id)
              const detailId = `${idPrefix}-file-${file.id}`
              const buckets = isOpen
                ? getBuckets({
                    matches: file.matches,
                    payableRates,
                    workflowType,
                  }).filter((bucket) => !bucket.empty)
                : []

              return (
                <Fragment key={file.id}>
                  <tr className={isOpen ? styles.open : undefined}>
                    <th scope="row">
                      <button
                        type="button"
                        className={styles.fileButton}
                        aria-expanded={isOpen}
                        aria-controls={isOpen ? detailId : undefined}
                        onClick={() => toggle(file.id)}
                      >
                        <span className={styles.baseName}>{baseName}</span>
                        {folder && (
                          <span className={styles.folder}>{folder}</span>
                        )}
                      </button>
                    </th>
                    <td>{formatCount(file.totals?.raw)}</td>
                    <td>{formatCount(file.totals?.equivalent)}</td>
                  </tr>
                  {isOpen && (
                    <tr id={detailId} className={styles.detail}>
                      <td colSpan={3}>
                        {buckets.length > 0 ? (
                          <table className={styles.buckets}>
                            <caption className={styles.hidden}>
                              {`${baseName} by analysis bucket`}
                            </caption>
                            <thead className={styles.hidden}>
                              <tr>
                                <th scope="col">Bucket</th>
                                <th scope="col">Raw</th>
                                <th scope="col">Weighted</th>
                              </tr>
                            </thead>
                            <tbody>
                              {buckets.map(({type, label, raw, equivalent}) => (
                                <tr key={type}>
                                  <th scope="row">{label}</th>
                                  <td>{formatCount(raw)}</td>
                                  <td>{formatCount(equivalent)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        ) : (
                          <p className={styles.noBuckets}>
                            {`No ${unit} to analyze in this file`}
                          </p>
                        )}
                      </td>
                    </tr>
                  )}
                </Fragment>
              )
            })}
          </tbody>
        </table>
      </div>
    </div>
  )
}

FileList.propTypes = {
  files: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      name: PropTypes.string,
      totals: PropTypes.shape({
        raw: PropTypes.number,
        equivalent: PropTypes.number,
      }),
      matches: PropTypes.arrayOf(
        PropTypes.shape({
          type: PropTypes.string.isRequired,
          raw: PropTypes.number,
          equivalent: PropTypes.number,
        }),
      ),
    }),
  ).isRequired,
  payableRates: PropTypes.object,
  workflowType: PropTypes.oneOf(Object.values(ANALYSIS_WORKFLOW_TYPES))
    .isRequired,
  countUnit: PropTypes.oneOf(Object.values(UNIT_COUNT)),
}
