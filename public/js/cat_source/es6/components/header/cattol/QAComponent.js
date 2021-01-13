/**
 * React Component for the editarea.

 */
import React  from 'react';
import LXQ from '../../../utils/lxq.main';

class QAComponent extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            navigationList: [],
            navigationIndex: 0,
            currentPriority: '',
            currentCategory: '',
            labels: {
                TAG: 'Tag',
                TAGS: 'Tag',
                lexiqa: 'Lexiqa',
                GLOSSARY: 'Glossary',
                MISMATCH: 'T. Conflicts'
            }
        };
    }

    scrollToSegment(increment) {

        let newIndex = (this.state.navigationIndex + increment);
        newIndex = newIndex === -1 ? this.state.navigationList.length - 1 : newIndex % this.state.navigationList.length;

        let segmentId = this.state.navigationList[newIndex];

        if (segmentId) {
            SegmentActions.openSegment(segmentId);
        }
        this.setState({
            navigationIndex: newIndex
        })
    }

    setCurrentNavigationElements(list, priority, category) {

        let segmentId = list[0];

        if (segmentId) {
            setTimeout(function (  ) {
                SegmentActions.scrollToSegment( segmentId );
            });
            SegmentActions.openSegment(segmentId);
        }

        this.setState({
            navigationList: list,
            navigationIndex: 0,
            currentPriority: priority,
            currentCategory: category
        })
    }

    allowHTML(string) {
        return {__html: string};
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    static getDerivedStateFromProps(props, state) {
        const category = (props.warnings[state.currentPriority]) ?
            props.warnings[state.currentPriority].Categories[state.currentCategory] : null;
        if (props.warnings && category) {
            return {
                navigationList: category
            };
        }else{
            return {
                navigationList: []
            };
        }
    }

    render() {

        let mismatch = '',
            error = [],
            warning = [],
            info = [];
        if (this.props.warnings) {
            if (this.props.warnings.ERROR.total > 0) {
                Object.keys(this.props.warnings.ERROR.Categories).map((key, index) => {
                    if (this.props.warnings.ERROR.Categories[key].length > 0) {
                        if (key === 'TAGS') {
                            let activeClass = (this.state.currentPriority === 'ERROR' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                            error.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                               onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.ERROR.Categories[key], 'ERROR', key)}>
                                <i className="icon-cancel-circle icon"></i>
                                {this.state.labels[key] ?
                                    this.state.labels[key] : key} errors
                                <b> ({this.props.warnings.ERROR.Categories[key].length})</b>
                            </button>)
                        } else {
                            let activeClass = (this.state.currentPriority === 'ERROR' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                            error.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                               onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.ERROR.Categories[key], 'ERROR', key)}>
                                <i className="icon-cancel-circle icon"></i>
                                {this.state.labels[key] ?
                                    this.state.labels[key] : key}
                                <b> ({this.props.warnings.ERROR.Categories[key].length})</b>
                            </button>)
                        }
                    }
                })
            }
            if (this.props.warnings.WARNING.total > 0) {
                Object.keys(this.props.warnings.WARNING.Categories).map((key, index) => {
                    if (this.props.warnings.WARNING.Categories[key].length > 0) {
                        let activeClass = (this.state.currentPriority === 'WARNING' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                        if (key === 'TAGS') {
                            warning.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                                 onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNING.Categories[key], 'WARNING', key)}>
                                <i className="icon-warning2 icon"></i>
                                {this.state.labels[key] ?
                                    this.state.labels[key] : key} warnings
                                <b> ({this.props.warnings.WARNING.Categories[key].length}) </b>
                            </button>)
                        }else if (key !== 'MISMATCH') {
                            warning.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                                 onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNING.Categories[key], 'WARNING', key)}>
                                <i className="icon-warning2 icon"></i>
                                {this.state.labels[key] ?
                                    this.state.labels[key] : key}
                                <b> ({this.props.warnings.WARNING.Categories[key].length}) </b>
                            </button>)
                        } else {
                            mismatch = <button key={index} className={"ui button qa-issue" + activeClass}
                                               onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNING.Categories[key], 'WARNING', key)}>
                                <i className="icon-warning2 icon"></i>
                                {this.state.labels[key] ?
                                    this.state.labels[key] : key}
                                <b> ({this.props.warnings.WARNING.Categories[key].length}) </b>
                            </button>
                        }
                    }

                })
            }
            if (this.props.warnings.INFO.total > 0) {
                Object.keys(this.props.warnings.INFO.Categories).map((key, index) => {
                    if (this.props.warnings.INFO.Categories[key].length > 0) {
                        let activeClass = (this.state.currentPriority === 'INFO' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                        info.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                          onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.INFO.Categories[key], 'INFO', key)}>
                            {this.state.labels[key] ?
                                this.state.labels[key] : key} <b> ({this.props.warnings.INFO.Categories[key].length})</b>
                        </button>)
                    }
                })
            }
        }
        let segmentsWithActive = (error.length > 0 || warning.length > 0 || info.length > 0) ? true : false;
        return ((this.props.active && this.props.totalWarnings > 0) ? <div className="qa-wrapper">
            <div className="qa-container">
                <div className="qa-container-inside">
                    <div className="qa-issues-list">
                        {(segmentsWithActive) ?
                            <div className="label-issues label-issues-segment">
                                Segments with:
                            </div> : null}
                        {(segmentsWithActive) ?
                            <div className="ui basic tiny buttons">
                                {error}
                                {warning}
                                {info}
                            </div> : null}
                        {(this.state.currentPriority === 'INFO' && this.state.currentCategory === 'lexiqa') ?
                            <div className="qa-lexiqa-info">
                                <span>QA:</span>
                                <a href={config.lexiqaServer + '/documentation.html'} target="_blank">Guide</a>
                                <a target="_blank" alt="Read the full QA report"
                                   href={config.lexiqaServer + '/errorreport?id=' + LXQ.partnerid + '-' + config.id_job + '-' + config.password + '&type=' + (config.isReview ? 'revise' : 'translate')}>Report</a>
                            </div> : null}
                        {mismatch ? <div className="label-issues labl">
                            Repetitions with:
                        </div> : null}
                        {mismatch ? <div className="qa-mismatch">
                            <div className="ui basic tiny buttons">
                                {mismatch}
                            </div>
                        </div> : null}
                    </div>
                    {this.state.navigationList.length > 0 ? <div className="qa-issues-navigator">
                        <div className="qa-actions">
                            <div className={'qa-arrows qa-arrows-enabled'}>
                                <button className="qa-move-up ui basic button"
                                    /*disabled={this.state.navigationIndex - 1 < 0}*/
                                        onClick={this.scrollToSegment.bind(this, -1)}>
                                    <i className="icon-chevron-left"/>
                                </button>
                                <div className="info-navigation-issues">
                                    <b>{this.state.navigationIndex + 1} </b> / {this.state.navigationList.length} {/*Segments*/}
                                </div>
                                <button className="qa-move-down ui basic button"
                                    /*disabled={this.state.navigationIndex + 1 >= this.state.navigationList.length}*/
                                        onClick={this.scrollToSegment.bind(this, 1)}>
                                    <i className="icon-chevron-right"/>
                                </button>
                            </div>
                        </div>
                    </div> : null}

                </div>
            </div>
        </div> : null)
    }
}

export default QAComponent;

