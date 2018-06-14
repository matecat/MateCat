var React = require('react');
var SegmentConstants = require('../../../constants/SegmentConstants');
var SegmentStore = require('../../../stores/SegmentStore');

class Search extends React.Component {

    constructor(props) {
        super(props);
        this.defaultState = {
            isReview: props.isReview,
            searchable_statuses: props.searchable_statuses,
            showReplaceOptionsInSearch: !ReviewImproved.enabled(),
            search: {
                enableReplace: false,
                matchCase: false,
                exactMatch: false,
                replaceTarget: null,
                selectStatus: 'all',
                searchTarget: null,
                searchSource: null
            },
            focus: true,
            currentTargetSearch: null,
            currentSourceSearch: null,
            funcFindButton: true  // true=find / false=next
        };
        this.state = this.defaultState;

        this.handleSubmit = this.handleSubmit.bind(this);
        this.handleCancelClick = this.handleCancelClick.bind(this);
        this.handleInputChange = this.handleInputChange.bind(this);

        this.handleReplaceAllClick = this.handleReplaceAllClick.bind(this);
        this.handleReplaceClick = this.handleReplaceClick.bind(this);
        this.replaceTargetOnFocus = this.replaceTargetOnFocus.bind(this);
        this.componenDidMount = this;
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if(this.props.active){
            if(this.sourceEl && this.state.focus){
                this.sourceEl.focus();
                this.setState({
                    focus: false
                });
            }
        }else{
            if(!this.state.focus){
                this.setState({
                    focus: true
                });
            }
        }
        $('.ui.checkbox')
            .checkbox()
        ;
        $('.ui.dropdown')
            .dropdown()
        ;
    }

    handleSubmit(event) {
        event.preventDefault();
        if (this.state.funcFindButton) {
            UI.execFind();
        }
        // else {
        //     if (!UI.goingToNext) {
        //         UI.goingToNext = true;
        //         UI.execNext();
        //     }
        // }
        this.setState({
            currentSourceSearch: this.state.search.searchSource,
            currentTargetSearch: this.state.search.searchTarget,
            funcFindButton: false
        })
    }

    goToNext() {
        if (!UI.goingToNext) {
            UI.goingToNext = true;
            UI.execNext();
        }
    }

    goToPrev() {
        if (!UI.goingToNext) {
            UI.goingToNext = true;
            UI.execPrev();
        }
    }

    handleCancelClick(event) {
        event.preventDefault();
        $("#filterSwitch").click();
        UI.body.removeClass('searchActive');
        UI.clearSearchMarkers();
        UI.clearSearchFields();
        $('#exec-replace, #exec-replaceall').attr('disabled', 'disabled');
        UI.enableTagMark();
        if (UI.segmentIsLoaded(UI.currentSegmentId)) {
            UI.gotoOpenSegment();
        } else {
            UI.render({
                firstLoad: false,
                segmentToOpen: UI.currentSegmentId
            });
        }
        UI.markGlossaryItemsInSource(UI.cachedGlossaryData);
        this.setState(this.defaultState);
    }

    handleReplaceAllClick(event) {
        event.preventDefault();
        APP.confirm({
            name: 'confirmReplaceAll',
            cancelTxt: 'Cancel',
            callback: 'execReplaceAll',
            okTxt: 'Continue',
            msg: "Do you really want to replace this text in all search results? <br>(The page will be refreshed after confirm)"
        });
    }

    handleReplaceClick(event) {
        event.preventDefault();
        if(this.state.search.searchTarget === this.state.search.replaceTarget){
            APP.alert({msg: 'Attention: you are replacing the same text!'});
            return false;
        }

        if (UI.searchMode !== 'onlyStatus') {

            // todo: redo marksearchresults on the target

            $("mark.currSearchItem").text(this.state.search.replaceTarget);
            let segment = $("mark.currSearchItem").parents('section');
            let status = UI.getStatus(segment);

            UI.setTranslation({
                id_segment: $(segment).attr('id').split('-')[1],
                status: status,
                caller: 'replace'
            });

            UI.updateSearchDisplayCount(segment);

            if (UI.numSearchResultsSegments > 1) UI.gotoNextResultItem(true);
        }
    }

    handleInputChange(event) {
        //serch model
        const target = event.target;
        const value = target.type === 'checkbox' ? target.checked : target.value;
        const name = target.name;
        let search = this.state.search;
        search[name] = value;


        this.setState({
            search: search,
            funcFindButton: true
        });
    }

    replaceTargetOnFocus() {
        let search = this.state.search;
        search.enableReplace = true;
        this.setState({
            search: search
        })
    }

