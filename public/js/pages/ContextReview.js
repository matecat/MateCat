import React, {useContext} from 'react'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import {CookieConsent} from '../components/common/CookieConsent'

const ContextReview = () => {
  return (
    <>
      <div className="context-review-container">
        <h1>Context Review</h1>
      </div>
      <footer>
        <CookieConsent />
      </footer>
    </>
  )
}

export default ContextReview

mountPage({
  Component: ContextReview,
  rootElement: document.getElementsByClassName('context-review__page')[0],
})
