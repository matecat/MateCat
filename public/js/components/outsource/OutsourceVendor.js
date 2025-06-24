import React from 'react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'
import {isUndefined} from 'lodash'
import {isNull} from 'lodash/lang'
import DatePicker from 'react-datepicker'
import $ from 'jquery'

import OutsourceInfo from './OutsourceInfo'
import {GMTSelect} from './GMTSelect'
import {getOutsourceQuote} from '../../api/getOutsourceQuote'
import {getChangeRates} from '../../api/getChangeRates'
import CommonUtils from '../../utils/commonUtils'
import UserStore from '../../stores/UserStore'

import 'react-datepicker/dist/react-datepicker.css'
import {Select} from '../common/Select'
import {DropdownMenu} from '../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE} from '../common/Button/Button'
class OutsourceVendor extends React.Component {
  constructor(props) {
    super(props)
    let changesRates =
      !isUndefined(Cookies.get('matecat_changeRates')) &&
      !isNull(Cookies.get('matecat_changeRates'))
        ? $.parseJSON(Cookies.get('matecat_changeRates'))
        : {}
    this.state = {
      outsource: false,
      revision: false,
      chunkQuote: null,
      outsourceConfirmed: !!this.props.job.get('outsource'),
      extendedView: this.props.extendedView,
      timezone: Cookies.get('matecat_timezone'),
      changeRates: changesRates,
      jobOutsourced: !!this.props.job.get('outsource'),
      errorPastDate: false,
      quoteNotAvailable: false,
      errorQuote: false,
      needItFaster: false,
      errorOutsource: false,
      deliveryDate:
        this.props.job && this.props.job.get('outsource')
          ? new Date(this.props.job.get('outsource').get('delivery_date'))
          : null,
      selectedTime: '12',
    }
    this.getOutsourceQuote = this.getOutsourceQuote.bind(this)
    if (config.enable_outsource) {
      this.getOutsourceQuote()
    }

    this.retrieveChangeRates()

    // Note 2024-07-08
    // I temporary removed RUB and TRY because the Translated API
    // does not return the corresponding conversion rates
    this.currencies = {
      EUR: {symbol: '€', name: 'Euro (EUR)'},
      USD: {symbol: 'US$', name: 'US dollar (USD)'},
      AUD: {symbol: '$', name: 'Australian dollar (AUD)'},
      CAD: {symbol: '$', name: 'Canadian dollar (CAD)'},
      NZD: {symbol: '$', name: 'New Zealand dollar (NZD)'},
      GBP: {symbol: '£', name: 'Pound sterling (GBP)'},
      BRL: {symbol: 'R$', name: 'Real (BRL)'},
      //RUB: {symbol: 'руб', name: 'Russian ruble (RUB)'},
      SEK: {symbol: 'kr', name: 'Swedish krona (SEK)'},
      CHF: {symbol: 'Fr.', name: 'Swiss franc (CHF)'},
      //TRY: {symbol: 'TL', name: 'Turkish lira (TL)'},
      KRW: {symbol: '￦', name: 'Won (KRW)'},
      JPY: {symbol: '￥', name: 'Yen (JPY)'},
      PLN: {symbol: 'zł', name: 'Złoty (PLN)'},
    }
    this.timeOptions = [
      {name: '7:00 AM', id: '7'},
      {name: '8:00 AM', id: '8'},
      {name: '9:00 AM', id: '9'},
      {name: '10:00 AM', id: '10'},
      {name: '11:00 AM', id: '11'},
      {name: '12:00 AM', id: '12'},
      {name: '1:00 PM', id: '13'},
      {name: '2:00 PM', id: '14'},
      {name: '3:00 PM', id: '15'},
      {name: '4:00 PM', id: '16'},
      {name: '5:00 PM', id: '17'},
      {name: '6:00 PM', id: '18'},
      {name: '7:00 PM', id: '19'},
      {name: '8:00 PM', id: '20'},
      {name: '9:00 PM', id: '21'},
    ]
  }

