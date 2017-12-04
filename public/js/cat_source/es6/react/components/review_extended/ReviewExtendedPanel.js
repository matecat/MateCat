class ReviewExtendedPanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {

        };

    }


    componentDidMount() {
        //TODO: togliere l'inizializzazione generica
        $('.ui.accordion')
            .accordion()
        ;
        $('.ui.dropdown')
            .dropdown()
        ;
    }

    componentWillUnmount() {

    }

    render() {
        return <div className="re-track-changes-box">
            <div className="re-header-track">
                <h4>Revise Track changes</h4>
                <div className="re-track-changes">
                    Prova <span className="deleted"> per track</span> changes <span className="added">che bella</span> la vita
                </div>
                <div className="explain-selection">
                    Select a <div className="selected">word</div> or <div className="selected">more words</div> to create a specific inssue card
                </div>
                <div className="error-list-box">
                    <div className="ui accordion">
                        <h4 className="title active">
                            Error list <i className="dropdown icon" />
                        </h4>
                        {/*<div className="issues-scroll">
                            <a href="issues-created">Issues Created (<span className="issues-number">2</span>)</a>
                        </div>*/}
                        <div className="error-list active">

                            <div className="error-item">
                                <div className="error-name">Error 1</div>
                                <div className="error-level">
                                    <select className="ui dropdown">
                                        <option value="0">Neutral</option>
                                        <option value="1">Minor</option>
                                        <option value="2">Major</option>
                                        <option value="2">Critical</option>
                                    </select>
                                </div>
                            </div>

                            <div className="error-item">
                                <div className="error-name">Error 1</div>
                                <div className="error-level">
                                    <select className="ui dropdown">
                                        <option value="0">Neutral</option>
                                        <option value="1">Minor</option>
                                        <option value="2">Major</option>
                                        <option value="2">Critical</option>
                                    </select>
                                </div>
                            </div>

                            <div className="error-item">
                                <div className="error-name">Error 1</div>
                                <div className="error-level">
                                    <select className="ui dropdown">
                                        <option value="0">Neutral</option>
                                        <option value="1">Minor</option>
                                        <option value="2">Major</option>
                                        <option value="2">Critical</option>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <section className="issues">
                    <h4>Issues</h4>
                    <div className="issues-list">
                        <div className="issue">
                            <div className="issue-head">
                                <div className="issue-number">(3)</div>
                                <div className="issue-title">Terminology:</div>
                                <div className="issue-severity">Major</div>
                            </div>
                            <div className="issue-activity-icon">
                                <div className="icon-buttons">
                                    <button><i className="icon-eye icon" /></button>
                                    <button><i className="icon-uniE96E icon" /></button>
                                    <button><i className="icon-trash-o icon" /></button>
                                </div>
                            </div>
                            <div className="selected-text">
                                <div></div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>;
    }
}

export default ReviewExtendedPanel ;
