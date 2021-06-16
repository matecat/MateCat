import React from 'react'

import OutsourceConstants from '../../constants/OutsourceConstants'
import OutsourceStore from '../../stores/OutsourceStore'

class OutsourceModal extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      showTranslatorInfo: false,
      outsource: false,
    }
    this.getOutsourceQuote = this.getOutsourceQuote.bind(this)
    this.hideTranslator = this.hideTranslator.bind(this)
    if (config.enable_outsource) {
      this.getOutsourceQuote()
    }
  }

  getOutsourceQuote() {
    let self = this
    let typeOfService = $(this.revisionCheckbox).is(':checked')
      ? 'premium'
      : 'professional'
    let fixedDelivery = $('#forceDeliveryChosenDate').text()
    UI.currentOutsourceProject = this.props.project
    UI.currentOutsourceJob = this.props.job
    UI.currentOutsourceUrl = this.props.url
    UI.getOutsourceQuoteFromManage(
      this.props.project.id,
      this.props.project.password,
      this.props.job.id,
      this.props.job.password,
      fixedDelivery,
      typeOfService,
    ).done(function (quoteData) {
      if (quoteData.data) {
        self.quoteResponse = quoteData.data[0]
        self.chunk = quoteData.data[0][0]

        UI.url_ok = quoteData.return_url.url_ok
        UI.url_ko = quoteData.return_url.url_ko
        UI.confirm_urls = quoteData.return_url.confirm_urls
        UI.data_key = self.chunk.id

        self.setState({
          outsource: true,
        })
      } else {
        self.initOutsourceModal()
      }
    })
  }

  showTranslatorInfo() {
    $('#forceDeliveryContainer').addClass('hide')
    $('#out-datepicker').addClass('hide')
    $('#changeTimezone').removeClass('hide')
    this.forceDelivery = false
    this.setState({
      showTranslatorInfo: true,
    })
  }

  hideTranslatorInfo() {
    $('#forceDeliveryContainer').addClass('hide')
    $('#out-datepicker').addClass('hide')
    $('#changeTimezone').removeClass('hide')
    this.forceDelivery = false
    this.setState({
      showTranslatorInfo: false,
    })
  }

  hideTranslator() {
    this.setState({
      showTranslatorInfo: false,
    })
  }

  getCurrency() {
    // if the customer has a currency in the cookie, then use it
    // otherwise use the default one
    let currToShow = APP.readCookie('matecat_currency')
    if (currToShow == '') {
      currToShow = 'EUR'
    }
    return currToShow
  }

  getTimeZone() {
    let timezoneToShow = APP.readCookie('matecat_timezone')
    if (timezoneToShow == '') {
      timezoneToShow = -1 * (new Date().getTimezoneOffset() / 60)
    }
    return timezoneToShow
  }

  getDate(date, timezoneTo) {
    let dd = new Date(date.replace(/-/g, '/'))
    let timezoneFrom = -1 * (new Date().getTimezoneOffset() / 60)
    dd.setMinutes(dd.getMinutes() + (timezoneTo - timezoneFrom) * 60)
    return dd
  }

  getDateString(date, timezoneTo) {
    let dd = new Date(date.replace(/-/g, '/'))
    let timezoneFrom = -1 * (new Date().getTimezoneOffset() / 60)
    dd.setMinutes(dd.getMinutes() + (timezoneTo - timezoneFrom) * 60)
    return (
      $.format.date(dd, 'd MMMM') +
      ' at ' +
      $.format.date(dd, 'hh') +
      ':' +
      $.format.date(dd, 'mm') +
      ' ' +
      $.format.date(dd, 'a')
    )
  }

  checkDelivery(deliveryToShow) {
    return (
      new Date(deliveryToShow).getTime() < $('#forceDeliveryChosenDate').text()
    )
  }

  getDeliveryHtml() {
    let containerClass = this.state.showTranslatorInfo ? 'compress' : ''
    if (this.state.outsource) {
      let isRevisionChecked = $(this.revisionCheckbox).is(':checked')
      let deliveryToShow = isRevisionChecked
        ? this.chunk.r_delivery
        : this.chunk.delivery
      let priceToShow = isRevisionChecked
        ? parseFloat(this.chunk.r_price) + parseFloat(this.chunk.price)
        : this.chunk.price

      let timeZone = this.getTimeZone()
      let dateString = this.getDateString(deliveryToShow, timeZone)
      let date = this.getDate(deliveryToShow, timeZone)
      let tooltip = ''

      if (this.checkDelivery(deliveryToShow)) {
        tooltip = (
          <a className="tooltip gray hide">
            i
            <span>
              <strong>We will deliver before the selected date.</strong>
              <br />
              This date already provides us with all the time we need to deliver
              quality work at the lowest price
            </span>
          </a>
        )
      }

      return (
        <div className={'delivery ' + containerClass}>
          <div className="delivery_label">Delivery by:</div>
          <div className="delivery_details">
            <span
              className="time"
              data-timezone={timeZone}
              data-rawtime={date.toUTCString()}
            >
              {dateString}
            </span>
            {tooltip}
            <br />
            <span className="zone2" />
            <a className="needitfaster">Need it faster?</a>
          </div>
        </div>
      )
    } else {
      return (
        <div className={'delivery ' + containerClass}>
          <div className="delivery_label">Delivery by:</div>
          <div className="delivery_details">
            <div className="ErrorMsgquoteNotAvailable ErrorMsg hide">
              <h3>Not available. </h3>
              <p>
                Unfortunately the solution chosen is not available. Try again
                with another delivery date.
              </p>
            </div>
            <div className="ErrorMsg ErrorMsgQuoteError hide">
              <h3>
                <strong>Ooops! </strong>
                <br />
                Cannot generate quote.
              </h3>
            </div>

            <span className="time" data-timezone="" data-rawtime="" />
            <a className="tooltip gray hide">
              i
              <span>
                <strong>We will deliver before the selected date.</strong>
                <br />
                This date already provides us with all the time we need to
                deliver quality work at the lowest price
              </span>
            </a>
            <br />
            <span className="zone2" />
            <a className="needitfaster">Need it faster?</a>
          </div>
        </div>
      )
    }
  }

  getRevisionHtml() {
    let dateString = ''
    let timeZone
    let date
    if (this.state.outsource && this.chunk.r_delivery) {
      timeZone = this.getTimeZone()
      dateString = this.getDateString(this.chunk.r_delivery, timeZone)
      date = this.getDate(this.chunk.r_delivery, timeZone).toUTCString()
    }
    return (
      <div className="addrevision">
        <input
          type="checkbox"
          name="revision"
          value="revision"
          ref={(select) => (this.revisionCheckbox = select)}
        />
        <h4>Add revision</h4>
        <span
          className="revision_delivery"
          data-timezone={timeZone}
          data-rawtime={date}
        >
          {dateString}
        </span>
        <span className="revision_price_box">
          +
          <span className="revision_currency" />
          <span className="revision_price" />
        </span>
      </div>
    )
  }

  getTranslatorInfoHtml() {
    let subjectsString = ''
    let isRevisionChecked = $("input[name='revision']").is(':checked')

    var voteToShow = isRevisionChecked ? this.chunk.r_vote : this.chunk.t_vote
    if (this.chunk.show_revisor_data != 1 && this.chunk.t_name !== '') {
      $('.outsourceto').addClass('revisorNotAvailable')
      voteToShow = this.chunk.t_vote
    }

    if (
      this.chunk.t_chosen_subject.length > 0 &&
      this.chunk.t_subjects.length > 0
    ) {
      subjectsString =
        '<strong>' +
        this.chunk.t_chosen_subject +
        '</strong>, ' +
        this.chunk.t_subjects
    } else if (this.chunk.t_chosen_subject.length > 0) {
      subjectsString = '<strong>' + this.chunk.t_chosen_subject + '</strong>'
    } else {
      subjectsString = this.chunk.t_subjects
    }

    let translatedWords = this.chunk.t_words_total
      .toString()
      .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
    return (
      <div className="translator_info_box">
        <div className="translator_info">
          <div className="translator_bio">
            <span className="translator_name">
              Translator: <strong>{this.chunk.t_name}</strong>
            </span>
            <p>
              <span className="label_info">Positive feedback:</span>
              <span className="score_number">{parseInt(voteToShow) + '%'}</span>
            </p>
            <p>
              <span className="label_info">Expert in:</span>
              <span
                className="subjects"
                dangerouslySetInnerHTML={this.allowHTML(subjectsString)}
              />
            </p>
            <p>
              <span className="label_info">Years of experience:</span>{' '}
              <span className="experience">
                {this.chunk.t_experience_years}
              </span>
            </p>
            <p>
              <span className="label_info">
                Words translated last 12 months:
              </span>
              <span className="translated_words">{translatedWords}</span>
            </p>
          </div>
        </div>
      </div>
    )
  }

  updateTimezonesDescriptions(selectedTimezone) {
    $('#changeTimezone')
      .find('option')
      .each(function () {
        $(this).text($(this).attr('data-description-long'))
      })

    var selectedElement = $('#changeTimezone').find(
      "option[value='" + selectedTimezone + "']",
    )
    selectedElement.text(selectedElement.attr('data-description-short'))
  }

  initOutsourceModal() {
    UI.outsourceInit()
    if (!this.forceDelivery) {
      ForceDelivery.init()
      this.forceDelivery = true
    }
    this.updateTimezonesDescriptions(this.getTimeZone())
  }

  allowHTML(string) {
    return {__html: string}
  }

  componentDidMount() {
    OutsourceStore.addListener(
      OutsourceConstants.GET_OUTSOURCE_QUOTE,
      this.getOutsourceQuote,
    )
    OutsourceStore.addListener(
      OutsourceConstants.CLOSE_TRANSLATOR,
      this.hideTranslator,
    )
    if (!config.enable_outsource) {
      this.initOutsourceModal()
    }
    $('.ui.rating').rating('disable')
  }
  componentWillUnmount() {
    OutsourceStore.removeListener(
      OutsourceConstants.GET_OUTSOURCE_QUOTE,
      this.getOutsourceQuote,
    )
    OutsourceStore.removeListener(
      OutsourceConstants.CLOSE_TRANSLATOR,
      this.hideTranslator,
    )
  }

  componentDidUpdate() {
    this.initOutsourceModal()
    if (this.state.outsource) {
      // a generic error
      if (this.chunk.quote_result != 1) {
        renderGenericErrorQuote()
        return false
      }

      // job already outsourced
      if (this.chunk.outsourced == 1) {
        renderOutsourcedQuote(this.chunk)
        return false
      }

      // delivery date too strict
      if (this.chunk.quote_available != 1) {
        renderNotAvailableQuote()
        return false
      }

      renderNormalQuote(this.chunk)
      // Event ga
      $(document).trigger('outsource-rendered', {
        quote_data: this.quoteResponse,
      })
    }
  }

  render() {
    let loadingClass = this.state.outsource ? '' : 'loading'
    let showShareToTranslator = !!!this.props.job.outource || !config.isLoggedIn

    let textGuaranteedByClass = this.state.showTranslatorInfo ? 'expanded' : ''
    let pricesClass = this.state.showTranslatorInfo ? 'compress' : ''
    let deliveryHtml = this.getDeliveryHtml()
    let revisionHtml = this.getRevisionHtml()

    let date = ''
    let translatorEmail = ''
    let delivery = ''
    if (this.props.job.translator) {
      delivery = APP.getGMTDate(
        this.props.job.translator.delivery_timestamp * 1000,
      )
      date =
        delivery.day +
        ' ' +
        delivery.month +
        ' at ' +
        delivery.time +
        ' (' +
        delivery.gmt +
        ')'
      translatorEmail = this.props.job.translator.email
    } else if (this.state.outsource) {
      // let timeZone = this.getTimeZone();
      // let dateString =  this.getDateString(deliveryToShow, timeZone);
      delivery = APP.getGMTDate(this.chunk.delivery)
      date =
        delivery.day +
        ' ' +
        delivery.month +
        ' at ' +
        delivery.time +
        ' (' +
        delivery.gmt +
        ')'
    }

    // TODO : @Ruben Modificare questo Return

    return (
      <div className={'modal outsource ' + loadingClass}>
        <div className="popup">
          <div className={'popup-box pricebox ' + pricesClass}>
            <h2>
              Choose how to translate:
              <span className="title-source">
                {' '}
                {this.props.job.sourceTxt}
              </span>{' '}
              &gt;
              <span className="title-target"> {this.props.job.targetTxt}</span>
              <span className="title-words">
                {' '}
                {this.props.job.stats.TOTAL_FORMATTED}
              </span>{' '}
              words
            </h2>
            <div className="choose">
              <div
                className={
                  this.props.translatorOpen
                    ? 'onyourown opened-send-translator'
                    : 'onyourown'
                }
              >
                <div className="heading">
                  <h3>Share the following link with your translator</h3>
                </div>

                <input
                  className={
                    this.props.fromManage ? 'out-link from-manage' : 'out-link'
                  }
                  type="text"
                  defaultValue={
                    window.location.protocol +
                    '//' +
                    window.location.host +
                    this.props.url
                  }
                  readOnly="true"
                />
                {!this.props.fromManage ? (
                  <a
                    href={this.props.url}
                    className="uploadbtn in-popup"
                    target="_blank"
                  >
                    Open
                  </a>
                ) : (
                  <a
                    href={this.props.url}
                    className="uploadbtn in-popup hide"
                    target="_blank"
                  >
                    Open
                  </a>
                )}
              </div>
              {this.props.showTranslatorBox ? (
                <div>
                  {!this.props.translatorOpen &&
                  showShareToTranslator &&
                  config.isLoggedIn ? (
                    <div
                      id="open-translator"
                      className="open-send-to-translator"
                    >
                      Send job to translator
                    </div>
                  ) : (
                    ''
                  )}

                  <div
                    className={
                      this.props.translatorOpen && showShareToTranslator
                        ? 'send-to-translator'
                        : 'send-to-translator hide'
                    }
                  >
                    <div className="send-to-translator-container ">
                      <input
                        className="out-email"
                        type="email"
                        placeholder="Enter email"
                        defaultValue={translatorEmail}
                      />
                      <input
                        className="out-date"
                        type="datetime"
                        placeholder="Date"
                        value={date}
                      />
                      <a
                        className="send-to-translator-btn in-popup disabled"
                        target="_blank"
                      >
                        Send to translator
                      </a>
                      <div className="validation-error email-translator-error">
                        <span
                          className="text"
                          style={{color: 'red', fontsize: '14px'}}
                        >
                          A valid email is required
                        </span>
                      </div>
                    </div>

                    {/*<!-- begin date picker -->*/}
                    <div
                      id="out-datepicker"
                      className="modal-outsource-datepicker hide"
                    >
                      <div className="delivery-manual">
                        <div className="delivery-manual-date">
                          <div className="tabsContent">
                            <p id="date-trans"></p>
                          </div>
                        </div>
                        <div className="delivery-manual-time">
                          <h3>Choose a time</h3>
                          <select
                            name="whenTime"
                            className="whenTime"
                            id="outsource-assign-date"
                          >
                            <option value="07">7:00 AM</option>
                            <option value="09">9:00 AM</option>
                            <option value="11">11:00 AM</option>
                            <option value="13">1:00 PM</option>
                            <option value="15">3:00 PM</option>
                            <option value="17">5:00 PM</option>
                            <option value="19">7:00 PM</option>
                            <option value="21">9:00 PM</option>
                          </select>

                          <br />
                          <br />

                          <h3>Timezone</h3>
                          <select
                            name="whenTimezone"
                            className="whenTimezone"
                            id="outsource-assign-timezone"
                          >
                            <option value="-11">
                              (GMT -11:00 ) Midway Islands, American Samoa
                            </option>
                            <option value="-10">
                              (GMT -10:00 ) Hawaii, Tahiti, Cook Islands
                            </option>
                            <option value="-9">(GMT -9:00 ) Alaska</option>
                            <option value="-8">
                              (GMT -8:00 ) Pacific Standard Time (LA, Vancouver)
                            </option>
                            <option value="-7">
                              (GMT -7:00 ) Mountain Standard Time (Denver, SLC)
                            </option>
                            <option value="-6">
                              (GMT -6:00 ) Central Standard Time (Mexico,
                              Chicago)
                            </option>
                            <option value="-5">
                              (GMT -5:00 ) Eastern Standard Time (NYC, Toronto)
                            </option>
                            <option value="-4">
                              (GMT -4:00 ) Atlantic Standard Time (Santiago)
                            </option>
                            <option value="-4.5">
                              (GMT -4:30 ) Venezuela (Caracas)
                            </option>
                            <option value="-3">
                              (GMT -3:00 ) Brasília, São Paulo, Buenos Aires
                            </option>
                            <option value="-2">
                              (GMT -2:00 ) South Sandwich Islands
                            </option>
                            <option value="-1">
                              (GMT -1:00 ) Azores, Cape Verde (Praia)
                            </option>
                            <option value="0">
                              (GMT) Western European Time (London, Lisbon)
                            </option>
                            <option value="1">
                              (GMT +1:00 ) Central European Time (Rome, Paris)
                            </option>
                            <option value="2">
                              (GMT +2:00 ) Eastern European Time, CAT{' '}
                            </option>
                            <option value="3">
                              (GMT +3:00 ) Arabia Standard Time (Baghdad,
                              Riyadh)
                            </option>
                            <option value="3.5">
                              (GMT +3:30 ) Iran Standard Time (Tehran)
                            </option>
                            <option value="4">
                              (GMT +4:00 ) Moscow, St. Petersburg, Dubai
                            </option>
                            <option value="4.5">
                              (GMT +4:30 ) Afghanistan Time (Kabul)
                            </option>
                            <option value="5">
                              (GMT +5:00 ) Karachi, Tashkent, Maldive Islands
                            </option>
                            <option value="5.5">
                              (GMT +5:30 ) India Standard Time (Mumbai, Colombo)
                            </option>
                            <option value="6">
                              (GMT +6:00 ) Yekaterinburg, Almaty, Dhaka
                            </option>
                            <option value="7">
                              (GMT +7:00 ) Bangkok, Hanoi, Jakarta
                            </option>
                            <option value="8">
                              (GMT +8:00 ) Beijing, Perth, Singapore, Hong Kong
                            </option>
                            <option value="9">(GMT +9:00 ) Tokyo, Seoul</option>
                            <option value="9.5">
                              (GMT +9:30 ) ACST (Darwin, Adelaide)
                            </option>
                            <option value="10">
                              (GMT +10:00 ) AEST (Brisbane, Sydney), Yakutsk
                            </option>
                            <option value="11">
                              (GMT +11:00 ) Vladivostok, Nouméa, Solomon Islands
                            </option>
                            <option value="12">
                              (GMT +12:00 ) Auckland, Fiji, Marshall Islands
                            </option>
                            <option value="13">(GMT +13:00 ) Samoa</option>
                          </select>

                          {/*<!--  design took fron validationEngine not the logic. Hardcoded -->*/}
                          <div
                            id="outsource-delivery_error"
                            className="delivery_manual_error hide"
                          >
                            <div>* Chosen delivery date is in the past</div>
                          </div>

                          <input
                            type="button"
                            value="Continue"
                            className="uploadbtn in-popup outsource-select-date"
                          />
                          <a
                            href="#"
                            className="btn-cancel in-popup outsource-cancel-date"
                          >
                            Clear
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                ''
              )}
              {config.enable_outsource ? (
                <div>
                  <div className="outsource-divider">
                    <div className="divider-line"></div>
                    <span>or</span>
                    <div className="divider-line"></div>
                  </div>
                  <div className="outsourceto">
                    <div className="total_outsource">
                      <div className="heading">
                        <h3>
                          Outsource Project Management + Translation
                          <span className="revision_heading hide">
                            {' '}
                            + Revision
                          </span>
                        </h3>

                        <select id="changecurrency">
                          <option value="EUR" data-symbol="€">
                            Euro (EUR)
                          </option>
                          <option value="USD" data-symbol="US$">
                            US dollar (USD)
                          </option>
                          <option value="AUD" data-symbol="$">
                            Australian dollar (AUD)
                          </option>
                          <option value="CAD" data-symbol="$">
                            Canadian dollar (CAD)
                          </option>
                          <option value="NZD" data-symbol="$">
                            New Zealand dollar (NZD)
                          </option>
                          <option value="GBP" data-symbol="£">
                            Pound sterling (GBP)
                          </option>
                          <option value="BRL" data-symbol="R$">
                            Real (BRL)
                          </option>
                          <option value="RUB" data-symbol="руб">
                            Russian ruble (RUB)
                          </option>
                          <option value="SEK" data-symbol="kr">
                            Swedish krona (SEK)
                          </option>
                          <option value="CHF" data-symbol="Fr.">
                            Swiss franc (CHF)
                          </option>
                          <option value="TRY" data-symbol="TL">
                            Turkish lira (TL)
                          </option>
                          <option value="KRW" data-symbol="￦">
                            Won (KRW)
                          </option>
                          <option value="JPY" data-symbol="￥">
                            Yen (JPY)
                          </option>
                          <option value="PLN" data-symbol="zł">
                            Złoty (PLN)
                          </option>
                        </select>

                        <select id="changeTimezone">
                          <option
                            value="-11"
                            data-description-short="GMT -11:00"
                            data-description-long="(GMT -11:00 ) Midway Islands, American Samoa"
                          >
                            GMT -11:00
                          </option>
                          <option
                            value="-10"
                            data-description-short="GMT -10:00"
                            data-description-long="(GMT -10:00 ) Hawaii, Tahiti, Cook Islands"
                          >
                            GMT -10:00
                          </option>
                          <option
                            value="-9"
                            data-description-short="GMT -9:00"
                            data-description-long="(GMT -9:00 ) Alaska"
                          >
                            GMT -9:00
                          </option>
                          <option
                            value="-8"
                            data-description-short="GMT -8:00"
                            data-description-long="(GMT -8:00 ) Pacific Standard Time (LA, Vancouver)"
                          >
                            GMT -8:00
                          </option>
                          <option
                            value="-7"
                            data-description-short="GMT -7:00"
                            data-description-long="(GMT -7:00 ) Mountain Standard Time (Denver, SLC)"
                          >
                            GMT -7:00
                          </option>
                          <option
                            value="-6"
                            data-description-short="GMT -6:00"
                            data-description-long="(GMT -6:00 ) Central Standard Time (Mexico, Chicago)"
                          >
                            GMT -6:00
                          </option>
                          <option
                            value="-5"
                            data-description-short="GMT -5:00"
                            data-description-long="(GMT -5:00 ) Eastern Standard Time (NYC, Toronto)"
                          >
                            GMT -5:00
                          </option>
                          <option
                            value="-4.5"
                            data-description-short="GMT -4:30"
                            data-description-long="(GMT -4:30 ) Venezuela (Caracas)"
                          >
                            GMT -4:30
                          </option>
                          <option
                            value="-4"
                            data-description-short="GMT -4:00"
                            data-description-long="(GMT -4:00 ) Atlantic Standard Time (Santiago)"
                          >
                            GMT -4:00
                          </option>
                          <option
                            value="-3"
                            data-description-short="GMT -3:00"
                            data-description-long="(GMT -3:00 ) Brasília, São Paulo, Buenos Aires"
                          >
                            GMT -3:00
                          </option>
                          <option
                            value="-2"
                            data-description-short="GMT -2:00"
                            data-description-long="(GMT -2:00 ) South Sandwich Islands"
                          >
                            GMT -2:00
                          </option>
                          <option
                            value="-1"
                            data-description-short="GMT -1:00"
                            data-description-long="(GMT -1:00 ) Azores, Cape Verde (Praia)"
                          >
                            GMT -1:00
                          </option>
                          <option
                            value="0"
                            data-description-short="GMT"
                            data-description-long="(GMT) Western European Time (London,Lisbon)"
                          >
                            GMT
                          </option>
                          <option
                            value="1"
                            data-description-short="GMT +1:00"
                            data-description-long="(GMT +1:00 ) Central European Time (Rome, Paris)"
                          >
                            GMT +1:00
                          </option>
                          <option
                            value="2"
                            data-description-short="GMT +2:00"
                            data-description-long="(GMT +2:00 ) Eastern European Time, CAT "
                          >
                            GMT +2:00
                          </option>
                          <option
                            value="3"
                            data-description-short="GMT +3:00"
                            data-description-long="(GMT +3:00 ) Arabia Standard Time (Baghdad, Riyadh)"
                          >
                            GMT +3:00
                          </option>
                          <option
                            value="3.5"
                            data-description-short="GMT +3:30"
                            data-description-long="(GMT +3:30 ) Iran Standard Time (Tehran)"
                          >
                            GMT +3:30
                          </option>
                          <option
                            value="4"
                            data-description-short="GMT +4:00"
                            data-description-long="(GMT +4:00 ) Moscow, St. Petersburg, Dubai"
                          >
                            GMT +4:00
                          </option>
                          <option
                            value="4.5"
                            data-description-short="GMT +4:30"
                            data-description-long="(GMT +4:30 ) Afghanistan Time (Kabul)"
                          >
                            GMT +4:30
                          </option>
                          <option
                            value="5"
                            data-description-short="GMT +5:00"
                            data-description-long="(GMT +5:00 ) Karachi, Tashkent, Maldive Islands"
                          >
                            GMT +5:00
                          </option>
                          <option
                            value="5.5"
                            data-description-short="GMT +5:30"
                            data-description-long="(GMT +5:30 ) India Standard Time (Mumbai, Colombo)"
                          >
                            GMT +5:30
                          </option>
                          <option
                            value="6"
                            data-description-short="GMT +6:00"
                            data-description-long="(GMT +6:00 ) Yekaterinburg, Almaty, Dhaka"
                          >
                            GMT +6:00
                          </option>
                          <option
                            value="7"
                            data-description-short="GMT +7:00"
                            data-description-long="(GMT +7:00 ) Bangkok, Hanoi, Jakarta"
                          >
                            GMT +7:00
                          </option>
                          <option
                            value="8"
                            data-description-short="GMT +8:00"
                            data-description-long="(GMT +8:00 ) Beijing, Perth, Singapore, Hong Kong"
                          >
                            GMT +8:00
                          </option>
                          <option
                            value="9"
                            data-description-short="GMT +9:00"
                            data-description-long="(GMT +9:00 ) Tokyo, Seoul"
                          >
                            GMT +9:00
                          </option>
                          <option
                            value="9.5"
                            data-description-short="GMT +9:30"
                            data-description-long="(GMT +9:30 ) ACST (Darwin, Adelaide)"
                          >
                            GMT +9:30
                          </option>
                          <option
                            value="10"
                            data-description-short="GMT +10:00"
                            data-description-long="(GMT +10:00 ) AEST (Brisbane, Sydney), Yakutsk"
                          >
                            GMT +10:00
                          </option>
                          <option
                            value="11"
                            data-description-short="GMT +11:00"
                            data-description-long="(GMT +11:00 ) Vladivostok, Nouméa, Solomon Islands"
                          >
                            GMT +11:00
                          </option>
                          <option
                            value="12"
                            data-description-short="GMT +12:00"
                            data-description-long="(GMT +12:00 ) Auckland, Fiji, Marshall Islands"
                          >
                            GMT +12:00
                          </option>
                          <option
                            value="13"
                            data-description-short="GMT +13:00"
                            data-description-long="(GMT +13:00 ) Samoa"
                          >
                            GMT +13:00
                          </option>
                        </select>
                      </div>
                      <div className="offer">
                        <div
                          className={'guaranteed_by ' + textGuaranteedByClass}
                        >
                          <div className="trust_text">
                            <strong>Guaranteed by</strong>
                            <a href="http://www.translated.net" target="_blank">
                              <img
                                src="/public/img/logo_translated.png"
                                title="visit our website"
                              />
                            </a>

                            <p className="trustbox1">
                              Translated uses the most qualified translator for
                              your subject (
                              <strong style={{textTransform: 'capitalize'}}>
                                {this.props.job.subject}
                              </strong>
                              ) and keeps using the same translator for your
                              next projects. <br />
                              {!this.state.showTranslatorInfo ? (
                                <a
                                  className="show_translator more"
                                  onClick={this.showTranslatorInfo.bind(this)}
                                >
                                  <span>Read more</span>
                                </a>
                              ) : (
                                <a
                                  className="show_translator more hide"
                                  onClick={this.showTranslatorInfo.bind(this)}
                                >
                                  <span>Read more</span>
                                </a>
                              )}
                            </p>

                            {this.state.showTranslatorInfo ? (
                              <p className="trustbox2">
                                Translated has over 15 years' experience as a
                                translation company and offers
                                <a
                                  href="http://www.translated.net/en/frequently-asked-questions#guarantees"
                                  target="_blank"
                                >
                                  {' '}
                                  two key guarantees on quality and delivery
                                </a>
                                .
                                <br />
                                <a
                                  className="hide_translator more minus"
                                  onClick={this.hideTranslatorInfo.bind(this)}
                                >
                                  <span>Close</span>
                                </a>
                              </p>
                            ) : (
                              <p className="trustbox2 hide">
                                Translated has over 15 years' experience as a
                                translation company and offers
                                <a
                                  href="http://www.translated.net/en/frequently-asked-questions#guarantees"
                                  target="_blank"
                                >
                                  {' '}
                                  two key guarantees on quality and delivery
                                </a>
                                .
                                <br />
                                <a
                                  className="hide_translator more minus"
                                  onClick={this.hideTranslatorInfo.bind(this)}
                                >
                                  <span>Close</span>
                                </a>
                              </p>
                            )}
                          </div>

                          {this.state.showTranslatorInfo && this.state.outsource
                            ? this.getTranslatorInfoHtml()
                            : ''}
                        </div>

                        {/*{ (!this.state.showTranslatorInfo) ? (*/}
                        <div className="delivery_container">{deliveryHtml}</div>
                        {/*) : (<div className="delivery_container">*/}
                        {/*<div className="delivery"/>*/}
                        {/*</div>)}*/}

                        <div className={'tprice ' + pricesClass}>
                          <div className="ErrorMsg ErrorMsgQuoteError hide">
                            <p>
                              Contact us at{' '}
                              <a href="mailto:info@translated.net">
                                info@translated.net
                              </a>{' '}
                              <br />
                              or call +39 06 90 254 001
                            </p>
                          </div>
                          <span className="euro" />
                          <span
                            className="displayprice"
                            data-currency="EUR"
                            data-rawprice="0.00"
                          />
                          <br />

                          {/*//TODO Inserire spazi*/}
                          <span className="displaypriceperword">
                            about
                            <span
                              className="euro currency_per_word"
                              style={{marginLeft: '2px', marginRight: '2px'}}
                            />
                            <span className="price_p_word" /> / word
                            {/*{ (this.state.showTranslatorInfo) ? (*/}
                            <div className="delivery_container">
                              {deliveryHtml}
                            </div>
                            {/*) : (<div className="delivery_container">*/}
                            {/*<div className="delivery compress"/>*/}
                            {/*</div>)}*/}
                          </span>
                          <form
                            id="continueForm"
                            action={config.outsource_service_login}
                            method="POST"
                            target="_blank"
                          >
                            <input type="hidden" name="url_ok" value="" />
                            <input type="hidden" name="url_ko" value="" />
                            <input type="hidden" name="confirm_urls" value="" />
                            <input type="hidden" name="data_key" value="" />
                            <input type="hidden" name="quoteData" value="" />
                            <a href="#" className="continuebtn disabled">
                              Order
                            </a>
                          </form>
                        </div>
                        {revisionHtml}
                      </div>
                    </div>
                  </div>
                  {/*<!--end outsourceto-->*/}
                  <div className="paymentinfo">
                    <p>
                      <strong>Easy payments</strong>: pay a single monthly
                      invoice within 30 days of receipt
                    </p>
                  </div>
                  <div className="contact_box">
                    <h3>Have a specific request?</h3>
                    <p>
                      Contact us at{' '}
                      <a href="mailto:info@translated.net">
                        info@translated.net
                      </a>{' '}
                      or call +39 06 90 254 001
                    </p>
                  </div>
                </div>
              ) : (
                ''
              )}

              {/*<!-- end total-->*/}
              {/*<!-- end reveal prices-->*/}
              {/*<!-- begin date picker -->*/}
              <div
                id="forceDeliveryContainer"
                className="modal-outsource-datepicker hide"
              >
                <div className="delivery-manual">
                  <span id="forceDeliveryChosenDate" className="hide">
                    0
                  </span>

                  <div className="delivery-manual-date">
                    <div className="tabsContent">
                      <p id="date2"></p>
                    </div>
                  </div>
                  <div className="delivery-manual-time">
                    <h3>Choose a time</h3>
                    <select name="whenTime" id="whenTime" className="whenTime">
                      <option value="07">7:00 AM</option>
                      <option value="09">9:00 AM</option>
                      <option value="11">11:00 AM</option>
                      <option value="13">1:00 PM</option>
                      <option value="15">3:00 PM</option>
                      <option value="17">5:00 PM</option>
                      <option value="19">7:00 PM</option>
                      <option value="21">9:00 PM</option>
                    </select>

                    <br />
                    <br />

                    <h3>Timezone</h3>
                    <select
                      id="whenTimezone"
                      name="whenTimezone"
                      className="whenTimezone"
                    >
                      <option value="-11">
                        (GMT -11:00 ) Midway Islands, American Samoa
                      </option>
                      <option value="-10">
                        (GMT -10:00 ) Hawaii, Tahiti, Cook Islands
                      </option>
                      <option value="-9">(GMT -9:00 ) Alaska</option>
                      <option value="-8">
                        (GMT -8:00 ) Pacific Standard Time (LA, Vancouver)
                      </option>
                      <option value="-7">
                        (GMT -7:00 ) Mountain Standard Time (Denver, SLC)
                      </option>
                      <option value="-6">
                        (GMT -6:00 ) Central Standard Time (Mexico, Chicago)
                      </option>
                      <option value="-5">
                        (GMT -5:00 ) Eastern Standard Time (NYC, Toronto)
                      </option>
                      <option value="-4">
                        (GMT -4:00 ) Atlantic Standard Time (Santiago)
                      </option>
                      <option value="-4.5">
                        (GMT -4:30 ) Venezuela (Caracas)
                      </option>
                      <option value="-3">
                        (GMT -3:00 ) Brasília, São Paulo, Buenos Aires
                      </option>
                      <option value="-2">
                        (GMT -2:00 ) South Sandwich Islands
                      </option>
                      <option value="-1">
                        (GMT -1:00 ) Azores, Cape Verde (Praia)
                      </option>
                      <option value="0">
                        (GMT) Western European Time (London, Lisbon)
                      </option>
                      <option value="1">
                        (GMT +1:00 ) Central European Time (Rome, Paris)
                      </option>
                      <option value="2">
                        (GMT +2:00 ) Eastern European Time, CAT{' '}
                      </option>
                      <option value="3">
                        (GMT +3:00 ) Arabia Standard Time (Baghdad, Riyadh)
                      </option>
                      <option value="3.5">
                        (GMT +3:30 ) Iran Standard Time (Tehran)
                      </option>
                      <option value="4">
                        (GMT +4:00 ) Moscow, St. Petersburg, Dubai
                      </option>
                      <option value="4.5">
                        (GMT +4:30 ) Afghanistan Time (Kabul)
                      </option>
                      <option value="5">
                        (GMT +5:00 ) Karachi, Tashkent, Maldive Islands
                      </option>
                      <option value="5.5">
                        (GMT +5:30 ) India Standard Time (Mumbai, Colombo)
                      </option>
                      <option value="6">
                        (GMT +6:00 ) Yekaterinburg, Almaty, Dhaka
                      </option>
                      <option value="7">
                        (GMT +7:00 ) Bangkok, Hanoi, Jakarta
                      </option>
                      <option value="8">
                        (GMT +8:00 ) Beijing, Perth, Singapore, Hong Kong
                      </option>
                      <option value="9">(GMT +9:00 ) Tokyo, Seoul</option>
                      <option value="9.5">
                        (GMT +9:30 ) ACST (Darwin, Adelaide)
                      </option>
                      <option value="10">
                        (GMT +10:00 ) AEST (Brisbane, Sydney), Yakutsk
                      </option>
                      <option value="11">
                        (GMT +11:00 ) Vladivostok, Nouméa, Solomon Islands
                      </option>
                      <option value="12">
                        (GMT +12:00 ) Auckland, Fiji, Marshall Islands
                      </option>
                      <option value="13">(GMT +13:00 ) Samoa</option>
                    </select>

                    {/*<!--  design took fron validationEngine not the logic. Hardcoded -->*/}
                    <div
                      id="delivery_manual_error"
                      className="delivery_manual_error hide"
                    >
                      <div>* Chosen delivery date is in the past</div>
                    </div>
                    <div
                      id="delivery_before_time"
                      className="delivery_before_time hide"
                    >
                      <div>We will deliver before the selected date</div>
                    </div>
                    <div
                      id="delivery_not_available"
                      className="delivery_not_available hide"
                    >
                      <div>Deadline too close, pick another one.</div>
                    </div>

                    <input type="hidden" id="datePickerSelectedDate" value="" />
                    <input
                      type="hidden"
                      id="datePickerSelectedDatePrint"
                      value=""
                    />
                    <input
                      name="forceDeliveryButtonOk"
                      type="button"
                      value="Continue"
                      className="uploadbtn in-popup forceDeliveryButtonOk"
                    />
                    <a
                      href="#"
                      className="btn-cancel in-popup cancelForceDelivery"
                    >
                      Clear
                    </a>
                  </div>
                </div>
              </div>
              {/*<!-- end date picker -->*/}
            </div>
          </div>
        </div>
      </div>
    )
  }
}
OutsourceModal.defaultProps = {
  showTranslatorBox: true,
}

export default OutsourceModal
