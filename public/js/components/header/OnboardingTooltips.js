import React, {lazy, Suspense, useEffect, useState} from 'react'

// react-joyride is only needed while a tour can run: users who dismissed it never download it.
const Joyride = lazy(() => import('react-joyride'))
// react-joyride's STATUS.FINISHED and STATUS.SKIPPED, inlined so the library stays out of the page.
const TOUR_DONE_STATUSES = ['finished', 'skipped']
export const ONBOARDING_PAGE = {
  HOME: 'home',
  CATTOOL: 'cattool',
}
const onBoardingLocalStorageName = 'onBoarding-tooltip-'
export const OnboardingTooltips = ({show, page}) => {
  const [run, setRun] = useState(false)
  const localStorageItem = localStorage.getItem(
    onBoardingLocalStorageName + page,
  )
  const steps = {
    home: [
      {
        target: '.popover-component-container',
        content: (
          <div className={'onboarding-tooltip'}>
            <h3 className="header">Manage your projects</h3>
            <p>
              Click here, then &quot;My projects&quot; to retrieve and manage all the
              projects you have created in Matecat.
            </p>
          </div>
        ),
        disableBeacon: true,
        placement: 'bottom-end',
      },
    ],
    cattool: [
      {
        target: '#action-three-dots',
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
        disableBeacon: true,
        placement: 'bottom-end',
      },
      {
        target: '#files-instructions',
        content: (
          <div className={'onboarding-tooltip'}>
            <h3>Instructions and references</h3>
            <p>
              You can view the instructions and references any time by clicking
              here.
            </p>
          </div>
        ),
        disableBeacon: true,
        placement: 'bottom-end',
      },
    ],
  }
  const closeCallback = (data) => {
    const {status, type} = data
    if (TOUR_DONE_STATUSES.includes(status)) {
      setRun(false)
      localStorage.setItem(onBoardingLocalStorageName + page, 1)
    }
  }

  useEffect(() => {
    if (show && !localStorageItem) {
      setRun(true)
    }
  }, [show])

  return !localStorageItem ? (
    <Suspense fallback={null}>
      <Joyride
        callback={closeCallback}
        steps={steps[page]}
        run={run}
        styles={{
          options: {
            primaryColor: '#0099cc',
          },
          tooltipContainer: {
            textAlign: 'left',
          },
        }}
      ></Joyride>
    </Suspense>
  ) : null
}