  getOutsourceQuote(delivery, revisionType) {
    let self = this
    let typeOfService = this.state.revision ? 'premium' : 'professional'
    if (revisionType) {
      typeOfService = revisionType
    }
    let fixedDelivery = delivery ? delivery : ''
    let timezoneToShow = this.state.timezone
    let currency = this.getCurrentCurrency()
    getOutsourceQuote(
      this.props.project.get('id'),
      this.props.project.get('password'),
      this.props.job.get('id'),
      this.props.job.get('password'),
      fixedDelivery,
      typeOfService,
      timezoneToShow,
      currency,
    )
      .then((quoteData) => {
        if (quoteData.data && quoteData.data.length > 0) {
          if (
            quoteData.data[0][0].quote_available !== '1' &&
            quoteData.data[0][0].outsourced !== '1'
          ) {
            self.setState({
              outsource: true,
              quoteNotAvailable: true,
            })
            return
          } else if (
            quoteData.data[0][0].quote_result !== '1' &&
            quoteData.data[0][0].outsourced !== '1'
          ) {
            self.setState({
              outsource: true,
              errorQuote: true,
            })
            return
          }

          self.quoteResponse = quoteData.data[0]
          let chunk = fromJS(quoteData.data[0][0])

          self.url_ok = quoteData.return_url.url_ok
          self.url_ko = quoteData.return_url.url_ko
          self.confirm_urls = quoteData.return_url.confirm_urls
          self.data_key = chunk.get('id')

          self.setState({
            outsource: true,
            quoteNotAvailable: false,
            errorQuote: false,
            chunkQuote: chunk,
            revision: chunk.get('typeOfService') === 'premium' ? true : false,
            jobOutsourced: chunk.get('outsourced') === '1',
            outsourceConfirmed: chunk.get('outsourced') === '1',
            deliveryDate: new Date(chunk.get('delivery')),
          })
          setTimeout(() => {
            let date = this.getDeliveryDate()
            this.setState({
              selectedTime: date.time2.split(':')[0],
            })
          })
        } else {
          self.setState({
            outsource: false,
            errorQuote: true,
            errorOutsource: true,
          })
        }
      })
      .catch(() => {
        this.setState({
          outsource: false,
          errorQuote: true,
          errorOutsource: true,
        })
      })
  }

  getCurrentCurrency() {
    let currency = Cookies.get('matecat_currency')
    if (!isUndefined(currency) && !isNull(currency) && currency !== 'null') {
      return currency
    } else {
      Cookies.set('matecat_currency', 'EUR', {secure: true})
      return 'EUR'
    }
  }

  getPriceCurrencySymbol() {
    if (this.state.outsource) {
      let currency = this.state.chunkQuote.get('currency')
      return this.currencies[currency].symbol
    } else {
      return ''
    }
  }

  getCurrencyPrice(price) {
    let current = this.getCurrentCurrency()
    if (this.state.changeRates) {
      return parseFloat(
        (price * this.state.changeRates[current]) /
          this.state.changeRates['EUR'],
      ).toFixed(2)
    } else {
      return price.toString()
    }
  }

  changeTimezone(value) {
    Cookies.set('matecat_timezone', value, {secure: true})
    this.setState({
      timezone: value,
    })
  }

  retrieveChangeRates() {
    let self = this
    let changeRates = Cookies.get('matecat_changeRates')
    if (
      isUndefined(changeRates) ||
      isNull(changeRates) ||
      changeRates === 'null'
    ) {
      getChangeRates().then(function (response) {
        var rates = $.parseJSON(response.data)
        if (!isUndefined(rates) && !isNull(changeRates)) {
          self.setState({
            changeRates: rates,
          })
          Cookies.set('matecat_changeRates', response.data, {
            expires: 1,
            secure: true,
          })
        }
      })
    }
  }

