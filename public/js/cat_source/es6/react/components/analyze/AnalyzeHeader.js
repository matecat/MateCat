let CSSTransitionGroup = React.addons.CSSTransitionGroup;
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class AnalyzeHeader extends React.Component {

    constructor(props) {
        super(props);
        this.previousQueueSize = 0;
        this.lastProgressSegments = 0;
        this.noProgressTail = 0;
        this.state = {
        };
    }


    getAnalysisStateHtml() {
        this.showProgressBar = false;

        let html = <div className="analysis-create">
            <div className="search-tm-matches">
                <div className="ui active inline loader"/>
                <div className="complete">Fast word counting...</div>
            </div>
        </div>;
        let status = this.props.data.get('STATUS');
        let in_queue_before = parseInt(this.props.data.get('IN_QUEUE_BEFORE'));
        if( status === 'DONE' ) {
             html = <div className="analysis-create">
                <div className="search-tm-matches hide" ref={(container) => this.containerAnalysisComplete = container}>
                    <h5 className="complete">Analysis:
                        <span>complete <i className="icon-checkmark icon" /></span>
                    </h5>
                    <a className="downloadAnalysisReport" onClick={this.downloadAnalysisReport.bind(this)}>Download Analysis Report</a>
                </div>
            </div>;

        } else if ( (status === 'NEW') || (status === '') || in_queue_before > 0 ) {
            if ( config.daemon_warning ) {

                html = this.errorAnalysisHtml();

            } else if ( in_queue_before > 0 ) {
                if ( this.previousQueueSize <= in_queue_before ) {
                    html = <div className="analysis-create">
                        <div className="search-tm-matches">
                            <div style={{top: '-12px'}} className="ui active inline loader right-15"/>
                            <span className="complete">Please wait... <p className="label">There are other projects in queue. </p></span>

                        </div>
                    </div>
                } else { //decreasing ( TM analysis on another project )
                    html = <div className="analysis-create">
                        <div className="search-tm-matches">
                            <div style={{top: '-12px'}} className="ui active inline loader right-15"/>
                            <span className="complete">Please wait...
                            <p className="label">There are still <span className="number">{this.props.data.get('IN_QUEUE_BEFORE_PRINT') }</span> segments in queue.</p>
                            </span>
                        </div>
                    </div>
                }
            }
            this.previousQueueSize = in_queue_before;

        } else if ( status === 'FAST_OK' && in_queue_before === 0 ) {

            if ( this.lastProgressSegments !== this.props.data.get('SEGMENTS_ANALYZED') ) {

                this.lastProgressSegments = this.props.data.get('SEGMENTS_ANALYZED');
                this.noProgressTail = 0;
                this.showProgressBar = true;
                html =  this.getProgressBarText();

            } else {

                this.noProgressTail++;
                if ( this.noProgressTail > 9 ) {
                    html = this.errorAnalysisHtml();
                }

            }

        }  else if( status === 'NOT_TO_ANALYZE' ){
            html = <div className="analysis-create">
                <div className="search-tm-matches">
                    <div className="complete">This job is too big.</div>
                    <div className="analysisNotPerformed">The analysis was not performed.</div>
                </div>
            </div>

        } else if (status === 'EMPTY'){
            let error = '';
            if ( config.support_mail.indexOf( '@' ) === -1 ) {
                error = config.support_mail;
            } else {
                error = <a href="mailto: + config.support_mail + "> {config.support_mail} </a>;
            }
            html =  <div className="analysis-create">
                <div className="search-tm-matches">
                    <span className="complete">Ops.. we got an error. No text to translate in the file {this.props.data.get('NAME')}.</span><br/>
                    <span className="analysisNotPerformed">Contact {error}</span>

                </div>
            </div>;
        } else {  // Unknown error :)
            html = this.errorAnalysisHtml();
        }
        return html;
    }

    errorAnalysisHtml() {
        let analyzerNotRunningErrorString;
        if ( config.support_mail.indexOf( '@' ) === -1 ) {
            analyzerNotRunningErrorString = <p className="label">The analysis seems not to be running. Contact {config.support_mail}.</p>;
        } else {
            analyzerNotRunningErrorString = <p className="label">The analysis seems not to be running. Contact <a href={"mailto: " + config.support_mail}>{config.support_mail}</a>.</p>;
        }
        return <div className="analysis-create">
            <div className="search-tm-matches">
                <span className="complete">{analyzerNotRunningErrorString}</span>
            </div>
        </div>;
    }

    getProgressBarText() {
        return  <div className="analysis-create">
                    <div className="search-tm-matches">
                        <h5>Searching for TM Matches </h5>
                        <span className="initial-segments"> ({this.props.data.get('SEGMENTS_ANALYZED_PRINT')} of </span>
                        <span className="total-segments"> {" " + this.props.data.get('TOTAL_SEGMENTS_PRINT')})</span>
                    </div>
                </div>;
    }

    getProgressBar() {
        if (this.showProgressBar) {
            let width = ((this.props.data.get('SEGMENTS_ANALYZED') / this.props.data.get('TOTAL_SEGMENTS')) * 100) + '%';
            return <div className="progress-bar">
                    <div className="progr">
                        <div className="meter">
                            <a className="approved-bar translate-tooltip"  data-html={'Approved ' + width}  style={{width: width}}/>
                        </div>
                    </div>
                </div>;
        }
        return null

    }

    getWordscount() {
        let tooltipText = 'MateCat suggests MT only when it helps thanks to a dynamic penalty system. We learn when to ' +
            'offer machine translation suggestions or translation memory matches thanks to the millions ' +
            'of words corrected by the MateCat community.<br> This data is also used to define a fair pricing ' +
            'scheme that splits the benefits of the technology between the customer and the translator.';

        let status = this.props.data.get('STATUS');
        let raw_words = this.props.data.get('TOTAL_RAW_WC'), weightedWords = '';
        if ( ((status === 'NEW') || (status === '') || this.props.data.get('IN_QUEUE_BEFORE') > 0) && config.daemon_warning ) {
            weightedWords = this.props.data.get('TOTAL_RAW_WC');
        } else {
            if ( status === 'DONE' || this.props.data.get('TOTAL_PAYABLE') > 0 ) {
                weightedWords = this.props.data.get('TOTAL_PAYABLE');
            }
            if( status === 'NOT_TO_ANALYZE' ) {
                weightedWords = this.props.data.get('TOTAL_RAW_WC');
            }
        }
        let saving_perc = (raw_words > 0 ) ? parseInt((raw_words - weightedWords)/raw_words * 100) + "%" : '0%';
        if (saving_perc !== this.saving_perc_value) {
            this.updatedSavingWords = true;
        }
        this.saving_perc_value = saving_perc;


        return <div className="word-count ui grid">
                <div className="sixteen wide column">
                    <div className="word-percent " ref={(container) => this.containerSavingWords = container}>
                        <h2 className="ui header">
                            <div className="percent">{saving_perc}</div>
                            <div className="content">
                                Saving on word count
                                <div className="sub header">
                                    {this.props.data.get('PAYABLE_WC_TIME')} work {this.props.data.get('PAYABLE_WC_UNIT')} at 3.000 w/day
                                </div>
                            </div>
                        </h2>
                        <p>MateCat gives you more matches than any other tool thanks to a better
                            integration of machine translation and translation memories.
                            <span style={{marginLeft: '2px'}} data-html={tooltipText} ref={(tooltip) => this.tooltip = tooltip}>
                                <span className="icon-info icon" style={{position: 'relative', top: '2px', color: '#a7a7a7'}}/>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
    }

    downloadAnalysisReport() {
        UI.downloadAnalysisReport();
    }

    componentDidUpdate() {
        let self = this;
        if (this.updatedSavingWords) {
            this.containerSavingWords.classList.add('updated-count');
            this.updatedSavingWords = false;
            setTimeout(function () {
                self.containerSavingWords.classList.remove('updated-count');
            }, 400)
        }
        let status = this.props.data.get('STATUS');
        if (status === 'DONE') {
            setTimeout(function () {
                self.containerAnalysisComplete.classList.remove('hide');
            }, 600)
        }

    }

    componentDidMount() {
        $(this.tooltip).popup({
            position: 'bottom center'
        });
        let self = this;
        let status = this.props.data.get('STATUS');
        if (status === 'DONE') {
            this.containerSavingWords.classList.add('updated-count');
            setTimeout(function () {
                self.containerSavingWords.classList.remove('updated-count');
                self.containerAnalysisComplete.classList.remove('hide');
            }, 400)
        }
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return ( !nextProps.data.equals(this.props.data))
    }

    render() {
        let analysisStateHtml = this.getAnalysisStateHtml();
        let wordsCountHtml = this.getWordscount();
        let projectName = (this.props.project.get('name')) ? this.props.project.get('name') : "";
        return <div className="project-header ui grid">
            <div className="left-analysis nine wide column">
                <h1>Volume Analysis</h1>
                <div className="ui ribbon label">
                    <div className="project-name" title="Project name"> {projectName} </div>
                </div>
                {analysisStateHtml}
            </div>

            <div className="seven wide right floated column">
                {wordsCountHtml}
            </div>
            <CSSTransitionGroup component="div" className="progress sixteen wide column"
                                transitionName="transition"
                                transitionEnterTimeout={500}
                                transitionLeaveTimeout={500}
            >
                {this.getProgressBar()}
            </CSSTransitionGroup>


        </div>;


    }
}

export default AnalyzeHeader;
