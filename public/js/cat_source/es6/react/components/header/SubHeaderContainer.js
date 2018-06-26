let React = require('react');
let CatToolConstants = require('../../constants/CatToolConstants');
let CatToolStore = require('../../stores/CatToolStore');
let SegmentSelectionPanel = require('./bulk_selection_bar/BulkSelectionBar').default ;
let Search = require('./search/Search').default;
let QaComponent = require('./QAComponent').default;
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');

class SubHeaderContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            selectionBar: true,
            search: false,
            segmentFilter: false,
            qaComponent: false,
            totalWarnings: 0,
            warnings: {
                ERROR: {
                    Categories: {},
                    total: 0
                },
                WARNING: {
                    Categories: {},
                    total: 0
                },
                INFO: {
                    Categories: {},
                    total: 0
                }
            }

        };
        this.closeSubHeader = this.closeSubHeader.bind(this);
        this.toggleContainer = this.toggleContainer.bind(this);
        this.showContainer = this.showContainer.bind(this);
        this.receiveGlobalWarnings = this.receiveGlobalWarnings.bind(this);
    }
    showContainer(container) {
        switch(container) {
            case 'search':
                this.setState({
                    search: true,
                    segmentFilter: false,
                    qaComponent: false
                });
                break;
            case 'segmentFilter':
                this.setState({
                    search: false,
                    segmentFilter: true,
                    qaComponent: false
                });
                break;
            case 'qaComponent':
                this.setState({
                    search: false,
                    segmentFilter: false,
                    qaComponent: true
                });
                break;
        }
    }
    toggleContainer(container) {
        switch(container) {
            case 'search':
                this.setState({
                    search: !this.state.search,
                    segmentFilter: false,
                    qaComponent: false
                });
                break;
            case 'segmentFilter':
                this.setState({
                    search: false,
                    segmentFilter: !this.state.segmentFilter,
                    qaComponent: false
                });
                break;
            case 'qaComponent':
                this.setState({
                    search: false,
                    segmentFilter: false,
                    qaComponent: !this.state.qaComponent
                });
                break;
        }
    }
    updateIcon(total,warnings) {
        if (total > 0) {
            if(warnings.ERROR.total>0){
                $('#notifbox').attr('class', 'warningbox')
                    .attr("title", "Click to see the segments with potential issues")
                    .find('.numbererror')
                    .text(total)
                    .removeClass('numberwarning numberinfo');
            }else if(warnings.WARNING.total>0){
                $('#notifbox').attr('class', 'warningbox')
                    .attr("title", "Click to see the segments with potential issues")
                    .find('.numbererror')
                    .text(total)
                    .addClass('numberwarning')
                    .removeClass('numberinfo');
            }else{
                $('#notifbox').attr('class', 'warningbox')
                    .attr("title", "Click to see the segments with potential issues")
                    .find('.numbererror')
                    .text(total)
                    .addClass('numberinfo')
                    .removeClass('numberwarning');
            }

        } else {
            $('#notifbox').attr('class', 'notific').attr("title", "Well done, no errors found!").find('.numbererror').text('')
        }
    }
    closeSubHeader() {
        this.setState({
            search: false,
            segmentFilter: false,
            qaComponent: false
        });
    }
    componentDidMount() {
        CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, this.showContainer);
        CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, this.toggleContainer);
        CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, this.closeSubHeader);
        SegmentStore.addListener(SegmentConstants.UPDATE_GLOBAL_WARNINGS, this.receiveGlobalWarnings);
    }

    componentWillUnmount() {
        CatToolStore.removeListener(CatToolConstants.SHOW_CONTAINER, this.showContainer);
        CatToolStore.removeListener(CatToolConstants.TOGGLE_CONTAINER, this.toggleContainer);
        CatToolStore.removeListener(CatToolConstants.CLOSE_SUBHEADER, this.closeSubHeader);
        SegmentStore.removeListener(SegmentConstants.UPDATE_GLOBAL_WARNINGS, this.receiveGlobalWarnings)
    }

    receiveGlobalWarnings(warnings) {
        let totalWarnings = [];
        if (warnings.lexiqa && warnings.lexiqa.length > 0) {
            warnings.matecat.INFO.Categories['lexiqa'] = warnings.lexiqa;
        }
        Object.keys(warnings.matecat).map(key => {
            let totalCategoryWarnings = [];
            Object.keys(warnings.matecat[key].Categories).map(key2 => {
                totalCategoryWarnings.push(...warnings.matecat[key].Categories[key2]);
                totalWarnings.push(...warnings.matecat[key].Categories[key2]);
            });
            warnings.matecat[key].total = totalCategoryWarnings.filter((value, index, self) => {
                return self.indexOf(value) === index;
            }).length
        });
        let tot = totalWarnings.filter((value, index, self) => {
            return self.indexOf(value) === index;
        }).length;
        this.updateIcon(tot,warnings.matecat);
        this.setState({
            warnings: warnings.matecat,
            totalWarnings: tot
        })
    }


    render() {
        return <div>
            <Search
                active={this.state.search}
                isReview={config.isReview}
                searchable_statuses ={config.searchable_statuses}
            />
            { this.props.filtersEnabled ? (
                <SegmentFilter
                    active={this.state.segmentFilter}
                    isReview={config.isReview}
                />
            ) : (null) }
            <QaComponent
                active={this.state.qaComponent}
                isReview={config.isReview}
                warnings={this.state.warnings}
                totalWarnings={this.state.totalWarnings}
            />
            <SegmentSelectionPanel
                active={this.state.selectionBar}
                isReview={config.isReview}
            />
        </div>
    }
}

export default SubHeaderContainer;