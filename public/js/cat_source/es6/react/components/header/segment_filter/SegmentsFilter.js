let CatToolConstants = require('../../../constants/CatToolConstants');
let CatToolStore = require('../../../stores/CatToolStore');

class SegmentsFilter extends React.Component {
    constructor(props) {
        super(props);
        this.moreFilters = [
            {value: 'unlocked', label: 'Unlocked'},
            {value: 'repetitions', label: 'Repetitions'},
            {value: 'mt', label: 'MT'},
            {value: 'matches', label: '100% Matches'},
            // {value: 'fuzzies_50_74', label: 'fuzzies_50_74'},
            {value: 'fuzzies_75_84', label: 'fuzzies_75_84'},
            {value: 'fuzzies_85_94', label: 'fuzzies_85_94'},
            {value: 'fuzzies_95_99', label: 'fuzzies_95_99'},
            {value: 'todo', label: 'Todo'}
        ];
        this.state = this.defaultState();
        this.setFilter = this.setFilter.bind(this);
        this.moreFilterSelectChanged = this.moreFilterSelectChanged.bind(this);
        this.doSubmitFilter = this.doSubmitFilter.bind(this);
        this.dropdownInitialized = false;

    }

    defaultState() {
        // let storedState = SegmentFilter.getStoredState();
        let storedState = {};


        if (storedState.reactState) {
            storedState.reactState.moreFilters = this.moreFilters;
            console.log(storedState.reactState);
            return storedState.reactState;
        }
        else {
            return {
                selectedStatus: '',
                samplingType: '',
                samplingSize: 5,
                filtering: false,
                filteredCount: 0,
                segmentsArray: [],
                moreFilters: this.moreFilters,
                filtersEnabled: true,
                dataSampleEnabled: false,
            }
        }
    }

    resetState() {
        this.setState( this.defaultState() );
    }

    resetStatusFilter() {
        $(this.statusDropdown).dropdown('restore defaults');
    }

    resetMoreFilter() {
        $(this.filtersDropdown).dropdown('restore defaults');
    }

    resetDataSampleFilter() {
        $(this.dataSampleDropDown).dropdown('restore defaults');
    }

    clearClick(e) {
        e.preventDefault();
        SegmentFilter.clearFilter();
        this.resetState();
        $(this.filtersDropdown).dropdown('restore defaults');
        $(this.dataSampleDropDown).dropdown('restore defaults');
        $(this.statusDropdown).dropdown('restore defaults');
        $(this.toggleFilters).checkbox('set unchecked');
    }

    closeClick(e) {
        e.preventDefault();
        SegmentFilter.closeFilter();
    }

    doSubmitFilter(segmentToOpen = null) {
        let sample;
        if ( this.applyFilters ) return; //updating the dropdown
        if (this.state.samplingType) {
            if (this.state.dataSampleEnabled) {
                sample = {
                    type: this.state.samplingType,
                    size: this.state.samplingSize,
                };
            } else {
                sample = {
                    type: this.state.samplingType
                };
            }

        }
        if ( sample || this.state.selectedStatus !== "" ) {
            SegmentFilter.filterSubmit({
                status: this.state.selectedStatus,
                sample: sample,
            },{
                samplingType: this.state.samplingType,
                samplingSize:this.state.samplingSize,
                selectedStatus: this.state.selectedStatus,
                dataSampleEnabled: this.state.dataSampleEnabled
            });
        } else {
            this.setState({
                filtering: false,
            });
            SegmentFilter.clearFilter();
        }
    }

    filterSelectChanged(value) {
        if ( (!config.isReview && value === "TRANSLATED" && this.state.samplingType === "todo") ||
            config.isReview && value === "APPROVED" && this.state.samplingType === "todo" ) {
            this.setState({
                selectedStatus: value,
                samplingType: "",
            });
        } else  {
            this.setState({
                selectedStatus: value,
            });
        }
        setTimeout(this.doSubmitFilter, 100);

    }

    moreFilterSelectChanged(value) {
        if ( (!config.isReview && this.state.selectedStatus === "TRANSLATED" && value === "todo") ||
            config.isReview && this.state.selectedStatus === "APPROVED" && value === "todo" ) {
            this.setState({
                samplingType: value,
                selectedStatus: "",
            });
        } else  {
            this.setState({
                samplingType: value,
            });
        }
        setTimeout( this.doSubmitFilter, 100 );
    }

    dataSampleChange(value) {
        this.setState({
            samplingType: value
        });
        setTimeout( this.doSubmitFilter, 100 );
    }

