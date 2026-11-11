import React, {useSyncExternalStore} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../Button/Button'
import styles from './DownloadProgress.module.scss'

/**
 * Holds the state of one download, so the notification showing it can update
 * without being added again.
 */
export const createDownloadProgressStore = () => {
  let state = {receiving: false, loaded: 0, total: 0}
  const listeners = new Set()
  return {
    getState: () => state,
    update: (next) => {
      state = {...state, ...next}
      listeners.forEach((listener) => listener())
    },
    subscribe: (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
  }
}

export const formatBytes = (bytes) => {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  if (bytes < 1024 * 1024 * 1024)
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
  return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`
}

/**
 * Body of the download notification. The server converts every file before it
 * sends the first byte, so the first phase can only say that work is going on;
 * once the file starts arriving it shows how much of it has.
 *
 * Renders phrasing content only: the notification wraps it in a <p>.
 */
export const DownloadProgress = ({store, onCancel}) => {
  const {receiving, loaded, total} = useSyncExternalStore(
    store.subscribe,
    store.getState,
  )
  const percent = total > 0 ? Math.min(100, (loaded * 100) / total) : null

  const status = !receiving
    ? 'Preparing your files'
    : total > 0
      ? `Downloading ${formatBytes(loaded)} of ${formatBytes(total)}`
      : `Downloading ${formatBytes(loaded)}`

  return (
    <span className={styles.container} data-testid="download-progress">
      <span className={styles.status}>{status}</span>
      <span
        className={styles.track}
        role="progressbar"
        aria-label={status}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={percent === null ? undefined : Math.floor(percent)}
      >
        <span
          className={percent === null ? styles.indeterminate : styles.bar}
          style={percent === null ? undefined : {width: `${percent}%`}}
        />
      </span>
      {!receiving && (
        <span className={styles.hint}>
          Large jobs can take a few minutes. Keep this page open.
        </span>
      )}
      <Button
        className={styles.cancel}
        type={BUTTON_TYPE.DEFAULT}
        mode={BUTTON_MODE.OUTLINE}
        size={BUTTON_SIZE.XSMALL}
        onClick={onCancel}
      >
        Cancel download
      </Button>
    </span>
  )
}

DownloadProgress.propTypes = {
  store: PropTypes.shape({
    getState: PropTypes.func.isRequired,
    subscribe: PropTypes.func.isRequired,
  }).isRequired,
  onCancel: PropTypes.func.isRequired,
}
