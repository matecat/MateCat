import React, {useEffect, useRef} from 'react'
import CommonUtils from '../../utils/commonUtils'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'

const OutsourceInfo = () => {
  const sliderRef = useRef(null)
  const sliderIndexRef = useRef(0)
  const timeoutSliderRef = useRef(null)

  const startSlider = () => {
    const slider = sliderRef.current
    if (!slider) return

    const items = slider.getElementsByClassName('customer-box-info')
    const pointers = slider.getElementsByClassName('pointer')

    clearTimeout(timeoutSliderRef.current)

    for (let i = 0; i < items.length; i++) {
      items[i].classList.remove('fade-in')
      pointers[i].classList.remove('active')
    }

    sliderIndexRef.current++
    if (sliderIndexRef.current > items.length) {
      sliderIndexRef.current = 1
    }

    items[sliderIndexRef.current - 1].classList.add('fade-in')
    pointers[sliderIndexRef.current - 1].classList.add('active')

    timeoutSliderRef.current = setTimeout(startSlider, 6000)
  }

  const slideItem = (n) => {
    sliderIndexRef.current = n - 1
    startSlider()
  }

  useEffect(() => {
    startSlider()
    return () => {
      clearTimeout(timeoutSliderRef.current)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <div className="customer-request sixteen wide column">
      <div className="ui grid">
        <div className="customer-box" ref={sliderRef}>
          <div className="title-pointer">
            <div className="pointers">
              <div className="pointer active" onClick={() => slideItem(1)} />
              <div className="pointer" onClick={() => slideItem(2)} />
              <div className="pointer" onClick={() => slideItem(3)} />
              <div className="pointer" onClick={() => slideItem(4)} />
            </div>
          </div>
          <div className="slider-box">
            <div className="appendix">
              <i className="icon-quote-client icon" />
            </div>
            <div className="customer-box-info">
              <div className="customer-text">
                We love how easy it is to assign a translation job and to know
                exactly how much it will cost and when we will receive it. You
                never miss a deadline! Thanks a lot.
              </div>
              <div className="customer-info">
                <div className="customer-photo">
                  <img
                    className="ui circular image"
                    src="../../public/img/outsource-clients/testimonial-sandra-alonso.jpg"
                  />
                </div>
                <div className="customer-name">Sandra Alonso</div>
                <div className="customer-role">- Project Manager</div>
              </div>
              <div className="customer-corporate-logo">
                <img src="../../public/img/outsource-clients/testimonial-responsive-translation.png" />
              </div>
            </div>
            <div className="customer-box-info">
              <div className="customer-text">
                I always receive translations back, exactly as I want. Great
                service, well worth trying out. I now want to use it for further
                languages and for projects with a tight delivery.
              </div>
              <div className="customer-info">
                <div className="customer-photo">
                  <img
                    className="ui circular image"
                    src="../../public/img/outsource-clients/testimonial-kennet.jpg"
                  />
                </div>
                <div className="customer-name">Kenneth van der Vlugt</div>
                <div className="customer-role">- Translator</div>
              </div>
              <div className="customer-corporate-logo">
                <img src="../../public/img/outsource-clients/testimonial-topdutch2.png" />
              </div>
            </div>
            <div className="customer-box-info">
              <div className="customer-text">
                Managing many file formats also simplifies our whole workflow,
                before and after delivery to the customer. Thanks for the
                excellent tool!
              </div>
              <div className="customer-info">
                <div className="customer-photo">
                  <img
                    className="ui circular image"
                    src="../../public/img/outsource-clients/testimonial-bruno-spagna.jpg"
                  />
                </div>
                <div className="customer-name">Bruno Spagna</div>
                <div className="customer-role">- IT Manager</div>
              </div>
              <div className="customer-corporate-logo">
                <img src="../../public/img/outsource-clients/testimonial-intradoc.png" />
              </div>
            </div>
            <div className="customer-box-info">
              <div className="customer-text">
                Sometimes I even split projects, outsource only a part, and then
                immediately assign the revision to a third person.
              </div>
              <div className="customer-info">
                <div className="customer-photo">
                  <img
                    className="ui circular image"
                    src="../../public/img/outsource-clients/testimonial-roberto-coppola.jpg"
                  />
                </div>
                <div className="customer-name">Roberto Coppola</div>
                <div className="customer-role">- Export adviser</div>
              </div>
              <div className="customer-corporate-logo">
                <img
                  className="c-export"
                  src="../../public/img/outsource-clients/testimonial-consulenza-export4.png"
                />
              </div>
            </div>
          </div>
        </div>
        <div className="request-box">
          <div className="title-request">
            <h3>Have a specific request?</h3>
          </div>
          <div className="request-info-box">
            <div className="mobile-mail-box account-box">
              <div className="ui relaxed horizontal list">
                <div className="item call">
                  <i className="big icon-phone2 middle aligned icon" />
                  <div className="content">
                    <div className="header">Call us:</div>
                    <a className="description" href="tel:+390690254001">
                      +39 06 90 254 001
                    </a>
                  </div>
                </div>
                <div className="item send-email">
                  <i className="big icon-envelope-o middle aligned icon" />
                  <div className="content">
                    <div className="header">Send us an email:</div>
                    <a className="description" href="mailto:info@matecat.com">
                      info@matecat.com
                    </a>
                  </div>
                </div>
                <div className="item open-chat">
                  <div className="content">
                    <Button
                      className="support-tip-button"
                      onClick={() => {
                        CommonUtils.dispatchCustomEvent('openChat')
                      }}
                    >
                      <i className="big icon-uniE970 middle aligned icon" />
                      Open chat
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default OutsourceInfo