  onCurrencyChange(value) {
    Cookies.set('matecat_currency', value, {secure: true})
    let quote = this.state.chunkQuote.set('currency', value)
    this.setState({
      chunkQuote: quote,
    })
  }

  confirmOutsource() {
    this.setState({
      outsourceConfirmed: true,
    })
  }

  goBack() {
    this.setState({
      outsourceConfirmed: false,
    })
  }

  sendOutsource() {
    this.quoteResponse[0] = this.state.chunkQuote.toJS()

    $(this.outsourceForm).find('input[name=url_ok]').attr('value', this.url_ok)
    $(this.outsourceForm).find('input[name=url_ko]').attr('value', this.url_ko)
    $(this.outsourceForm)
      .find('input[name=confirm_urls]')
      .attr('value', this.confirm_urls)
    $(this.outsourceForm)
      .find('input[name=data_key]')
      .attr('value', this.data_key)

    //IMPORTANT post out the quotes
    $(this.outsourceForm)
      .find('input[name=quoteData]')
      .attr('value', JSON.stringify(this.quoteResponse))
    $(this.outsourceForm).submit()
    $(this.outsourceForm).find('input[name=quoteData]').attr('value', '')
    const data = {
      event: 'outsource_clicked',
      quote_data: this.quoteResponse,
    }
    CommonUtils.dispatchAnalyticsEvents(data)
    // this.setState({
    //     jobOutsourced: true
    // });
  }

  openOutsourcePage() {
    window.open(
      this.props.job.get('outsource').get('quote_review_link'),
      '_blank',
    )
  }

  clickRevision() {
    let service = this.revisionCheckbox.checked ? 'premium' : 'professional'
    this.setState({
      revision: this.revisionCheckbox.checked,
    })
    let self = this
    setTimeout(function () {
      self.getOutsourceQuote(self.selectedDate, service)
    })
  }

  getDeliveryDate() {
    if (!isNull(this.props.job.get('outsource'))) {
      return CommonUtils.getGMTDate(
        this.props.job.get('outsource').get('delivery_date'),
      )
    } else if (this.state.outsource) {
      // let timeZone = this.getTimeZone();
      // let dateString =  this.getDateString(deliveryToShow, timeZone);
      if (this.state.revision && this.state.chunkQuote.get('r_delivery')) {
        return CommonUtils.getGMTDate(this.state.chunkQuote.get('r_delivery'))
      } else {
        return CommonUtils.getGMTDate(this.state.chunkQuote.get('delivery'))
      }
    }
  }

  checkChosenDateIsAfter() {
    if (this.state.outsource && this.selectedDate) {
      if (this.state.revision && this.state.chunkQuote.get('r_delivery')) {
        return (
          this.selectedDate >
          new Date(this.state.chunkQuote.get('r_delivery')).getTime()
        )
      } else {
        return (
          this.selectedDate >
          new Date(this.state.chunkQuote.get('delivery')).getTime()
        )
      }
    }
    return false
  }

  getPrice() {
    let price
    if (!isNull(this.props.job.get('outsource'))) {
      price = this.props.job.get('outsource').get('price')
      return this.getCurrencyPrice(parseFloat(price))
    } else if (this.state.outsource) {
      if (this.state.revision) {
        price = parseFloat(
          parseFloat(this.state.chunkQuote.get('r_price')) +
            parseFloat(this.state.chunkQuote.get('price')),
        )
      } else {
        price = parseFloat(this.state.chunkQuote.get('price'))
      }
      return this.getCurrencyPrice(parseFloat(price))
    }
  }

  getPricePW(price) {
    if (this.state.outsource) {
      return (parseFloat(price) / this.props.standardWC)
        .toFixed(3)
        .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
    }
  }

  getTranslatedWords() {
    if (this.state.outsource) {
      return this.state.chunkQuote
        .get('t_words_total')
        .toString()
        .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
    }
  }

