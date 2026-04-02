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
    <div className="customer-request">
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
          <div className="quote">&ldquo;</div>
          <div className="customer-box-info">
            <div className="customer-text">
              We love how easy it is to assign a translation job and to know
              exactly how much it will cost and when we will receive it. You
              never miss a deadline! Thanks a lot.
            </div>
            <div className="customer-info">
              <div>
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
          </div>
          <div className="customer-box-info">
            <div className="customer-text">
              I always receive translations back, exactly as I want. Great
              service, well worth trying out. I now want to use it for further
              languages and for projects with a tight delivery.
            </div>
            <div className="customer-info">
              <div>
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
          </div>
          <div className="customer-box-info">
            <div className="customer-text">
              Managing many file formats also simplifies our whole workflow,
              before and after delivery to the customer. Thanks for the
              excellent tool!
            </div>
            <div className="customer-info">
              <div>
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
          </div>
          <div className="customer-box-info">
            <div className="customer-text">
              Sometimes I even split projects, outsource only a part, and then
              immediately assign the revision to a third person.
            </div>
            <div className="customer-info">
              <div>
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
      </div>
      <div className="request-box">
        <div className="title-request">Have a specific request?</div>
        <div className="request-info-box">
          <div className="item call">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
            >
              <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6.90502 3.66234C6.62124 3.5188 6.2861 3.5188 6.00232 3.66234C5.8956 3.71632 5.76256 3.83158 5.17554 4.41861L5.0179 4.57624C4.45769 5.13646 4.30668 5.29848 4.18597 5.51336C4.04606 5.76243 3.92884 6.21317 3.92969 6.49884C3.93045 6.75305 3.96798 6.90614 4.13324 7.48839C4.94884 10.362 6.48744 13.0737 8.75177 15.3381C11.0161 17.6024 13.7279 19.141 16.6014 19.9566C17.1837 20.1219 17.3368 20.1594 17.591 20.1601C17.8767 20.161 18.3274 20.0438 18.5765 19.9039C18.7914 19.7831 18.9534 19.6321 19.5136 19.0719L19.6712 18.9143C20.2582 18.3273 20.3735 18.1942 20.4275 18.0875C20.571 17.8037 20.571 17.4686 20.4275 17.1848C20.3735 17.0781 20.2583 16.9451 19.6712 16.358L19.4764 16.1632C19.0907 15.7775 19.0039 15.6985 18.9382 15.6557C18.6067 15.4402 18.1795 15.4402 17.848 15.6557C17.7823 15.6985 17.6955 15.7775 17.3098 16.1632C17.3022 16.1708 17.2944 16.1786 17.2864 16.1866C17.1967 16.2767 17.0831 16.3907 16.9466 16.4884C16.4597 16.8371 15.7976 16.95 15.2226 16.7824C15.0622 16.7356 14.9302 16.672 14.8275 16.6225C14.8193 16.6186 14.8114 16.6148 14.8037 16.611C13.2537 15.8669 11.802 14.8528 10.5195 13.5703C9.23706 12.2878 8.22297 10.8361 7.4788 9.28617C7.47507 9.27842 7.47125 9.27048 7.46733 9.26236C7.41783 9.15967 7.35419 9.02766 7.30744 8.86728C7.13981 8.29225 7.25271 7.63018 7.60141 7.1432C7.69911 7.00676 7.81314 6.89315 7.9032 6.80341C7.91122 6.79542 7.91906 6.78761 7.92667 6.78C8.31238 6.39429 8.39136 6.30756 8.43409 6.24183C8.64959 5.91039 8.64959 5.48309 8.4341 5.15165C8.39136 5.08591 8.31238 4.99918 7.92667 4.61347L7.7318 4.4186C7.14478 3.83158 7.01174 3.71632 6.90502 3.66234ZM5.09963 1.87764C5.95097 1.44704 6.95637 1.44704 7.80771 1.87764C8.24031 2.09645 8.61586 2.47296 9.0503 2.90851C9.08188 2.94017 9.11377 2.97215 9.14601 3.00439L9.34088 3.19926C9.36213 3.22051 9.38318 3.24152 9.40402 3.26233C9.69068 3.5485 9.93761 3.79501 10.1108 4.06146C10.7573 5.0558 10.7573 6.33767 10.1108 7.33201C9.93761 7.59846 9.69068 7.84497 9.40402 8.13114C9.38318 8.15195 9.36213 8.17296 9.34088 8.19421C9.28328 8.25182 9.25389 8.28136 9.23342 8.30302L9.23235 8.3084C9.23198 8.31056 9.23151 8.31438 9.23151 8.31438C9.23481 8.32169 9.23927 8.33136 9.24542 8.34443C9.25503 8.36485 9.26632 8.38839 9.28176 8.42053C9.92958 9.76981 10.8131 11.0354 11.9337 12.1561C13.0544 13.2768 14.32 14.1603 15.6693 14.8081C15.7014 14.8235 15.725 14.8348 15.7454 14.8444C15.7585 14.8506 15.7681 14.855 15.7755 14.8583C15.7755 14.8583 15.7793 14.8579 15.7814 14.8575L15.7868 14.8564C15.8085 14.8359 15.838 14.8066 15.8956 14.7489C15.9169 14.7277 15.9379 14.7066 15.9587 14.6858C16.2449 14.3991 16.4914 14.1522 16.7578 13.979C17.7522 13.3325 19.034 13.3325 20.0284 13.979C20.2948 14.1522 20.5413 14.3991 20.8275 14.6858C20.8483 14.7066 20.8693 14.7277 20.8906 14.7489L21.0854 14.9438C21.1177 14.9761 21.1497 15.0079 21.1813 15.0395C21.6169 15.474 21.9934 15.8495 22.2122 16.2821C22.6428 17.1335 22.6428 18.1389 22.2122 18.9902C21.9934 19.4228 21.6169 19.7984 21.1813 20.2328C21.1497 20.2644 21.1177 20.2963 21.0854 20.3285L20.9278 20.4861C20.905 20.509 20.8824 20.5316 20.8601 20.5539C20.3948 21.0196 20.0381 21.3768 19.556 21.6476C19.0061 21.9565 18.2158 22.162 17.585 22.1601C17.0332 22.1585 16.6307 22.0441 16.1117 21.8966C16.0931 21.8913 16.0743 21.886 16.0554 21.8806C12.8621 20.9743 9.84919 19.2639 7.33755 16.7523C4.82592 14.2406 3.11557 11.2277 2.20923 8.03448C2.20386 8.01555 2.19853 7.99678 2.19323 7.97816C2.04573 7.45915 1.93134 7.05668 1.9297 6.5048C1.92782 5.87402 2.13333 5.08378 2.44226 4.53384C2.71306 4.05177 3.07021 3.69498 3.53594 3.22973C3.55828 3.20742 3.58086 3.18486 3.60369 3.16203L3.76132 3.00439C3.79357 2.97215 3.82546 2.94017 3.85704 2.90851C4.29148 2.47296 4.66703 2.09645 5.09963 1.87764Z"
                fill="currentColor"
              />
            </svg>
            <div className="content">
              <div className="header">Call us:</div>
              <a className="description" href="tel:+390690254001">
                +39 06 90 254 001
              </a>
            </div>
          </div>
          <div className="item send-email">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
            >
              <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6.75866 3H17.2413C18.0462 2.99999 18.7106 2.99998 19.2518 3.04419C19.8139 3.09012 20.3306 3.18868 20.8159 3.43597C21.5686 3.81947 22.1805 4.43139 22.564 5.18404C22.8026 5.65238 22.9028 6.14994 22.9508 6.68931C23.0042 6.8527 23.0136 7.02505 22.9818 7.18959C23 7.63971 23 8.16035 23 8.75868V15.2413C23 16.0463 23 16.7106 22.9558 17.2518C22.9098 17.8139 22.8113 18.3306 22.564 18.816C22.1805 19.5686 21.5686 20.1805 20.8159 20.564C20.3306 20.8113 19.8139 20.9099 19.2518 20.9558C18.7106 21 18.0462 21 17.2413 21H6.75864C5.95368 21 5.28933 21 4.74814 20.9558C4.18604 20.9099 3.66934 20.8113 3.184 20.564C2.43135 20.1805 1.81943 19.5686 1.43594 18.816C1.18865 18.3306 1.09008 17.8139 1.04416 17.2518C0.99994 16.7106 0.999951 16.0463 0.999963 15.2413V8.7587C0.999954 8.16037 0.999945 7.63972 1.0181 7.1896C0.986344 7.02505 0.995717 6.85269 1.04918 6.6893C1.09717 6.14993 1.19731 5.65238 1.43594 5.18404C1.81943 4.43139 2.43135 3.81947 3.184 3.43597C3.66934 3.18868 4.18604 3.09012 4.74814 3.04419C5.28933 2.99998 5.95369 2.99999 6.75866 3ZM2.99996 8.92066V15.2C2.99996 16.0566 3.00074 16.6389 3.03752 17.089C3.07334 17.5274 3.13826 17.7516 3.21795 17.908C3.4097 18.2843 3.71566 18.5903 4.09198 18.782C4.24838 18.8617 4.47259 18.9266 4.911 18.9624C5.36109 18.9992 5.94338 19 6.79996 19H17.2C18.0565 19 18.6388 18.9992 19.0889 18.9624C19.5273 18.9266 19.7515 18.8617 19.9079 18.782C20.2843 18.5903 20.5902 18.2843 20.782 17.908C20.8617 17.7516 20.9266 17.5274 20.9624 17.089C20.9992 16.6389 21 16.0566 21 15.2V8.92066L14.4085 13.5347C14.3697 13.5618 14.3313 13.5888 14.2932 13.6156C13.7485 13.998 13.2703 14.3338 12.7256 14.4696C12.2491 14.5884 11.7508 14.5884 11.2744 14.4696C10.7297 14.3338 10.2514 13.998 9.70677 13.6156C9.66866 13.5888 9.63022 13.5618 9.59142 13.5347L2.99996 8.92066ZM20.9172 6.53728L13.2616 11.8962C12.5326 12.4065 12.3782 12.495 12.2418 12.529C12.083 12.5686 11.9169 12.5686 11.7581 12.529C11.6217 12.495 11.4673 12.4065 10.7383 11.8962L3.08273 6.53728C3.11846 6.33012 3.16494 6.19607 3.21795 6.09202C3.4097 5.7157 3.71566 5.40973 4.09198 5.21799C4.24838 5.1383 4.47259 5.07337 4.911 5.03755C5.36109 5.00078 5.94338 5 6.79996 5H17.2C18.0565 5 18.6388 5.00078 19.0889 5.03755C19.5273 5.07337 19.7515 5.1383 19.9079 5.21799C20.2843 5.40973 20.5902 5.7157 20.782 6.09202C20.835 6.19607 20.8815 6.33012 20.9172 6.53728Z"
                fill="currentColor"
              />
            </svg>
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
                type={BUTTON_TYPE.PRIMARY}
                mode={BUTTON_MODE.OUTLINE}
                className="support-tip-button"
                onClick={() => {
                  CommonUtils.dispatchCustomEvent('openChat')
                }}
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 16 16"
                  fill="none"
                >
                  <path
                    fillRule="evenodd"
                    clipRule="evenodd"
                    d="M1.33337 7.99992C1.33337 4.31802 4.31814 1.33325 8.00004 1.33325C11.6819 1.33325 14.6667 4.31802 14.6667 7.99992C14.6667 11.6818 11.6819 14.6666 8.00004 14.6666C7.11449 14.6666 6.26771 14.4936 5.49285 14.1789C5.42286 14.1505 5.38245 14.1341 5.35262 14.1229C5.34909 14.1216 5.34603 14.1204 5.3434 14.1195C5.34242 14.1196 5.34136 14.1198 5.34024 14.1199C5.31563 14.1233 5.2821 14.1288 5.21807 14.1394L2.84615 14.5348C2.83981 14.5358 2.83343 14.5369 2.827 14.538C2.71992 14.5558 2.60011 14.5758 2.49607 14.5837C2.38261 14.5923 2.20212 14.5952 2.01137 14.5134C1.77557 14.4123 1.58766 14.2244 1.48653 13.9886C1.40471 13.7978 1.4077 13.6173 1.41626 13.5039C1.42411 13.3998 1.44412 13.28 1.462 13.173C1.46307 13.1665 1.46414 13.1601 1.4652 13.1538L1.86052 10.7819C1.87119 10.7179 1.8767 10.6843 1.88004 10.6597C1.88019 10.6586 1.88033 10.6575 1.88046 10.6565C1.87951 10.6539 1.87838 10.6509 1.87706 10.6473C1.86585 10.6175 1.8495 10.5771 1.82108 10.5071C1.50639 9.73225 1.33337 8.88546 1.33337 7.99992ZM8.00004 2.66659C5.05452 2.66659 2.66671 5.0544 2.66671 7.99992C2.66671 8.71056 2.80534 9.38718 3.05642 10.0054C3.06039 10.0152 3.06447 10.0252 3.06864 10.0354C3.10935 10.1351 3.15775 10.2536 3.18256 10.3643C3.2051 10.4648 3.21483 10.5527 3.21485 10.6557C3.21486 10.7688 3.19558 10.8831 3.18003 10.9754C3.17855 10.9842 3.1771 10.9927 3.17571 11.0011L2.81108 13.1889L4.99887 12.8242C5.00723 12.8229 5.01581 12.8214 5.02458 12.8199C5.11682 12.8044 5.23114 12.7851 5.34421 12.7851C5.44725 12.7851 5.53513 12.7949 5.63566 12.8174C5.74635 12.8422 5.86489 12.8906 5.96458 12.9313C5.97478 12.9355 5.98478 12.9396 5.99455 12.9435C6.61278 13.1946 7.2894 13.3333 8.00004 13.3333C10.9456 13.3333 13.3334 10.9454 13.3334 7.99992C13.3334 5.0544 10.9456 2.66659 8.00004 2.66659ZM4.00004 7.99992C4.00004 7.44763 4.44776 6.99992 5.00004 6.99992C5.55233 6.99992 6.00004 7.44763 6.00004 7.99992C6.00004 8.5522 5.55233 8.99992 5.00004 8.99992C4.44776 8.99992 4.00004 8.5522 4.00004 7.99992ZM7.00004 7.99992C7.00004 7.44763 7.44776 6.99992 8.00004 6.99992C8.55233 6.99992 9.00004 7.44763 9.00004 7.99992C9.00004 8.5522 8.55233 8.99992 8.00004 8.99992C7.44776 8.99992 7.00004 8.5522 7.00004 7.99992ZM10 7.99992C10 7.44763 10.4478 6.99992 11 6.99992C11.5523 6.99992 12 7.44763 12 7.99992C12 8.5522 11.5523 8.99992 11 8.99992C10.4478 8.99992 10 8.5522 10 7.99992Z"
                    fill="currentColor"
                  />
                </svg>
                Open chat
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default OutsourceInfo
