import React from 'react'
import {CookieConsent} from '../common/CookieConsent'

const Footer = () => {
  return (
    <footer className="normal-foo">
      <div className="footer-body">
        <div className="info">
          <div className="image">
            <img src="public/img/logo_matecat_small.svg" />
          </div>
          <div className="description">
            Matecat is a free and open source online CAT tool. It’s free for
            translation companies, translators and enterprise users.
          </div>
        </div>
        <div className="side-info">
          <div className="item">
            <a
              href="https://site.matecat.com/terms"
              target="_blank"
              rel="noreferrer"
            >
              Terms of service
            </a>
          </div>

          <div className="item">
            <a
              href="https://site.matecat.com/open-source"
              target="_blank"
              rel="noreferrer"
            >
              Open Source
            </a>
          </div>
          <div className="item">
            <a href="/api/docs" target="_blank">
              API
            </a>
          </div>
          <div className="item">
            <a
              href="mailto:support@matecat.com"
              className="email-link"
              rel="noreferrer"
              target="_blank"
            >
              Contact us
            </a>
          </div>
        </div>
        <div className="footer-logo">
          <a href="https://www.translated.com" target="_blank">
            <img src="public/img/matecat-logo-translated.svg" width="130" />
          </a>
        </div>
      </div>
      <CookieConsent />
    </footer>
  )
}

export default Footer
