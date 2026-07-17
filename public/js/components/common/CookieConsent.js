import React, {useState, useEffect} from 'react'
import Cookies from 'js-cookie'

const COOKIE_NAME = 'cookiebanner-accepted'
const COOKIE_TTL_DAYS = 365

export const CookieConsent = () => {
  const [visible, setVisible] = useState(!Cookies.get(COOKIE_NAME))

  // useEffect(() => {
  //   if (visible) {
  //     document.getElementsByTagName('body')[0].classList.add('show-cookies-bar')
  //   }
  // }, [visible])

  const consent = () => {
    setVisible(false)
    // document
    //   .getElementsByTagName('body')[0]
    //   .classList.remove('show-cookies-bar')
    Cookies.set(COOKIE_NAME, 1, {
      expires: COOKIE_TTL_DAYS,
      secure: true,
    })
  }

  //hide cookie consent bar after 20s of navigation
  setTimeout(consent, 20000)

  return visible ? (
    <div className="cookiebanner">
      <div className="cookiebanner-close" onClick={() => consent()}>
        ✖
      </div>
      <span>
        We use cookies to enhance your experience. By continuing to visit this
        site you agree to our use of cookies.{' '}
        <a
          href="https://site.matecat.com/terms/#cookies"
          target="_blank"
          rel="noreferrer"
        >
          Learn more
        </a>
      </span>
    </div>
  ) : null
}
