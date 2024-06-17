import React from 'react'
import Cookies from 'js-cookie'

import {TransitionGroup, CSSTransition} from 'react-transition-group'

import AssignToTranslator from './AssignToTranslator'
import OutsourceVendor from './OutsourceVendor'

class OutsourceContainer extends React.Component {
  constructor(props) {
    super(props)
    this.handleDocumentClick = this.handleDocumentClick.bind(this)
    this._handleEscKey = this._handleEscKey.bind(this)
    this.checkTimezone()
  }

  allowHTML(string) {
    return {__html: string}
  }

  checkTimezone() {
    var timezoneToShow = Cookies.get('matecat_timezone')
    if (!timezoneToShow) {
      timezoneToShow = -1 * (new Date().getTimezoneOffset() / 60)
      Cookies.set('matecat_timezone', timezoneToShow, {secure: true})
    }
  }

  handleDocumentClick(evt) {
    evt.stopPropagation()

    if (
      this.container &&
      !this.container.contains(evt.target) &&
      !$(evt.target).hasClass('open-view-more') &&
      !$(evt.target).hasClass('outsource-goBack') &&
      !$(evt.target).hasClass('faster') &&
      !$(evt.target).hasClass('need-it-faster-close') &&
      !$(evt.target).hasClass('need-it-faster-close-icon') &&
      !$(evt.target).hasClass('get-price') &&
      !$(evt.target).hasClass('react-datepicker__day')
    ) {
      this.props.onClickOutside(evt)
    }
  }

  _handleEscKey(event) {
    if (event.keyCode === 27) {
      event.preventDefault()
      event.stopPropagation()
      this.props.onClickOutside()
    }
  }

  componentDidMount() {}

  componentWillUnmount() {
    window.removeEventListener('click', this.handleDocumentClick)
    window.removeEventListener('keydown', this._handleEscKey)
  }

  componentDidUpdate() {
    let self = this
    if (this.props.openOutsource || this.props.showTranslatorBox) {
      setTimeout(function () {
        window.addEventListener('click', self.handleDocumentClick)
        window.addEventListener('keydown', self._handleEscKey)
        self.container && self.container.scrollIntoView({block: 'center'})
      }, 500)
    } else {
      window.removeEventListener('click', self.handleDocumentClick)
      window.removeEventListener('keydown', self._handleEscKey)
    }
    $(this.languageTooltip).popup()
  }

  render() {
    let outsourceContainerClass =
      !config.enable_outsource ||
      (this.props.showTranslatorBox && !this.props.openOutsource)
        ? 'no-outsource'
        : this.props.showTranslatorBox && this.props.openOutsource
          ? 'showTranslator'
          : this.props.openOutsource
            ? 'showOutsource'
            : ''

    return (
      <TransitionGroup>
        {this.props.openOutsource || this.props.showTranslatorBox ? (
          <CSSTransition
            key={this.props.idJobLabel}
            classNames="transitionOutsource"
            timeout={{enter: 500, exit: 300}}
          >
            <div
              className={
                'outsource-container chunk ui grid shadow-1 ' +
                outsourceContainerClass
              }
              ref={(container) => (this.container = container)}
            >
              <div className=" outsource-header sixteen wide column ">
                {this.props.idJobLabel ? (
                  <div className="job-id" title="Job Id">
                    ID: {this.props.idJobLabel}
                  </div>
                ) : null}
                <div
                  className="source-target languages-tooltip"
                  ref={(tooltip) => (this.languageTooltip = tooltip)}
                  data-html={
                    this.props.job.get('sourceTxt') +
                    ' > ' +
                    this.props.job.get('targetTxt')
                  }
                  data-variation="tiny"
                >
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
                <div className="job-payable">
                  <div>
                    <span id="words">{this.props.standardWC}</span> words
                  </div>
                </div>
                {/*<div className="project-subject">*/}
                {/*  <b>Subject</b>: {this.props.job.get('subject_printable')}*/}
                {/*</div>*/}
              </div>
              <div className="sixteen wide column">
                <div
                  className="ui grid"
                  ref={(container) => (this.container = container)}
                >
                  {this.props.showTranslatorBox ? (
                    <AssignToTranslator
                      job={this.props.job}
                      url={this.props.url}
                      project={this.props.project}
                      closeOutsource={this.props.onClickOutside}
                    />
                  ) : null}
                  {config.enable_outsource && this.props.openOutsource ? (
                    <OutsourceVendor
                      project={this.props.project}
                      job={this.props.job}
                      extendedView={this.props.extendedView}
                      standardWC={this.props.standardWC}
                      translatorsNumber={this.translatorsNumber}
                    />
                  ) : null}
                </div>
              </div>
            </div>
          </CSSTransition>
        ) : null}
      </TransitionGroup>
    )
  }
}
OutsourceContainer.defaultProps = {
  showTranslatorBox: true,
  extendedView: true,
}

export default OutsourceContainer