  getTranslatorSubjects() {
    if (this.state.outsource) {
      if (
        this.state.chunkQuote.get('t_chosen_subject').length > 0 &&
        this.state.chunkQuote.get('t_subjects').length > 0
      ) {
        return (
          this.state.chunkQuote.get('t_chosen_subject') +
          ', ' +
          this.state.chunkQuote.get('t_subjects')
        )
      } else if (this.state.chunkQuote.get('t_chosen_subject').length > 0) {
        return this.state.chunkQuote.get('t_chosen_subject')
      } else {
        return this.state.chunkQuote.get('t_subjects')
      }
    }
  }

  getUserEmail() {
    const userInfo = UserStore.getUser()
    if (userInfo.user) {
      return userInfo.user.email
    } else {
      return ''
    }
  }

  viewMoreClick() {
    this.setState({
      extendedView: true,
    })
  }

  needItFaster() {
    this.setState({
      needItFaster: !this.state.needItFaster,
    })
  }

  getNewRates() {
    let date = this.state.deliveryDate
    let time = this.state.selectedTime
    date.setHours(time)
    date.setMinutes((2 - parseFloat(this.state.timezone)) * 60)
    let timestamp = new Date(date).getTime()
    let now = new Date().getTime()
    if (timestamp < now) {
      this.selectedDate = null
      this.setState({
        errorPastDate: true,
        needItFaster: false,
      })
    } else {
      this.selectedDate = timestamp
      this.setState({
        outsource: false,
        errorPastDate: false,
        needItFaster: false,
      })
      this.getOutsourceQuote(timestamp)
    }
  }

  getLoaderHtml() {
    let msg = 'Choosing the best available translator...'
    if (
      this.props.translatorsNumber &&
      parseInt(this.props.translatorsNumber.asInt) > 30
    ) {
      msg =
        'Choosing the best available translator from the matching ' +
        this.props.translatorsNumber.printable +
        '...'
    }
    return (
      <div className="translated-loader">
        <img src="../../public/img/loader-matecat-translated-outsource.gif" />
        <div className="text-loader-outsource">{msg}</div>
      </div>
    )
  }

  numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  }

  getExtendedView() {
    let checkboxDisabledClass = this.state.outsourceConfirmed ? 'disabled' : ''
    let delivery = this.getDeliveryDate()
    let showDateMessage = this.checkChosenDateIsAfter()
    let price = this.getPrice()
    let priceCurrencySymbol = this.getPriceCurrencySymbol()
    let translatedWords = this.getTranslatedWords()
    let email = this.getUserEmail()
    let pricePWord = this.getPricePW(price)
    /*let translatorSubjects = this.getTranslatorSubjects();*/
    return (
      <div className="outsource-to-vendor sixteen wide column">
        <div className="payment-service">
          <div className="service-box">
            <div className="service project-management">
              Outsource: Project Management{' '}
            </div>
            <div className="service translation"> + Translation </div>
            {this.state.revision ? (
              <div className="service revision"> + Revision</div>
            ) : null}
          </div>
          <div className="fiducial-logo">
            <div className="translated-logo">
              Guaranteed by
              <img className="logo-t" src="/img/matecat-logo-translated.svg" />
            </div>
          </div>
        </div>
        {this.state.outsource ? (
          <div className="payment-details-box">
            <div className="translator-job-details">
              {this.state.chunkQuote.get('t_name') !== '' ? (
                <div className="translator-details-box">
                  <div className="ui list left">
                    <div className="item">
                      <b>{this.state.chunkQuote.get('t_name')}</b> by Translated
                    </div>
                  </div>
                  <div className="ui list right">
                    <div className="item">
                      <b>{translatedWords}</b> words translated last 12 months
                    </div>
                    <div className="item">
                      <b>
                        {this.state.chunkQuote.get('t_experience_years')} years
                        of experience
                      </b>
                    </div>
                    {/*<div className="item"><b>{translatorSubjects}</b></div>*/}
                  </div>
                </div>
              ) : (
                <div className="translator-details-box">
                  <div className="translator-no-found">
                    <p>
                      Translated uses the <b>most qualified translator</b>{' '}
                      <br /> and{' '}
                      <b>
                        keeps using the same translator for your next
                        projects.{' '}
                      </b>
                    </p>
                  </div>
                </div>
              )}

              <div className="job-details-box">
                <div className="source-target-outsource st-details">
                  <div className="source-box">
                    {this.props.job.get('sourceTxt')}
                  </div>
                  <div className="in-to">
                    <i className="icon-chevron-right icon" />
                  </div>
                  <div className="target-box">
                    {this.props.job.get('targetTxt')}
                  </div>
                </div>
                <div className="job-payment">
                  {/*{this.props.standardWC ? (*/}
                  {/*<div className="not-payable">{this.props.standardWC} words</div>*/}
                  {/*) : (null)}*/}
                  <div className="payable">
                    {this.numberWithCommas(this.state.chunkQuote.get('words'))}{' '}
                    words
                  </div>
                </div>
              </div>
              {this.state.outsourceConfirmed ? (
                ''
              ) : (
                <div className="job-price">
                  {priceCurrencySymbol}{' '}
                  {this.getCurrencyPrice(
                    this.state.chunkQuote.get('price'),
                  ).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')}
                </div>
              )}
            </div>
            <div className="revision-box">
              <div className="add-revision">
                <div className={'ui checkbox ' + checkboxDisabledClass}>
                  <input
                    type="checkbox"
                    checked={this.state.revision}
                    ref={(checkbox) => (this.revisionCheckbox = checkbox)}
                    onChange={this.clickRevision.bind(this)}
                  />
                  <label>Add Revision</label>
                </div>
              </div>
              {this.state.outsourceConfirmed ? (
                ''
              ) : (
                <div className="job-price">
                  {priceCurrencySymbol}{' '}
                  {this.getCurrencyPrice(
                    this.state.chunkQuote.get('r_price'),
                  ).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')}
                </div>
              )}
            </div>
            {!this.state.errorQuote ? (
              !this.state.needItFaster ? (
                <div className="delivery-order">
                  <div className="delivery-box">
                    <label>Delivery date:</label>

                    <div className="delivery-date">
                      {delivery.day + ' ' + delivery.month}
                    </div>
                    <div className="atdd">at</div>
                    <div className="delivery-time">{delivery.time}</div>

                    <div className="gmt">
                      <GMTSelect changeValue={this.changeTimezone.bind(this)} />
                      {/* <div className="gmt-outsourced"> GMT +2 </div>*/}
                    </div>

                    {!this.state.outsourceConfirmed ? (
                      <div className="need-it-faster">
                        {this.state.errorPastDate ? (
                          <div className="errors-date past-date">
                            * Chosen delivery date is in the past
                          </div>
                        ) : null}
                        {this.state.quoteNotAvailable ? (
                          <div className="errors-date generic-error">
                            * Deadline too close, pick another one.
                          </div>
                        ) : null}

                        {showDateMessage ? (
                          <div className="errors-date too-far-date">
                            We will deliver before the selected date
                            <div
                              className="tip"
                              data-tooltip="This date already provides us with all the time we need to deliver quality work at the lowest price"
                              data-position="bottom center"
                              data-variation="wide"
                            >
                              <i className="icon-info icon" />
                            </div>
                          </div>
                        ) : (
                          ''
                        )}
                        <a
                          className="faster"
                          ref={(faster) => (this.dateFaster = faster)}
                          onClick={this.needItFaster.bind(this)}
                        >
                          Need it faster?
                        </a>
                      </div>
                    ) : (
                      ''
                    )}
                  </div>
                  {this.state.outsourceConfirmed &&
                  !this.state.jobOutsourced ? (
                    <div className="confirm-delivery-input">
                      <div className="back" onClick={this.goBack.bind(this)}>
                        <a className="outsource-goBack">
                          <i className="icon-chevron-left icon" />
                          Back
                        </a>
                      </div>
                      <div className="email-confirm">
                        Insert your email and we’ll start working on your
                        project instantly.
                      </div>
                      <div className="ui input">
                        <input
                          type="text"
                          placeholder="Insert email"
                          defaultValue={email}
                        />
                      </div>
                    </div>
                  ) : (
                    ''
                  )}
                  {this.state.outsourceConfirmed && this.state.jobOutsourced ? (
                    <div className="confirm-delivery-box">
                      <div className="confirm-title">Order sent correctly</div>
                      <p>
                        Thank you for choosing our Outsource service
                        <br />
                        You will soon be contacted by a Account Manager to send
                        you an invoice
                      </p>
                    </div>
                  ) : (
                    ''
                  )}
                </div>
              ) : (
                <div className="delivery-order need-it-faster-box">
                  <a
                    className="need-it-faster-close"
                    onClick={this.needItFaster.bind(this)}
                  >
                    <i className="icon-cancel3 icon need-it-faster-close-icon" />
                  </a>
                  <div className="delivery-box">
                    <div className="ui form">
                      <div className="fields">
                        <div className="field">
                          <label>Delivery Date</label>
                          <div
                            className="ui calendar"
                            ref={(calendar) => (this.calendar = calendar)}
                          >
                            <div className="ui input">
                              <DatePicker
                                selected={this.state.deliveryDate}
                                onChange={(date) => {
                                  this.setState({
                                    deliveryDate: date,
                                  })
                                }}
                              />
                            </div>
                          </div>
                        </div>
                        <div className="field input-time">
                          <Select
                            label="Time"
                            onSelect={({id}) => {
                              this.setState({
                                selectedTime: id,
                              })
                            }}
                            activeOption={this.timeOptions.find(
                              ({id}) => id === this.state.selectedTime,
                            )}
                            options={this.timeOptions}
                          />
                        </div>
                        <div className="field gmt">
                          <GMTSelect
                            showLabel={true}
                            changeValue={this.changeTimezone.bind(this)}
                          />
                        </div>
                        <div className="field">
                          <button
                            className="get-price ui blue basic button"
                            onClick={this.getNewRates.bind(this)}
                          >
                            Get Price
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )
            ) : (
              <div className="delivery-order-not-available">
                <div className="quote-not-available-message">
                  Quote not available, please contact us at info@translated.net
                  or call +39 06 90 254 001
                </div>
              </div>
            )}

            {!this.state.errorQuote ? (
              <div className="order-box-outsource">
                <div className="order-box">
                  <div className="outsource-price">
                    {priceCurrencySymbol}{' '}
                    {price.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')}
                  </div>
                  <DropdownMenu
                    toggleButtonProps={{
                      children: (
                        <>
                          <a className="price-pw">
                            about {priceCurrencySymbol} {pricePWord} / word
                          </a>
                        </>
                      ),
                      mode: BUTTON_MODE.LINK,
                    }}
                    items={Object.keys(this.currencies).map((key) => {
                      return {
                        label: this.currencies[key].name,
                        onClick: () => {
                          this.onCurrencyChange(key)
                        },
                      }
                    })}
                  />
                </div>
                <div className="order-button-outsource">
                  {!this.state.outsourceConfirmed ? (
                    <button
                      className="open-order ui green button"
                      id="accept-outsource-quote"
                      onClick={this.sendOutsource.bind(this)}
                    >
                      Order now
                    </button>
                  ) : !this.state.jobOutsourced ? (
                    <button
                      className="open-order ui green button"
                      id="accept-outsource-quote"
                      onClick={this.sendOutsource.bind(this)}
                    >
                      Confirm
                    </button>
                  ) : (
                    <button
                      className="open-outsourced ui button "
                      id="accept-outsource-quote"
                      onClick={this.openOutsourcePage.bind(this)}
                    >
                      View status
                    </button>
                  )}
                </div>
              </div>
            ) : null}
          </div>
        ) : (
          <div className="payment-details-box">{this.getLoaderHtml()}</div>
        )}
        <div className="easy-pay-box">
          <h4 className="easy-pay">
            Easy payments:{' '}
            <span>Pay a single monthly invoice within 30 days of receipt</span>
          </h4>
          {/*<p>Pay a single monthly invoice within 30 days of receipt</p>*/}
        </div>
        <OutsourceInfo />
      </div>
    )
  }

  getCompactView() {
    let delivery = this.getDeliveryDate()
    let price = this.getPrice()
    let priceCurrencySymbol = this.getPriceCurrencySymbol()
    let pricePWord = this.getPricePW(price)
    let email = this.getUserEmail()
    return (
      <div className="outsource-to-vendor-reduced sixteen wide column">
        {this.state.outsource ? (
          <div className="reduced-boxes">
            <div className="container-reduced">
              <div className="title-reduced">Let us do it for you</div>

              <div className="payment-service">
                <div className="service-box">
                  <div className="service project-management">
                    Outsource: PM{' '}
                  </div>
                  <div className="service translation"> + Translation </div>
                  <div className="service revision"> + Revision</div>
                </div>
                {/*<div className="fiducial-logo">
                                <div className="translated-logo">Guaranteed by
                                    <img className="logo-t" src="/public/img/logo_translated.png" />
                                </div>
                            </div>*/}
                <div className="view-more">
                  <a
                    className="open-view-more"
                    onClick={this.viewMoreClick.bind(this)}
                  >
                    + view more
                  </a>
                </div>
              </div>
              {!this.state.errorQuote ? (
                <div className="delivery-order">
                  <div className="delivery-box">
                    <label>Delivery date:</label>
                    {/*<br />*/}
                    <div>
                      <div className="delivery-date">
                        {delivery.day + ' ' + delivery.month}
                      </div>
                      <div className="atdd">at</div>
                      <div className="delivery-time">{delivery.time}</div>

                      <div className="gmt">
                        <GMTSelect
                          direction="up"
                          changeValue={this.changeTimezone.bind(this)}
                        />
                        {/*<div className="gmt-outsourced"> GMT +2 </div>*/}
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="delivery-order-not-available">
                  <div className="quote-not-available-message">
                    Quote not available, please contact us at
                    info@translated.net or call +39 06 90 254 001
                  </div>
                </div>
              )}

              {/*<div className="errors-date generic-error">* This is a generic error</div>*/}
              {this.state.outsourceConfirmed && !this.state.jobOutsourced ? (
                <div className="confirm-delivery-input">
                  <div className="back" onClick={this.goBack.bind(this)}>
                    <a className="outsource-goBack">
                      <i className="icon-chevron-left icon" />
                      Back
                    </a>
                  </div>
                  <div className="email-confirm">
                    Great, an Account Manager will contact you to send you the
                    invoice as a customer to this email
                  </div>
                  <div className="ui input">
                    <input
                      type="text"
                      placeholder="Insert email"
                      defaultValue={email}
                    />
                  </div>
                </div>
              ) : (
                ''
              )}
            </div>
            {!this.state.errorQuote ? (
              <div className="order-box-outsource">
                <div className="order-box">
                  <div className="outsource-price">
                    {priceCurrencySymbol}{' '}
                    {price.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')}
                  </div>
                  <DropdownMenu
                    toggleButtonProps={{
                      children: (
                        <>
                          <a className="price-pw">
                            about {priceCurrencySymbol} {pricePWord} / word
                          </a>
                        </>
                      ),
                    }}
                    items={Object.keys(this.currencies).map((key) => {
                      return {
                        label: this.currencies[key].name,
                        onClick: () => {
                          this.onCurrencyChange(key)
                        },
                      }
                    })}
                  />
                </div>
                <div className="order-button-outsource">
                  {!this.state.outsourceConfirmed ? (
                    <button
                      className="open-order ui green button"
                      onClick={this.sendOutsource.bind(this)}
                    >
                      Order now
                    </button>
                  ) : // <button className="open-order ui green button" onClick={this.confirmOutsource.bind(this)}>Order now</button>
                  !this.state.jobOutsourced ? (
                    <button
                      className="confirm-order ui green button"
                      onClick={this.sendOutsource.bind(this)}
                    >
                      Confirm
                    </button>
                  ) : (
                    <button
                      className="open-outsourced ui button "
                      href=""
                      onClick={this.openOutsourcePage.bind(this)}
                    >
                      View status
                    </button>
                  )}
                </div>
              </div>
            ) : null}
            {this.state.jobOutsourced ? (
              <div className="confirm-delivery-box">
                <div className="confirm-title">Order sent correctly</div>
                <p>Thank you for choosing our Outsource service.</p>
              </div>
            ) : (
              ''
            )}
          </div>
        ) : (
          this.getLoaderHtml()
        )}
      </div>
    )
  }

  allowHTML(string) {
    return {__html: string}
  }

  componentDidMount() {}

  componentWillUnmount() {
    // $(this.dateFaster).datetimepicker('destroy');
  }

  componentDidUpdate() {
    if (this.state.outsource) {
      if (this.state.extendedView) {
        this.revisionCheckbox.checked =
          this.state.chunkQuote.get('typeOfService') === 'premium'
            ? true
            : false
      }
    }
  }

  shouldComponentUpdate(nextProps, nextState) {
    return (
      (nextState.chunkQuote &&
        !nextState.chunkQuote.equals(this.state.chunkQuote)) ||
      nextState.outsource !== this.state.outsource ||
      nextState.extendedView !== this.state.extendedView ||
      nextState.revision !== this.state.revision ||
      nextState.timezone !== this.state.timezone ||
      nextState.outsourceConfirmed !== this.state.outsourceConfirmed ||
      nextState.jobOutsourced !== this.state.jobOutsourced ||
      nextState.errorPastDate !== this.state.errorPastDate ||
      nextState.quoteNotAvailable !== this.state.quoteNotAvailable ||
      nextState.needItFaster !== this.state.needItFaster ||
      nextState.deliveryDate !== this.state.deliveryDate ||
      nextState.selectedTime !== this.state.selectedTime
    )
  }

  render() {
    let containerClass = !this.state.extendedView ? 'compact-background' : ''
    if (this.state.errorOutsource) {
      return (
        <div className={'background-outsource-vendor ' + containerClass}>
          <div className="outsource-to-vendor-reduced sixteen wide column">
            <div className="outsource-not-available">
              <div className="outsource-not-available-message">
                Quote not available, please contact us at info@translated.net or
                call +39 06 90 254 001
              </div>
            </div>
          </div>
        </div>
      )
    } else {
      return (
        <div className={'background-outsource-vendor ' + containerClass}>
          {this.state.extendedView
            ? this.getExtendedView()
            : this.getCompactView()}
          <form
            id="continueForm"
            action={config.outsource_service_login}
            method="POST"
            target="_blank"
            ref={(form) => (this.outsourceForm = form)}
          >
            <input type="hidden" name="url_ok" value="" />
            <input type="hidden" name="url_ko" value="" />
            <input type="hidden" name="confirm_urls" value="" />
            <input type="hidden" name="data_key" value="" />
            <input type="hidden" name="quoteData" value="" />
          </form>
        </div>
      )
    }
  }
}

export default OutsourceVendor