    // humanSampleType() {
    //     let map = {
    //         'segment_length_high_to_low': 'Segment length (high to low)',
    //         'segment_length_low_to_high': 'Segment length (low to high)',
    //         'regular_intervals': 'Regular intervals',
    //         'edit_distance_high_to_low': 'Edit distance (high to low)',
    //         'edit_distance_low_to_high': 'Edit distance (low to high)'
    //     };
    //
    //     return map[this.state.samplingType];
    // }

    samplingSizeChanged() {
        let value = parseInt(this.sampleSizeInput.value);
        if (value > 100 || value < 1) return false;

        this.setState({
            samplingSize: value,
        });
    }

    moveUp(e) {
        if (this.state.filtering && this.state.filteredCount > 1) {
            UI.gotoPreviousSegment();
        }
    }

    moveDown(e) {
        if (this.state.filtering && this.state.filteredCount > 1) {
            UI.gotoNextSegment();
        }
    }

    selectAllSegments() {
        SegmentActions.setBulkSelectionSegments(this.state.segmentsArray.slice(0));
    }

    setFilter(data, state) {
        if ( _.isUndefined(state) ) {
            this.setState({
                filteredCount: data.count,
                filtering: true,
                segmentsArray: data.segment_ids
            });
        } else {
            this.applyFilters = true;
            state.filteredCount = data.count;
            state.filtering = true;
            state.segmentsArray = data.segment_ids;
            this.setState(state);
            setTimeout(this.updateObjects.bind(this));
        }
    }

