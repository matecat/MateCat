import React, {useCallback, useEffect, useId, useMemo, useRef} from 'react'
import PropTypes from 'prop-types'
import {createPortal} from 'react-dom'
import {fromJS} from 'immutable'
import OutsourceContainer from '../../outsource/OutsourceContainer'
import Close from '../../../../img/icons/Close'
import styles from './BuyTranslationDrawer.module.scss'

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])'

// What Tab can reach: not disabled, not hidden, not aria-hidden
const getFocusable = (root) =>
  [...root.querySelectorAll(FOCUSABLE)].filter(
    (element) =>
      !element.disabled &&
      element.offsetParent !== null &&
      !element.closest('[aria-hidden="true"]'),
  )

/**
 * Right-side dialog with the existing order form (OutsourceVendor) for one job
 * or chunk, so ordering works as it always did. Escape, the backdrop and the
 * close button dismiss it; Tab stays inside while it is open.
 */
export const BuyTranslationDrawer = ({
  open,
  onClose,
  project,
  projectJob,
  title,
  subtitle,
  weightedWords,
}) => {
  const titleId = useId()
  const dialogRef = useRef()
  const closeRef = useRef()

  const immutableProject = useMemo(() => fromJS(project), [project])
  const immutableJob = useMemo(() => fromJS(projectJob), [projectJob])

  // Rendered into document.body: everything else on the page goes inert.
  // Declared first so the page is live again before the focus goes back
  const portalRef = useRef(null)
  useEffect(() => {
    if (!open || !portalRef.current) return undefined
    const others = [...document.body.children].filter(
      (element) =>
        element !== portalRef.current && !element.hasAttribute('inert'),
    )
    others.forEach((element) => element.setAttribute('inert', ''))
    return () => others.forEach((element) => element.removeAttribute('inert'))
  }, [open])

  useEffect(() => {
    if (!open) return undefined
    const previousFocus = document.activeElement
    closeRef.current?.focus()
    return () => previousFocus?.focus?.()
  }, [open])

  useEffect(() => {
    if (!open) return undefined
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.stopPropagation()
        onClose()
        return
      }
      if (event.key !== 'Tab' || !dialogRef.current) return
      const focusable = getFocusable(dialogRef.current)
      if (focusable.length === 0) return
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      } else if (!dialogRef.current.contains(document.activeElement)) {
        event.preventDefault()
        first.focus()
      }
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [open, onClose])

  // The order form closes on any click outside itself: the drawer's own frame
  // (title, padding) is not outside the dialog
  const handleClickOutsideForm = useCallback(
    (event) => {
      if (event?.target && dialogRef.current?.contains(event.target)) return
      onClose()
    },
    [onClose],
  )

  if (!open) return null

  return createPortal(
    <div ref={portalRef} className={styles.overlay}>
      <div
        className={styles.backdrop}
        data-testid="buy-translation-backdrop"
        onClick={onClose}
      />
      <div
        ref={dialogRef}
        className={styles.drawer}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <div className={styles.header}>
          <div>
            <h2 id={titleId} className={styles.title}>
              {title}
            </h2>
            {subtitle && <p className={styles.subtitle}>{subtitle}</p>}
          </div>
          <button
            ref={closeRef}
            type="button"
            className={styles.close}
            aria-label="Close"
            onClick={onClose}
          >
            <Close size={20} />
          </button>
        </div>
        <div className={styles.body}>
          <OutsourceContainer
            project={immutableProject}
            job={immutableJob}
            openOutsource={true}
            showTranslatorBox={false}
            extendedView={true}
            idJobLabel={`${projectJob.id}-${projectJob.password}`}
            standardWC={weightedWords}
            onClickOutside={handleClickOutsideForm}
          />
        </div>
      </div>
    </div>,
    document.body,
  )
}

BuyTranslationDrawer.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  // Plain project, as returned by the project API
  project: PropTypes.object.isRequired,
  // The job of project.jobs for the chunk to buy
  projectJob: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
    password: PropTypes.string.isRequired,
  }).isRequired,
  title: PropTypes.string.isRequired,
  subtitle: PropTypes.string,
  weightedWords: PropTypes.number,
}