    render() {

        let options = config.searchable_statuses.map(function (item, index) {
            return <option key={index} value={item.value}>{item.label}</option>;
        });
        let findIsDisabled = false;
        if (!this.state.search.searchTarget && !this.state.search.searchSource) {
            findIsDisabled = true;
        }
        /*return ( this.props.active ? <div className="searchbox">
            <form onSubmit={this.handleSubmit}>
                <div className="search-inputs">
                    <div className="block">
                        <label htmlFor="search-source">Find in source</label>
                        <input id="search-source" className="search-input" type="text" name="searchSource"
                               ref={(input) => { this.sourceEl = input; }}
                               checked={this.state.search.searchSource}
                               onChange={this.handleInputChange}/>
                    </div>
                    <div className="block">
                        <div className="field">
                            <label htmlFor="search-target">Find in target</label>
                            <input id="search-target"
                                   className={"search-input " + (!this.state.search.searchTarget && this.state.search.replaceTarget ? 'warn' : null)}
                                   type="text" name="searchTarget"
                                   onChange={this.handleInputChange}
                                   defaultValue={this.state.search.searchTarget}/>
                        </div>

                        {this.state.showReplaceOptionsInSearch ?
                            <div className="field">
                                <input id="enable-replace" type="checkbox" name="enableReplace"
                                       checked={this.state.search.enableReplace}
                                       onChange={this.handleInputChange}/>
                                <label htmlFor="enable-replace">Replace target with</label>

                            </div>
                            : null}

                        {this.state.showReplaceOptionsInSearch && this.state.search.enableReplace ?
                            <div className="field">
                                <input id="replace-target" className="search-input" type="text" name="replaceTarget"
                                       onFocus={this.replaceTargetOnFocus}
                                       onChange={this.handleInputChange}
                                       defaultValue={this.state.search.replaceTarget}/>
                                <button id="exec-replaceall" className="btn" onClick={this.handleReplaceAllClick}
                                        disabled={!this.state.search.enableReplace || !this.state.search.searchTarget}>
                                    Replace all
                                </button>

                                <button id="exec-replace" className="btn" onClick={this.handleReplaceClick}
                                        disabled={!this.state.search.enableReplace || !this.state.search.searchTarget}>
                                    Replace
                                </button>
                            </div>
                            : null}
                    </div>
                    <div className="block">
                        <label htmlFor="select-status">Status</label>
                        <select id="select-status" className="search-select" name="selectStatus"
                                onChange={this.handleInputChange}
                                defaultValue={this.state.search.selectStatus}>
                            <option value="all">All</option>
                            {options}
                        </select>
                    </div>
                    <div className="block right buttons">
                        <div className="field">
                            <input id="exec-cancel" type="button" className="btn" onClick={this.handleCancelClick}
                                   defaultValue="Cancel"/>
                            <input id="exec-find" type="submit" className="btn" data-func="find"
                                   defaultValue="FIND" disabled={!this.state.funcFindButton}/>
                        </div>
                    </div>
                </div>
                <div className="search-options">
                    <div className="block">
                        <input id="match-case" type="checkbox" name="matchCase"
                               onChange={this.handleInputChange}
                               defaultValue={this.state.search.matchCase}/>
                        <label htmlFor="match-case">Match case</label>
                    </div>
                    <div className="block">
                        <input id="exact-match" type="checkbox" name="exactMatch"
                               onChange={this.handleInputChange}
                               defaultValue={this.state.search.exactMatch}/>
                        <label htmlFor="exact-match">Whole word</label>
                    </div>
                </div>
            </form>
            <div className="search-display">
                <p className="searching">Searching ...</p>
                <p className="found"><span className="numbers">Found <span
                    className="results">...</span> results in <span
                    className="segments">...</span> segments</span> having<span className="query">...</span></p>
                <div className="search-result-buttons">
                    <div onClick={this.goToPrev.bind(this)}>PREV</div>
                    <div onClick={this.goToNext.bind(this)}>NEXT</div>
                </div>
            </div>
        </div> : (null) )*/

        return ( this.props.active ? <form className="ui tiny form">
                <div className="find-wrapper">
                    <div className="find-container">
                        <div className="find-container-inside">
                            <div className="find-list">
                                <div className="fields">
                                    <div className="field">
                                        <div>
                                            <label>Source</label>
                                            <input type="text" placeholder="Find in source" />
                                        </div>
                                        <div className="">
                                            <div>
                                                <input type="checkbox"/>
                                                <label>Match Case</label>
                                            </div>
                                            <div>
                                                <input type="checkbox"/>
                                                <label>Hole word</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="field">
                                        <div>
                                            <label>Target</label>
                                            <input type="text" placeholder="Find in target" />
                                            <div>
                                                <input type="checkbox"/>
                                                <label>Replace with</label>
                                            </div>
                                        </div>
                                        <div className="">
                                            <div>
                                                <input type="text" placeholder="Replace in target" />
                                                <button className="ui basic tiny button">Replace</button>
                                                <button className="ui basic tiny button">Replace All</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="field">
                                        <label>Find for</label>
                                        <div className="find-dropdown">
                                            <div className="ui top left pointing dropdown basic tiny button not-filtered">
                                                <div className="text">
                                                    <div>Status Segment</div>
                                                    <div className="ui cancel label"><i className="icon-cancel3" /></div>
                                                </div>
                                                <div className="menu">
                                                    <div className="item">
                                                        <div className="ui gray empty circular label" />
                                                        New
                                                    </div>
                                                    <div className="item">
                                                        <div className="ui black empty circular label" />
                                                        Draft
                                                    </div>
                                                    <div className="item">
                                                        <div className="ui blue empty circular label" />
                                                        Translated
                                                    </div>
                                                    <div className="item">
                                                        <div className="ui green empty circular label" />
                                                        Approved
                                                    </div>
                                                    <div className="item">
                                                        <div className="ui red empty circular label" />
                                                        Rejected
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            {/*<div className="find-actions">
                                <button type="button" className="ui button">Find</button>
                                <button type="button" className="ui button">Clear</button>
                            </div>*/}
                        </div>
                    </div>
                </div>
        </form> : (null) )
    }
}

export default Search;



{/*
<div className="ui form">
    <div className="two fields">
        <div className="field">
            <label>First name</label>
            <input type="text" placeholder="First Name">
        </div>
        <div className="field">
            <label>Middle name</label>
            <input type="text" placeholder="Middle Name">
        </div>
    </div>
</div>*/}
