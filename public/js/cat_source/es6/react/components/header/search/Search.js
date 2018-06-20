let React = require('react');
let SegmentConstants = require('../../../constants/SegmentConstants');
let CattolConstants = require('../../../constants/CatToolConstants');
let SegmentStore = require('../../../stores/SegmentStore');
let CatToolStore = require('../../../stores/CatToolStore');
let SearchUtils = require('./ui.search');

class Search extends React.Component {

    constructor(props) {
        super(props);
        this.defaultState = {
            isReview: props.isReview,
            searchable_statuses: props.searchable_statuses,
            showReplaceOptionsInSearch: ( !ReviewImproved.enabled() ) || ( ReviewImproved.enabled() && !config.isReview ),
            search: {
                enableReplace: false,
                matchCase: false,
                exactMatch: false,
                replaceTarget: "",
                selectStatus: 'all',
                searchTarget: "",
                searchSource: ""
            },
            focus: true,
            funcFindButton: true, // true=find / false=next
            segments: [],
            total: null,
            searchReturn: false
        };
        this.state = _.cloneDeep(this.defaultState);

        this.handleSubmit = this.handleSubmit.bind(this);
        this.handleCancelClick = this.handleCancelClick.bind(this);
        this.handleInputChange = this.handleInputChange.bind(this);
        this.handleReplaceAllClick = this.handleReplaceAllClick.bind(this);
        this.handleReplaceClick = this.handleReplaceClick.bind(this);
        this.replaceTargetOnFocus = this.replaceTargetOnFocus.bind(this);
        this.dropdownInit = false;
    }

    handleSubmit(event) {
        event.preventDefault();
        if (this.state.funcFindButton) {
            SearchUtils.execFind(this.state.search);
        }
        this.setState({
            funcFindButton: false
        })
    }

    setResults(total, segments) {
        this.setState({
            total: parseInt(total),
            segments: segments,
            searchReturn: true
        });
    }

    goToNext() {
        if (!UI.goingToNext) {
            UI.goingToNext = true;
            SearchUtils.execNext();
        }
    }

    goToPrev() {
        if (!UI.goingToNext) {
            UI.goingToNext = true;
            SearchUtils.execPrev();
        }
    }

    handleCancelClick() {
        this.dropdownInit = false;
        // CatToolActions.closeSubHeader();
        // UI.body.removeClass('searchActive');
        SearchUtils.clearSearchMarkers();
        // UI.enableTagMark();
        // if (UI.segmentIsLoaded(UI.currentSegmentId)) {
        //     UI.gotoOpenSegment();
        // } else {
        //     UI.render({
        //         firstLoad: false,
        //         segmentToOpen: UI.currentSegmentId
        //     });
        // }
        UI.markGlossaryItemsInSource(UI.cachedGlossaryData);
        this.resetStatusFilter();
        setTimeout(() => {
            this.setState(_.cloneDeep(this.defaultState));
        });
    }

    resetStatusFilter() {
        $(this.statusDropDown).dropdown('restore defaults');
    }

    handleReplaceAllClick(event) {
        event.preventDefault();
        let self = this;
        let props = {
            modalName: 'confirmReplace',
            text: 'Do you really want to replace this text in all search results? <br>(The page will be refreshed after confirm)',
            successText: "Continue",
            successCallback: function () {
                SearchUtils.execReplaceAll(self.state.search);
                APP.ModalWindow.onCloseModal();
            },
            cancelText: "Cancel",
            cancelCallback: function () {
                APP.ModalWindow.onCloseModal();
            }

        };
        APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Confirmation required");
    }

