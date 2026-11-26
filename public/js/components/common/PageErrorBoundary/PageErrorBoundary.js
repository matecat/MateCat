import React, {useEffect, useRef} from 'react'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../Button/Button'
import styles from './PageErrorBoundary.module.scss'

// Rendered when the page itself has crashed, so it reads no context or store:
// they may be what failed.
const PageErrorFallback = () => {
  const reloadRef = useRef(null)
  // Each installation sets its own support contact, which may not be an address
  const supportMail = window.config?.support_mail

  useEffect(() => {
    reloadRef.current?.focus()
  }, [])

  return (
    <div className={styles.wrapper}>
      <main className={styles['status-page__card']}>
        <a
          href="/"
          className={styles['status-page__logo']}
          aria-label="Matecat"
        />
        <h1 className={styles['status-page__title']}>Something went wrong</h1>
        <div className={styles['status-page__text']}>
          <p>
            An unexpected error stopped this page. Reload it to continue;
            changes that were already saved are kept.
          </p>
          {supportMail && (
            <p>
              If it keeps happening, write to{' '}
              {supportMail.includes('@') ? (
                <a href={`mailto:${supportMail}`}>{supportMail}</a>
              ) : (
                supportMail
              )}
              .
            </p>
          )}
        </div>
        <Button
          ref={reloadRef}
          type={BUTTON_TYPE.PRIMARY}
          size={BUTTON_SIZE.BIG}
          tabIndex={0}
          onClick={() => window.location.reload()}
        >
          Reload page
        </Button>
      </main>
    </div>
  )
}

// Replaces a crashed page with the fallback instead of an empty root. The
// error is reported by the root's onCaughtError (rootErrorOptions), so the
// boundary only switches what is rendered.
// Error boundaries can only be class components
// eslint-disable-next-line no-restricted-syntax
export class PageErrorBoundary extends React.Component {
  state = {failed: false}

  static getDerivedStateFromError() {
    return {failed: true}
  }

  render() {
    return this.state.failed ? <PageErrorFallback /> : this.props.children
  }
}
