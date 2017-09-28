let OutsourceConstants = require('../../constants/OutsourceConstants');
let AssignToTranslator = require('./AssignToTranslator').default;
let OutsourceVendor = require('./OutsourceVendor').default;
let OpenJobBox = require('./OpenJobBox').default;
let CSSTransitionGroup = React.addons.CSSTransitionGroup;


class OutsourceContainer extends React.Component {


    constructor(props) {
        super(props);
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this._handleEscKey = this._handleEscKey.bind(this);
        this.checkTimezone();
    }

    allowHTML(string) {
        return { __html: string };
    }

    checkTimezone() {
        var timezoneToShow = $.cookie( "matecat_timezone" );
        if ( !timezoneToShow) {
            timezoneToShow = -1 * ( new Date().getTimezoneOffset() / 60 );
            $.cookie( "matecat_timezone" , timezoneToShow);
        }
    }

    getProjectAnalyzeUrl() {
        return '/analyze/' + this.props.project.get('project_slug') + '/' +this.props.project.get('id')+ '-' + this.props.project.get('password');
    }

    handleDocumentClick(evt)  {
        evt.stopPropagation();
        const area = ReactDOM.findDOMNode(this.container);

        if (this.container && !area.contains(evt.target) &&
            !$(evt.target).hasClass('open-view-more') &&
            !$(evt.target).hasClass('outsource-goBack') &&
            !$(evt.target).hasClass('faster') &&
            !$(evt.target).hasClass('need-it-faster-close') &&
            !$(evt.target).hasClass('need-it-faster-close-icon') &&
            !$(evt.target).hasClass('get-price')) {
            this.props.onClickOutside(evt)
        }
    }

    _handleEscKey(event){
        if(event.keyCode === 27){
            event.preventDefault();
            event.stopPropagation();
            this.props.onClickOutside();
        }
    }

    componentDidMount () {}

    componentWillUnmount() {
        window.removeEventListener('click', this.handleDocumentClick);
        window.removeEventListener("keydown", this._handleEscKey);
    }

    componentDidUpdate(prevProps, prevState) {
        let self = this;
        if (this.props.openOutsource) {
            setTimeout(function () {
                window.addEventListener('click', self.handleDocumentClick);
                window.addEventListener("keydown", self._handleEscKey);
                $('html, body').animate({
                    scrollTop: $(self.container).offset().top - 55
                }, 500);
            }, 500);

        } else {
            window.removeEventListener('click', self.handleDocumentClick);
            window.removeEventListener("keydown", self._handleEscKey);
            if (prevProps.openOutsource) {
                $('html, body').animate({
                    scrollTop: $(self.container).offset().top - 200
                }, 200);
            }
        }
        $(this.languageTooltip).popup();
    }

    render() {
        let outsourceContainerClass = (!config.enable_outsource) ? ('no-outsource') :
            ((this.props.showTranslatorBox) ? 'showTranslator' : ((this.props.showOpenBox)? 'showOpenBox': 'showOutsource') );

        return <CSSTransitionGroup component="div" className="ui grid"
                                   transitionName="transitionOutsource"
                                   transitionEnterTimeout={400}
                                   transitionLeaveTimeout={100}
        >
            {this.props.openOutsource ? (
                <div className={"outsource-container chunk ui grid " + outsourceContainerClass} ref={(container)=>this.container=container}>
                    <div className=" outsource-header sixteen wide column shadow-1">
                        {this.props.idJobLabel ? (
                            <div className="job-id" title="Job Id">
                                ID: {this.props.idJobLabel}
                            </div>
                        ) :(null)}
                        <div className="source-target languages-tooltip"
                             ref={(tooltip) => this.languageTooltip = tooltip}
                             data-html={this.props.job.get('sourceTxt') + ' > ' + this.props.job.get('targetTxt')} data-variation="tiny">
                            <div className="source-box">
                                {this.props.job.get('sourceTxt')}
                            </div>
                            <div className="in-to"><i className="icon-chevron-right icon"/></div>
                            <div className="target-box">
                                {this.props.job.get('targetTxt')}
                            </div>
                        </div>
                        <div className="job-payable">
                            <div><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</div>
                        </div>
                    </div>
                    <div className="sixteen wide column shadow-1">
                        <div className="ui grid"
                        ref={(container) => this.container = container}>
                                {(this.props.showTranslatorBox ) ? (
                                    <AssignToTranslator job={this.props.job}
                                                        url={this.props.url}
                                                        project={this.props.project}
                                                        showOpenBox={this.props.showOpenBox}
                                                        closeOutsource={this.props.onClickOutside}/>
                                ) : (null)}

                                {(this.props.showOpenBox ) ? (
                                    <OpenJobBox job={this.props.job}
                                                url={this.props.url}
                                                project={this.props.project}/>
                                ) : (null)}

                                {( (this.props.showTranslatorBox || this.props.showOpenBox) && config.enable_outsource ) ? (
                                    <div className="divider-or sixteen wide column">
                                        <div className="or">
                                            or
                                        </div>
                                    </div>
                                ) : (null)}
                                {config.enable_outsource ? (
                                    <OutsourceVendor project={this.props.project}
                                                     job={this.props.job}
                                                     extendedView={this.props.extendedView}
                                                     standardWC={this.props.standardWC}/>
                                ) :(null)}

                        </div>
                    </div>
                </div>
            ) : (null)}
        </CSSTransitionGroup>;
    }
}
OutsourceContainer.defaultProps = {
    showTranslatorBox: true,
    extendedView: true,
    showOpenBox: false
};

export default OutsourceContainer ;