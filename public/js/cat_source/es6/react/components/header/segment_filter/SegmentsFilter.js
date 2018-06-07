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


    }

    defaultState() {
        let storedState = SegmentFilter.getStoredState();


        if (storedState.reactState) {
            storedState.reactState.moreFilters = this.moreFilters;
            console.log(storedState.reactState);
            return storedState.reactState;
        }
        else {
            return {
                searchSettingsOpen: false,
                selectedStatus: '',
                samplingEnabled: false,
                samplingType: '',
                samplingSize: null,
                filtering: false,
                filteredCount: 0,
                segmentsArray: [],
                moreFilters: this.moreFilters

            }
        }
    }

    resetState() {
        this.setState(this.defaultState());
    }

    toggleSettings() {
        this.setState({
            searchSettingsOpen: !this.state.searchSettingsOpen
        });
    }

    clearClick(e) {
        e.preventDefault();
        SegmentFilter.clearFilter();
        this.resetState();
    }

    closeClick(e) {
        e.preventDefault();
        SegmentFilter.closeFilter();
    }

    submitClick(e) {
        e.preventDefault();
        this.doSubmitFilter();
    }

    doSubmitFilter(segmentToOpen = null) {
        let sample;

        if (this.state.samplingType) {
            if (this.state.samplingSize) {
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

        SegmentFilter.filterSubmit({
            status: this.state.selectedStatus,
            sample: sample,
        }, segmentToOpen,{
            samplingType: this.state.samplingType,
            samplingSize:this.state.samplingSize,
            selectedStatus: this.state.selectedStatus,
            samplingEnabled: this.state.samplingEnabled,
        });

        this.setState({
            searchSettingsOpen: false
        });
    }

    filterSelectChanged(e) {
        let value = e.target.value;
        if ( (!config.isReview && value === "TRANSLATED" && this.state.samplingType === "todo") ||
            config.isReview && value === "APPROVED" && this.state.samplingType === "todo" ) {
            this.setState({
                selectedStatus: e.target.value,
                samplingType: ""
            });
        } else  {
            this.setState({
                selectedStatus: e.target.value
            });
        }

    }

    moreFilterSelectChanged(e) {
        let value = e.target.value;
        if ( (!config.isReview && this.state.selectedStatus === "TRANSLATED" && value === "todo") ||
            config.isReview && this.state.selectedStatus === "APPROVED" && value === "todo" ) {
            this.setState({
                samplingType: e.target.value,
                selectedStatus: "",
                samplingEnabled: false,
                samplingSize: null
            });
        } else  {
            this.setState({
                samplingType: e.target.value,
                samplingEnabled: false,
                samplingSize: null
            });
        }

    }

    submitEnabled() {
        return this.state.samplingType !== '' || this.state.selectedStatus !== '';
    }

    samplingTypeChecked(e) {
        this.setState({
            samplingType: e.target.value
        });
    }

    samplingEnabledClick(e) {
        let samplingType = '',
            samplingSize = null;

        if (e.target.checked) {
            samplingType = 'edit_distance_high_to_low';
            samplingSize = '5';
        }
        this.setState({
            samplingEnabled: e.target.checked,
            samplingType: samplingType,
            samplingSize: samplingSize
        });
    }

    humanSampleType() {
        let map = {
            'segment_length_high_to_low': 'Segment length (high to low)',
            'segment_length_low_to_high': 'Segment length (low to high)',
            'regular_intervals': 'Regular intervals',
            'edit_distance_high_to_low': 'Edit distance (high to low)',
            'edit_distance_low_to_high': 'Edit distance (low to high)'
        };

        return map[this.state.samplingType];
    }

    samplingSizeChanged(e) {
        let value = parseInt(e.target.value);
        if (value > 100 || value < 1) return false;

        this.setState({
            samplingSize: e.target.value,
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

    setStatusClick(e) {
        e.preventDefault();
        SegmentActions.setBulkSelectionSegments(this.state.segmentsArray.slice(0));
    }

    setFilter(data) {
        this.setState({
            filteredCount: data.count,
            filtering: true,
            segmentsArray: data.segment_ids
        });
    }

    componentDidMount() {
        CatToolStore.addListener(CatToolConstants.SET_SEGMENT_FILTER, this.setFilter);
        let storedState = SegmentFilter.getStoredState();
        if (storedState.reactState) {
            this.doSubmitFilter(storedState.lastSegmentId);
        }

    }

    componentWillUnmount() {
        CatToolStore.removeListener(CatToolConstants.SET_SEGMENT_FILTER, this.setFilter);
    }

    render() {

        let searchSettingsClass = classnames({
            hide: !this.state.searchSettingsOpen,
            'search-settings-panel': true
        });

        let options = config.searchable_statuses.map(function (item, index) {
            return <option key={index} value={item.value}>{item.label}</option>;
        });
        let moreOptions = this.state.moreFilters.map(function (item, index) {
            return <option key={index} value={item.value}>{item.label}</option>;
        });

        let fullOptions = [<option key="" value="">All</option>].concat(options),
            fullMoreOptions = [<option key="" value="">All</option>].concat(moreOptions),
            submitEnabled = this.submitEnabled(),
            filteringInfo,
            navigation,
            currentSampleSettings,
            buttonArrowsClass = 'qa-arrows-disabled';

        if (this.state.filtering && this.state.filteredCount > 1) {
            buttonArrowsClass = 'qa-arrows-enabled';
        }

        navigation = <div className="sf-segment-navigation-arrows">
            <div className={'qa-arrows ' + buttonArrowsClass}>
                <button className="qa-move-up ui basic button" onClick={this.moveUp.bind(this)}>
                    <i className="icon-chevron-left"/>
                </button>
                <button className="qa-move-down ui basic button" onClick={this.moveDown.bind(this)}>
                    <i className="icon-chevron-right"/>
                </button>
            </div>
        </div>;


        if (this.state.filtering) {
            if (this.state.filteredCount > 0) {
                filteringInfo =
                    <div className="block filter-segments-count">{this.state.filteredCount} segments</div>;
            }
            else {
                filteringInfo = <div className="block filter-segments-count">0 segments</div>;
            }

        }

        if (this.state.samplingEnabled) {
            currentSampleSettings = <div className="block">
                <div className="search-settings-info">{this.state.samplingSize}% - {this.humanSampleType()}</div>
                <a className="search-settings"
                   onClick={this.toggleSettings.bind(this)}>Settings</a>
                <div className={searchSettingsClass}>

                    <div>
                        Select the sample size
                        <input type="number" value={this.state.samplingSize} style={{width: '3em'}}
                               onChange={this.samplingSizeChanged.bind(this)}
                               className="advanced-sample-size"/>
                    </div>

                    <h4>Sample criteria</h4>

                    <div className="block">
                        <input onChange={this.samplingTypeChecked.bind(this)}
                               id="sample-edit-distance-high-to-low"
                               checked={this.state.samplingType == 'edit_distance_high_to_low'}
                               value="edit_distance_high_to_low"
                               name="samplingType" type="radio"/><label htmlFor="sample-edit-distance-high-to-low">Edit
                        distance (high to low)</label>
                    </div>

                    <div className="block">
                        <input onChange={this.samplingTypeChecked.bind(this)}
                               id="sample-edit-distance-low-to-high"
                               checked={this.state.samplingType == 'edit_distance_low_to_high'}
                               value="edit_distance_low_to_high"
                               name="samplingType" type="radio"/><label htmlFor="sample-edit-distance-low-to-high">Edit
                        distance (low to high)</label>
                    </div>

                    <div className="block">
                        <input
                            id="sample-segment-length-high-to-low"
                            onChange={this.samplingTypeChecked.bind(this)}
                            checked={this.state.samplingType == 'segment_length_high_to_low'}
                            value="segment_length_high_to_low"
                            name="samplingType" type="radio"/><label htmlFor="sample-segment-length-high-to-low">Segment
                        length (high to low)</label>
                    </div>

                    <div className="block">
                        <input
                            id="sample-segment-length-low-to-high"
                            onChange={this.samplingTypeChecked.bind(this)}
                            checked={this.state.samplingType == 'segment_length_low_to_high'}
                            value="segment_length_low_to_high"
                            name="samplingType" type="radio"/><label htmlFor="sample-segment-length-low-to-high">Segment
                        length (low to high)</label>
                    </div>

                    <div className="block">
                        <input
                            id="sample-regular-intervals"
                            onChange={this.samplingTypeChecked.bind(this)}
                            checked={this.state.samplingType == 'regular_intervals'}
                            value="regular_intervals"
                            name="samplingType" type="radio"/><label htmlFor="sample-regular-intervals">Regular
                        interval</label>
                    </div>

                </div>
            </div>;

        }

        let controlsForSampling;

        if (window.config.isReview) {
            controlsForSampling = <div>
                <div className="block data-sample-checkbox-container">
                    <label htmlFor="data-sample-checkbox">Data sample</label>
                    <input type="checkbox"
                           id="data-sample-checkbox"
                           onClick={this.samplingEnabledClick.bind(this)}
                           checked={this.state.samplingEnabled}/>
                </div>

                {currentSampleSettings}


            </div>;
        }

        return (this.props.active ? <div className="advanced-filter-searchbox searchbox">
            <form>
                <div className="block filter-status-container">
                    <label htmlFor="search-projectname">segment status</label>
                    <select
                        onChange={this.filterSelectChanged.bind(this)}
                        value={this.state.selectedStatus} className="search-select">
                        {fullOptions}
                    </select>
                </div>

                <div className="block filters-container">
                    <label htmlFor="search-projectname">Filters</label>
                    <select
                        onChange={this.moreFilterSelectChanged.bind(this)}
                        value={this.state.samplingType} className="search-select">
                        {fullMoreOptions}
                    </select>
                </div>

                {controlsForSampling}

                <div className="block right">

                    <input onClick={this.setStatusClick.bind(this)} id="setStatus-filter"
                           type="button"
                           className={
                               classnames({
                                   btn: true,
                                   "select-all-filter": true
                               })}
                           disabled={!this.state.filtering}
                           value="Select All"/>

                    <input id="close-filter"
                           type="button"
                           onClick={this.closeClick.bind(this)}
                           className={classnames({btn: true})}
                           value="CLOSE"/>

                    <input id="clear-filter"
                           type="button"
                           onClick={this.clearClick.bind(this)}
                           className={classnames({btn: true})}
                           disabled={!this.state.filtering}
                           value="CLEAR"/>

                    <input onClick={this.submitClick.bind(this)} id="exec-filter"
                           type="submit"
                           className={classnames({btn: true})}
                           disabled={!submitEnabled}
                           value="FILTER"/>
                </div>

            </form>

            {navigation}
            {filteringInfo}

        </div> : (null));
    }
}

export default SegmentsFilter;
