import React, {lazy, Suspense, useEffect, useRef, useState} from 'react'
import {ErrorBoundary} from '../common/ErrorBoundary'

// react-joyride is only needed while a tour can run: users who dismissed it never download it.
const Joyride = lazy(() =>
  import('react-joyride').then(({Joyride}) => ({default: Joyride})),
)
// The tour is optional: if its code cannot be loaded (a chunk gone after a
// deploy, a network failure) it is left out instead of taking the page down
// to the page error card. The root's onCaughtError still passes the error on to
// error tracking, which may drop a network failure it is set to ignore.
// react-joyride's STATUS.FINISHED and STATUS.SKIPPED, inlined so the library stays out of the page.
const TOUR_DONE_STATUSES = ['finished', 'skipped']
// react-joyride's EVENTS.TOOLTIP: a step's tooltip was shown to the user.
const TOOLTIP_SHOWN_EVENT = 'tooltip'
export const ONBOARDING_PAGE = {
  HOME: 'home',
  CATTOOL: 'cattool',
}
const onBoardingLocalStorageName = 'onBoarding-tooltip-'
export const OnboardingTooltips = ({show, page}) => {
  const [run, setRun] = useState(false)
  const tooltipShownRef = useRef(false)
  const localStorageItem = localStorage.getItem(
    onBoardingLocalStorageName + page,
  )
  const steps = {
    home: [
      {
        target: '[data-onboarding="user-menu"]',
        content: (
          <div className={'onboarding-tooltip'}>
            <h3 className="header">Manage your projects</h3>
            <p>
              Click here, then &quot;My projects&quot; to retrieve and manage all the
              projects you have created in Matecat.
            </p>
          </div>
        ),
        skipBeacon: true,
        placement: 'bottom-end',
      },
    ],
    cattool: [
      {
        target: '[data-onboarding="cattool-actions-menu"]',
        content: (
          <div className={'onboarding-tooltip'}>
            <h3>Easier tool navigation and new shortcuts</h3>
            <p>Click here to navigate to:</p>
            <ul>
              <li>- Translate/Revise mode</li>
              <li>- Volume analysis</li>
              <li>- XLIFF-to-target converter</li>
              <li>- Shortcut guide</li>
            </ul>
          </div>
        ),
        skipBeacon: true,
        placement: 'bottom-end',
      },
      {
        target: '[data-onboarding="job-instructions"]',
        content: (
          <div className={'onboarding-tooltip'}>
            <h3>Instructions and references</h3>
            <p>
              You can view the instructions and references any time by clicking
              here.
            </p>
          </div>
        ),
        skipBeacon: true,
        placement: 'bottom-end',
      },
    ],
  }
  const closeCallback = (data) => {
    const {status, type} = data
    if (type === TOOLTIP_SHOWN_EVENT) {
      tooltipShownRef.current = true
    }
    if (TOUR_DONE_STATUSES.includes(status)) {
      setRun(false)
      // A tour also ends when every target is missing: it was never seen, so
      // leave it to run again on a page that has them.
      if (tooltipShownRef.current) {
        localStorage.setItem(onBoardingLocalStorageName + page, 1)
      }
    }
  }

  useEffect(() => {
    if (show && !localStorageItem) {
      setRun(true)
    }
  }, [show])

  return !localStorageItem ? (
    <ErrorBoundary fallback={null}>
      <Suspense fallback={null}>
        <Joyride
          onEvent={closeCallback}
          steps={steps[page]}
          run={run}
          // Text and overlay colours as react-joyride 2 drew them. With the
          // target blocked, a click on the highlighted element is absorbed and
          // does nothing, so the element cannot be used during the tour; only a
          // click on the dark overlay around it closes the step.
          options={{
            primaryColor: '#0099cc',
            textColor: '#333',
            overlayColor: 'rgba(0, 0, 0, 0.5)',
            blockTargetInteraction: true,
          }}
          styles={{
            tooltipContainer: {
              textAlign: 'left',
            },
          }}
        ></Joyride>
      </Suspense>
    </ErrorBoundary>
  ) : null
}
