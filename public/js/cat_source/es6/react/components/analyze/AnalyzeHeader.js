
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class AnalyzeHeader extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidUpdate() {
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return true;
    }

    getDate() {
        var date = this.props.project.
    }

    render() {
        return <div className="project-header ui grid">
                    <div className="left-analysis nine wide column">
                        <h1>Volume Analysis</h1>
                        <div className="ui ribbon label">
                            <div className="project-id" title="Project id"> ({this.props.project.id}) </div>
                            <div className="project-name" title="Project name"> {this.props.project.name} </div>
                        </div>
                        <div className="project-create">Created on Fri, March 03 2017</div>
                        <div className="analysis-create">
                            <div className="search-tm-matches">
                                <h5>Searching for TM Matches </h5>
                                <div className="initial-segments"> (71 of </div>
                                <div className="total-segments">2393)</div>
                                <div className="progress-bar">
                                    <div className="progr">
                                        <div className="meter">
                                            <a className="approved-bar translate-tooltip"  data-html="Approved 100%" style={{width: "60%"}}/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
        
                    <div className="seven wide right floated column">
                        <div className="word-count ui grid">
                            <div className="sixteen wide column">
                                <div className="word-percent ">
                                    <h2 className="ui header">
                                        <div className="percent">30%</div>
                                        <div className="content">
                                            Saving on word count
                                            <div className="sub header">27 work minutes at 3.000 w/day
                                            </div>
                                        </div>
                                    </h2>
                                    <p>MateCat gives you more matches than any other CAT tool thanks to a mix of public and private translation memories, and machine translation.
                                    </p>
                                </div>
                            </div>
                            <div className="sixteen wide column pad-top-0">
                                <div className="raw-matecat ui grid">
                                    <div className="eight wide column pad-right-7">
                                        <div className="word-raw">
                                            <h3>6’233.332</h3>
                                            <h4>Raw words</h4>
                                        </div>
                                        <div className="overlay"/>
                                    </div>
                                    <div className="eight wide column pad-left-7">
                                        <div className="matecat-raw ">
                                            <h3>6’233.332</h3>
                                            <h4>Raw words</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>;


    }
}

export default AnalyzeHeader;
