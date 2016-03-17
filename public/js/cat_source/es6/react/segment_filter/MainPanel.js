class MainPanel extends React.Component {
    constructor(props) {
        super(props);


        this.state = this.defaultState();
    }

    defaultState() {
        return {
            searchSettingsOpen : false, 
            selectedStatus : '',
            samplingEnabled : false,
            samplingType : 'edit_distance',
            samplingSize : '10',
            filtering : false,
            filteredCount : 0
        }
    }

    resetState() {
        this.setState( this.defaultState() );
    }

    toggleSettings() {
        this.setState({
            searchSettingsOpen : !this.state.searchSettingsOpen
        }); 
    }

    clearClick(e) {
        e.preventDefault();

        SegmentFilter.closeFilter();
        // TODO
    }

    submitClick(e) {
        e.preventDefault() ;
        let sample  ;

        if ( this.state.samplingEnabled ) {
            sample = {
                type : this.state.samplingType,
                size : this.state.samplingSize,
            }
        }

        SegmentFilter.filterSubmit({
            status : this.state.selectedStatus,
            sample : sample
        });

        this.setState({
            searchSettingsOpen : false
        });
    }

    filterSelectChanged(e) {
        this.setState({
            selectedStatus : e.target.value,
        });
    }

    submitEnabled() {
        return this.state.samplingEnabled || this.state.selectedStatus != '';
    }

    samplingTypeChecked(e) {
        this.setState({
            samplingType : e.target.value
        });
    }

    samplingEnabledClick(e) {
        this.setState({
            samplingEnabled : e.target.checked
        }); 
    }

    humanSampleType() {
        var map = {
            'segment_length' : 'Segment length',
            'regular_intervals' : 'Regular intervals',
            'edit_distance' : 'Edit distance'
        };

        return map[this.state.samplingType];
    }

    samplingSizeChanged(e) {
        this.setState({
            samplingSize : e.target.value,
        });
    }

    render() {

        var searchSettingsClass = classnames({
            hide                    : !this.state.searchSettingsOpen,
            'search-settings-panel' : true
        }); 

        var options = config.searchable_statuses.map(function(item, index) {
            return <option key={index} value={item.value}>{item.label}</option>;
        });

        var fullOptions = [<option key="" value="">All</option>].concat( options );

        var submitEnabled = this.submitEnabled();

        var filteringInfo;
        var currentSampleSettings ;

        if ( this.state.filtering ) {
            if (this.state.filteredCount > 0) {
                filteringInfo = <div className="block filter-segments-count">Showing {this.state.filteredCount} segments</div>;
            }
            else {
                filteringInfo = <div className="block filter-segments-count">No segments matched by this filter</div>;
            }

        }

        if ( this.state.samplingEnabled ) {
            currentSampleSettings = <div className="block">
                    <div className="search-settings-info">{this.state.samplingSize}% - {this.humanSampleType()}</div>
                    <a className="search-settings"
                        onClick={this.toggleSettings.bind(this)}>Settings</a>
                    <div className={searchSettingsClass}>
                        Select the sample size
                        <select value={this.state.samplingSize}
                        onChange={this.samplingSizeChanged.bind(this)}
                            className="advanced-sample-size">
                            <option value="5">5%</option>
                            <option value="10">10%</option>
                            <option value="20">20%</option>
                        </select>
                        <h4>Sample criteria</h4>

                        <div className="block">
                            <input onChange={this.samplingTypeChecked.bind(this)}
                                id="sample-edit-distance"
                                checked={this.state.samplingType == 'edit_distance'}
                                value="edit_distance"
                                name="samplingType" type="radio" /><label htmlFor="sample-edit-distance">Edit distance</label>
                        </div>

                        <div className="block">
                            <input
                                id="sample-regular-intervals"
                                onChange={this.samplingTypeChecked.bind(this)}
                                checked={this.state.samplingType == 'regular_intervals'}
                                value="regular_intervals"
                                name="samplingType" type="radio" /><label htmlFor="sample-regular-intervals">Regular interval</label>
                        </div>

                        <div className="block">
                            <input
                                id="sample-segment-length"
                                onChange={this.samplingTypeChecked.bind(this)}
                                checked={this.state.samplingType == 'segment_length'}
                                value="segment_length"
                                name="samplingType" type="radio" /><label htmlFor="sample-segment-length">Segment length</label>
                        </div>
                    </div>
                </div>;

        }


        return <div className="advanced-filter-searchbox searchbox">
            <form>
                <div className="block">
                    <label htmlFor="search-projectname">segment status</label>
                    <select
                        onChange={this.filterSelectChanged.bind(this)}
                        value={this.state.selectedStatus} className="search-select">
                        {fullOptions}
                    </select>
                </div>

                <div className="block">
                    <label htmlFor="data-sample-checkbox">Data sample</label>
                    <input type="checkbox"
                        id="data-sample-checkbox"
                        onClick={this.samplingEnabledClick.bind(this)}
                        checked={this.state.samplingEnabled} />
                </div>

                {currentSampleSettings}

                

                <div className="block right">
                    <input id="clear-filter"
                        type="button"
                        onClick={this.clearClick.bind(this)}
                        className={classnames({btn: true, disabled: !this.state.filtering})}
                        value="CLEAR" />

                    <input onClick={this.submitClick.bind(this)} id="exec-filter"
                        type="submit"
                            className={classnames({ btn: true, disabled: !submitEnabled})}
                            value="FILTER" />
                </div>
            </form>

            {filteringInfo}

        </div>; 
    }
}

export default MainPanel ;
