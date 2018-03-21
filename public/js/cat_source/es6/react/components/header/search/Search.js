var React = require('react');
var SegmentConstants = require('../../../constants/SegmentConstants');
var SegmentStore = require('../../../stores/SegmentStore');

class Search extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            isReview: props.isReview,
            searchable_statuses: props.searchable_statuses,
            showReplaceOptionsInSearch: !ReviewImproved.enabled()
        };
        this.handleSubmit = this.handleSubmit.bind(this);
    }


    componentDidMount() {
    }

    componentWillUnmount() {
    }

    handleSubmit(event){
        event.preventDefault();
        if ($("#exec-find").attr('disabled') != 'disabled')
            $("#exec-find").click();
    }

    render() {

        let options = config.searchable_statuses.map(function(item, index) {
            return <option key={index} value={item.value}>{item.label}</option>;
        });
        return <div className="searchbox">
            <form onSubmit={this.handleSubmit}>
                <div className="search-inputs">
                    <div className="block">
                        <label htmlFor="search-source">Find in source</label>
                        <input id="search-source" className="search-input" type="text" defaultValue=""/>
                    </div>
                    <div className="block">
                        <div className="field">
                            <label htmlFor="search-target">Find in target</label>
                            <input id="search-target" className="search-input" type="text" defaultValue=""/>
                        </div>

                        {this.state.showReplaceOptionsInSearch ?
                            <div className="field">
                                <input id="enable-replace" type="checkbox"/>
                                <label htmlFor="enable-replace">Replace with</label>
                                <input id="replace-target" className="search-input" type="text" defaultValue=""/>
                            </div>
                            : null}
                    </div>
                    <div className="block">
                        <label htmlFor="select-status">Status</label>
                        <select id="select-status" className="search-select" defaultValue="all">
                            <option value="all">All</option>
                            {options}
                        </select>
                    </div>
                    <div className="block right buttons">
                        <div className="field">
                            <input id="exec-cancel" type="button" className="btn" defaultValue="Cancel"/>
                            <input id="exec-find" type="submit" className="btn" data-func="find" defaultValue="Find"/>
                        </div>
                        {this.state.showReplaceOptionsInSearch ?
                            <div className="field">
                                <input id="exec-replaceall" type="button" className="btn" disabled="disabled"
                                       defaultValue="Replace all"/>
                                <input id="exec-replace" type="button" className="btn" disabled="disabled"
                                       defaultValue="Replace"/>
                            </div>
                            : null}
                    </div>
                </div>
                <div className="search-options">
                    <div className="block">
                        <input id="match-case" type="checkbox"/>
                        <label htmlFor="match-case">Match case</label>
                    </div>
                    <div className="block">
                        <input id="exact-match" type="checkbox"/>
                        <label htmlFor="exact-match">Segment exact match</label>
                    </div>
                </div>
            </form>
            <div className="search-display">
                <p className="searching">Searching ...</p>
                <p className="found"><span className="numbers">Found <span
                    className="results">...</span> results in <span
                    className="segments">...</span> segments</span> having<span className="query">...</span></p>
            </div>
        </div>
    }
}

export default Search;