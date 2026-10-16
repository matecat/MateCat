import React, {useEffect, useId, useRef} from 'react'
import PropTypes from 'prop-types'
import {createPortal} from 'react-dom'
import Close from '../../../../img/icons/Close'
import TranslatedIcon from '../../../../img/icons/TranslatedIcon'
import styles from './OutsourceDrawer.module.scss'

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])'

// Layers that handle their own Escape: dropdown menus and the date picker
const NESTED_LAYERS =
  '[data-radix-popper-content-wrapper], [role="menu"], .react-datepicker, .react-datepicker-wrapper, .react-datepicker__input-container'

// What Tab can reach: not disabled, not hidden, not aria-hidden
const getFocusable = (root) =>
  [...root.querySelectorAll(FOCUSABLE)].filter(
    (element) =>
      !element.disabled &&
      element.offsetParent !== null &&
      !element.closest('[aria-hidden="true"]'),
  )

/**
 * Right-side dialog to buy a professional translation (analysis page and
 * Manage): the Translated header with what is being bought, and the order
 * content as children. Rendered into document.body with the rest of the page
 * inert; Escape, the backdrop and the close button dismiss it, Tab stays
 * inside, and the focus goes back where it was.
 */
export const OutsourceDrawer = ({open, onClose, item, children}) => {
  const titleId = useId()
  const dialogRef = useRef()
  const closeRef = useRef()

  // Rendered into document.body: everything else on the page goes inert.
  // The focused element is read first (inert takes the focus away), and
  // gets the focus back once the page is live again
  const portalRef = useRef(null)
  useEffect(() => {
    if (!open || !portalRef.current) return undefined
    const previousFocus = document.activeElement
    const others = [...document.body.children].filter(
      (element) =>
        element !== portalRef.current && !element.hasAttribute('inert'),
    )
    others.forEach((element) => element.setAttribute('inert', ''))
    closeRef.current?.focus()
    return () => {
      others.forEach((element) => element.removeAttribute('inert'))
      previousFocus?.focus?.()
    }
  }, [open])

  useEffect(() => {
    if (!open) return undefined
    const onKeyDown = (event) => {
      // Escape inside a menu or a calendar closes that, not the drawer
      if (event.defaultPrevented || event.target?.closest?.(NESTED_LAYERS)) {
        return
      }
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
          <div className={styles.headerTop}>
            <span className={styles.brand}>
              {/* Decorative: the label next to it names Translated */}
              <span className={styles.mark} aria-hidden="true">
                <TranslatedIcon size={36} />
              </span>
              <span className={styles.brandName}>Translated</span>
            </span>
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
          <span className={styles.eyebrow}>
            Professional translation service
          </span>
          <h2 id={titleId} className={styles.title}>
            Let Translated do it for you
          </h2>
          <span className={styles.lead}>
            Project management and translation by professionals, delivery
            guaranteed by Translated.
          </span>
          {item && <span className={styles.item}>{item}</span>}
        </div>
        <div className={styles.body}>{children}</div>
      </div>
    </div>,
    document.body,
  )
}

OutsourceDrawer.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  // What is being bought, e.g. "en-US › fr-FR · 59 Matecat weighted words"
  item: PropTypes.string,
  children: PropTypes.node,
}

export default OutsourceDrawer