    handleReplaceClick() {
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

            SearchUtils.updateSearchDisplayCount(segment);

            if (SearchUtils.numSearchResultsSegments > 1) SearchUtils.gotoNextResultItem(true);
        }
    }

    handleStatusChange(value) {
        let search =  _.cloneDeep(this.state.search);
        search['selectStatus'] = value;
        this.setState({
            search: search,
            funcFindButton: true
        });
    }

    handleInputChange(name, event) {
        //serch model
        const target = event.target;
        const value = target.type === 'checkbox' ? target.checked : target.value;
        let search = this.state.search;
        search[name] = value;

        if ( name !== "enableReplace" ) {
            this.setState({
                search: search,
                funcFindButton: true
            });
        } else {
            this.setState({
                search: search
            });
        }
    }

    replaceTargetOnFocus() {
        let search = this.state.search;
        search.enableReplace = true;
        this.setState({
            search: search
        })
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if(this.props.active){
            $('body').addClass("search-open");
            if(this.sourceEl && this.state.focus){
                this.sourceEl.focus();
                this.setState({
                    focus: false
                });
            }
            let self = this;
            if ( !this.dropdownInit ) {
                this.dropdownInit = true;
                $(this.statusDropDown).dropdown({
                    onChange: function(value, text, $selectedItem) {
                        value = (value === "") ? "all": value;
                        self.handleStatusChange(value)
                    }
                });
            }
        }else{
            $('body').removeClass("search-open");
            if(!this.state.focus){
                this.setState({
                    focus: true
                });
            }
            this.dropdownInit = false;
        }


    }
    getResultsHtml() {
        var html = "";

        //Waiting for results
        if (!this.state.funcFindButton && !this.state.searchReturn) {
            html = <div className="search-display">
                    <p className="searching">Searching ...</p>
                </div>;
        } else if (!this.state.funcFindButton && this.state.searchReturn){

            let query = [];
            if (this.state.search.exactMatch)
                query.push(' exactly');
            if (this.state.search.searchSource)
                query.push(<span className="query"><span className="param">{htmlEncode(this.state.search.searchSource)}</span>in source </span>);
            if (this.state.search.searchTarget)
                query.push(<span className="query"><span className="param">{htmlEncode(this.state.search.searchTarget)}</span>in target </span>);
            if (this.state.search.selectStatus !== 'all') {
                let statusLabel = <span> and status <span className="param">{this.state.search.selectStatus}</span></span>;
                query.push(statusLabel);
            }
            let caseLabel = ' (' + ((this.state.search.matchCase) ? 'case sensitive' : 'case insensitive') + ')';
            query.push(caseLabel);
            let searchMode =(this.state.search.searchSource !== "" && this.state.search.searchTarget !== "") ? 'source&target' : 'normal';
            let numbers = "";
            if (searchMode === 'source&target') {
                let total = this.state.segments.length ? this.state.segments.length : 0;
                let label = (total === 1) ? 'segment' : 'segments';
                numbers =  total > 0 ? (
                    <span className="numbers">Found <span className="segments">{this.state.segments.length}</span> {label}</span>
                ) : (
                    <span className="numbers">No segments found</span>
                    )
            } else {
                let total = this.state.total ? this.state.total : 0;
                let label = (total === 1) ? 'result' : 'results';
                let label2 = (total === 1) ? 'segment' : 'segments';
                numbers =  total > 0 ? (
                    <span className="numbers">Found
                        <span className="results">{' '+this.state.total}</span>{' '}<span>{label}</span>  in
                        <span className="segments">{' '+this.state.segments.length}</span> {' '}<span>{label2}</span>
                    </span>
                ) : (
                    <span className="numbers">No segments found</span>
                )
            }
            html = <div className="search-display">
                        <p className="found">
                            {numbers}
                            {' '}
                            having
                            {query}
                        </p>
                        {this.state.segments.length > 0 ? (
                            <div className="search-result-buttons">
                                <div className="ui basic tiny button" onClick={this.goToPrev.bind(this)}>PREV</div>
                                <div className="ui basic tiny button" onClick={this.goToNext.bind(this)}>NEXT</div>
                            </div>
                        ) : (null) }

                    </div>
        }
        return html;
    }
    escFunction(event){
        if(event.keyCode === 27) {
            this.handleCancelClick();
        }
    }
    componentDidMount(){
        document.addEventListener("keydown", this.escFunction, false);        
        CatToolStore.addListener(CattolConstants.SET_SEARCH_RESULTS, this.setResults.bind(this));

    }
    componentWillUnmount(){
        document.removeEventListener("keydown", this.escFunction, false);
        CatToolStore.removeListener(CattolConstants.SET_SEARCH_RESULTS, this.setResults);
    }

    render() {

        let options = config.searchable_statuses.map(function (item, index) {
            return <div className="item" key={index} data-value={item.value}>
                <div  className={"ui "+ item.label.toLowerCase() +"-color empty circular label"} />
                {item.label}
            </div>;
        });
        let findIsDisabled = true;
        if ( this.state.search.searchTarget !== "" || this.state.search.searchSource !== "") {
            findIsDisabled = false;
        }
        let findButtonClassDisabled = (!this.state.funcFindButton || findIsDisabled) ?  "disabled" : "";
        let statusDropdownClass = (this.state.search.selectStatus !== "" && this.state.search.selectStatus !== "all") ? "filtered" : "not-filtered";
        let statusDropdownDisabled = (this.state.search.searchTarget !== "" || this.state.search.searchSource !== "") ? "" : "disabled";
        let replaceCheckboxClass = (this.state.search.searchTarget) ? "" : "disabled";
        let replaceButtonsClass = (this.state.search.enableReplace && this.state.search.searchTarget && !this.state.funcFindButton) ? "" : "disabled";
        let replaceAllButtonsClass = (this.state.search.enableReplace && this.state.search.searchTarget) ? "" : "disabled";
        return ( this.props.active ? <form className="ui form">
                <div className="find-wrapper">
                    <div className="find-container">
                        <div className="find-container-inside">
                            <div className="find-list">
                                <div className="find-element ui input">
                                    <div className="find-in-source">
                                        <input type="text" value={this.state.search.searchSource} placeholder="Find in source" onChange={this.handleInputChange.bind(this, "searchSource")}/>
                                    </div>
                                    <div className="find-exact-match">
                                        <div className="exact-match">
                                            <input type="checkbox" checked={this.state.search.matchCase} onChange={this.handleInputChange.bind(this, "matchCase")}
                                                   ref={(checkbox)=>this.matchCaseCheck=checkbox}/>
                                            <label> Match Case</label>
                                        </div>
                                        <div className="exact-match">
                                            <input type="checkbox" checked={this.state.search.exactMatch} onChange={this.handleInputChange.bind(this, "exactMatch")}/>
                                            <label> Whole word</label>
                                        </div>
                                    </div>
                                </div>
                                <div className="find-element-container">
                                    <div className="find-element ui input">
                                        <div className="find-in-target">
                                            <input type="text" placeholder="Find in target" value={this.state.search.searchTarget} onChange={this.handleInputChange.bind(this, "searchTarget")}
                                                   className={(!this.state.search.searchTarget && this.state.search.enableReplace ? 'warn' : null)}/>
                                            {this.state.showReplaceOptionsInSearch ?
                                            <div className={"enable-replace-check " + replaceCheckboxClass}>
                                                <input type="checkbox" checked={this.state.search.enableReplace} onChange={this.handleInputChange.bind(this, "enableReplace")}/>
                                                <label> Replace with</label>
                                            </div>
                                            : (null)}
                                        </div>
                                    </div>
                                    {this.state.showReplaceOptionsInSearch && this.state.search.enableReplace ?
                                    <div className="find-element ui input">
                                        <div className="find-in-replace">
                                            <input type="text" placeholder="Replace in target" value={this.state.search.replaceTarget} onChange={this.handleInputChange.bind(this, "replaceTarget")}/>
                                        </div>
                                    </div>
                                    : (null)}
                                </div>
                                <div className="find-element find-dropdown-status">
                                    <div className={"find-dropdown " + statusDropdownClass + " " + statusDropdownDisabled}>
                                        <div className="ui top left pointing dropdown basic tiny button" ref={(dropdown)=>this.statusDropDown=dropdown}>
                                            <div className="text">
                                                <div>Status Segment</div>
                                            </div>
                                            <div className="ui cancel label" onClick={this.resetStatusFilter.bind(this)}><i className="icon-cancel3" /></div>
                                            <div className="menu">
                                                {options}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="find-element find-clear-all">
                                    { !this.state.funcFindButton ? (
                                        <div className="find-clear">
                                            <button type="button" className="" onClick={this.handleCancelClick.bind(this)}>Clear</button>
                                        </div>
                                    ) : (null)}
                                </div>
                            </div>
                            {this.state.showReplaceOptionsInSearch ? (
                                <div className="find-actions">
                                    <button type="button" className={"ui basic tiny button " + findButtonClassDisabled} onClick={this.handleSubmit.bind(this)}>FIND</button>
                                    <button className={"ui basic tiny button " + replaceButtonsClass} onClick={this.handleReplaceClick.bind(this)}>REPLACE</button>
                                    <button className={"ui basic tiny button " + replaceAllButtonsClass} onClick={this.handleReplaceAllClick.bind(this)}>REPLACE ALL</button>
                                </div>
                                ) : (
                                <div className="find-actions">
                                    <button type="button" className={"ui basic tiny button " + findButtonClassDisabled} onClick={this.handleSubmit.bind(this)}>FIND</button>
                                </div>
                                )}
                        </div>
                        {this.getResultsHtml()}
                    </div>
                </div>



        </form> : (null) )
    }
}

export default Search;