    initDropDown() {
        if (this.props.active && !this.dropdownInitialized) {
            this.dropdownInitialized = true;
            let self = this;
            $(this.statusDropdown).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.filterSelectChanged(value);
                }
            });
            $(this.filtersDropdown).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.moreFilterSelectChanged(value);
                }
            });
            $(this.dataSampleDropDown).dropdown({
                onChange: function(value, text, $selectedItem) {
                    self.dataSampleChange(value);
                }
            });
            $(this.toggleFilters).checkbox({
                onChecked: function() {
                    $(self.filtersDropdown).dropdown('restore defaults');
                    self.setState({
                        filtersEnabled: false,
                        dataSampleEnabled: true,
                        samplingType: '',
                    });
                },
                onUnchecked: function() {
                    $(self.dataSampleDropDown).dropdown('restore defaults');
                    self.setState({
                        filtersEnabled: true,
                        dataSampleEnabled: false,
                        samplingType: '',
                    });
                }
            });
        }
    }

    updateObjects() {
        if ( this.applyFilters ) {

            $(this.statusDropdown).dropdown('set selected', this.state.selectedStatus);
            if (!this.state.dataSampleEnabled) {
                $(this.filtersDropdown).dropdown('set selected', this.state.samplingType);
                $(this.toggleFilters).checkbox('set unchecked');
            } else {
                $(this.dataSampleDropDown).dropdown('set selected', this.state.samplingType);
                $(this.toggleFilters).checkbox('set checked');
            }
            this.applyFilters = false;
        }
    }

    componentDidMount() {
        CatToolStore.addListener(CatToolConstants.SET_SEGMENT_FILTER, this.setFilter);
        this.initDropDown();
    }

    componentDidUpdate() {
        this.initDropDown();
    }

    componentWillUnmount() {
        CatToolStore.removeListener(CatToolConstants.SET_SEGMENT_FILTER, this.setFilter);
    }

    render () {
        let buttonArrowsClass = 'qa-arrows-disbled';
        let options = config.searchable_statuses.map(function (item, index) {
            return <div className="item" key={index} data-value={item.value}>
                        <div  className={"ui "+ item.label.toLowerCase() +"-color empty circular label"} />
                            {item.label}
                    </div>;
        });
        let moreOptions = this.state.moreFilters.map(function (item, index) {
            return <div key={index} data-value={item.value} className="item">
                {item.label}
            </div>;
        });

        if (this.state.filtering && this.state.filteredCount > 1) {
            buttonArrowsClass = 'qa-arrows-enabled';
        }

        let filterClassEnabled = (this.state.filtersEnabled) ? "" : "disabled";
        let dataSampleClassEnabled = (this.state.dataSampleEnabled) ? "" : "disabled";
        let statusFilterClass = (this.state.selectedStatus !== "") ? "filtered" : "not-filtered";
        filterClassEnabled = (!this.state.dataSampleEnabled && this.state.samplingType !== "") ? filterClassEnabled + " filtered" :
            filterClassEnabled + " not-filtered";
        dataSampleClassEnabled = (this.state.dataSampleEnabled && this.state.samplingType !== "") ? dataSampleClassEnabled + " filtered" :
            dataSampleClassEnabled + " not-filtered";

        return (this.props.active ? <div className="filter-wrapper">
            <div className="filter-container">
                <div className="filter-container-inside">

                    <div className="filter-list">
                        <div className="filter-dropdown">
                            <div className={"filter-status " + statusFilterClass}>
                                <div className="ui top left pointing dropdown basic tiny button" ref={(dropdown)=>this.statusDropdown=dropdown}>
                                    <div className="text">
                                        <div>Segment Status</div>
                                    </div>
                                    <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetStatusFilter.bind(this)}/></div>
                                    <div className="menu">
                                        {options}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="filter-dropdown">
                            <div className={"filter-activities " + filterClassEnabled} >
                                <div className="ui top left pointing dropdown basic tiny button" ref={(dropdown)=>this.filtersDropdown=dropdown}>
                                    <div className="text">Filters</div>
                                    <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetMoreFilter.bind(this)}/></div>
                                    <div className="menu">
                                        {moreOptions}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {config.isReview ? (

                        <div className="filter-dropdown">
                            <div className="filter-toggle">
                                <div className="ui toggle checkbox" ref={(checkbox)=>this.toggleFilters=checkbox}>
                                    <input type="checkbox" name="public" />
                                </div>
                            </div>
                            <div className={"filter-data-sample " + dataSampleClassEnabled}>
                                <div className="percent-item">
                                    15%
                                </div>
                                <div className="ui top left pointing dropdown basic tiny button" ref={(checkbox)=>this.dataSampleDropDown=checkbox}>
                                    <div className="text">Data Sample</div>
                                    <div className="ui cancel label"><i className="icon-cancel3" onClick={this.resetDataSampleFilter.bind(this)}/></div>
                                    <div className="menu">
                                        <div className="head-dropdown">
                                            <div className="ui mini input">
                                                <label>Sample size <b>(%)</b></label>
                                                <input type="number" placeholder="n°" value={this.state.samplingSize} onChange={this.samplingSizeChanged.bind(this)} ref={(input)=>this.sampleSizeInput=input}/>
                                            </div>
                                        </div>
                                        <div className="divider" />
                                        <div className="item" data-value="edit_distance_high_to_low">
                                            <div className="type-item">Edit distance </div>
                                            <div className="order-item"> (A - Z)</div>
                                        </div>
                                        <div className="item" data-value="edit_distance_low_to_high">
                                            <div className="type-item" >Edit distance</div>
                                            <div className="order-item"> (Z - A)</div>
                                        </div>
                                        <div className="item" data-value="segment_length_high_to_low">
                                            <div className="type-item">Segment length</div>
                                            <div className="order-item"> (A - Z)</div>
                                        </div>
                                        <div className="item" data-value="segment_length_low_to_high">
                                            <div className="type-item">Segment length</div>
                                            <div className="order-item"> (Z - A)</div>
                                        </div>
                                        <div className="item" data-value="regular_intervals">
                                            Regular interval
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ):(null)}
                        {this.state.filtering ? (

                        <div className="clear-filter-element">
                            <div className="clear-filter">
                                <button href="#" onClick={this.clearClick.bind(this)}>Clear all</button>
                            </div>
                            <div className="select-all-filter">
                                <button href="#" ref={(button)=>this.selectAllButton=button} onClick={this.selectAllSegments.bind(this)}>Select All</button>
                            </div>
                        </div>
                        ) : (null)}
                    </div>
                    <div className="filter-navigator">
                        <div className="filter-actions">
                            {this.state.filtering && this.state.filteredCount > 0 ? (
                            <div className={"filter-arrows filter-arrows-enabled " + buttonArrowsClass}>
                                <div className="label-filters labl"><b>{this.state.filteredCount}</b> Filtered segments</div>
                                <button className="filter-move-up ui basic button" onClick={this.moveUp.bind(this)}>
                                    <i className="icon-chevron-left" />
                                </button>
                                <button className="filter-move-up ui basic button" onClick={this.moveDown.bind(this)}>
                                    <i className="icon-chevron-right" />
                                </button>
                            </div>
                            ) : (null)}
                        </div>
                    </div>
                </div>
            </div>
        </div> : (null) )
    }
}

export default SegmentsFilter;
