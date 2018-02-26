var React = require( 'react' );
var SegmentConstants = require( '../../../constants/SegmentConstants' );
var SegmentStore = require( '../../../stores/SegmentStore' );
let SegmentFooterTabIssuesListItem = require( "./SegmentFooterTabIssuesListItem" ).default;

class SegmentFooterTabIssues extends React.Component {

    constructor( props ) {
        super( props );
        this.state = {
            categorySelected: null,
            categoriesIssue: [],
            segment: this.props.segment,
            translation: this.props.segment.translation,
            oldTranslation: this.props.segment.translation,
            isChangedTextarea: true,
            firstSave: true, //because when initialize the component, we receive TRANSLATION_EDITED with same value and the button make green
            issues: []
        }
    }

    componentDidMount() {
        $( this.selectIssueCategory ).dropdown();
        $( this.selectIssueSeverity ).dropdown();

        SegmentStore.addListener( SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.segmentOpened.bind( this ) );
        SegmentStore.addListener( SegmentConstants.TRANSLATION_EDITED, this.trackChanges.bind( this ) );
    }

    componentDidUpdate() {
        $( this.selectIssueSeverity ).dropdown();
        if ( this.state.categorySelected ) {
            $( this.selectIssueCategoryWrapper ).find( '.ui.dropdown' ).removeClass( 'disabled' );
        } else {
            $( this.selectIssueCategoryWrapper ).find( '.ui.dropdown' ).addClass( 'disabled' );
        }
    }

    componentWillUnmount() {
        SegmentStore.removeListener( SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.segmentOpened );
        SegmentStore.removeListener( SegmentConstants.TRANSLATION_EDITED, this.trackChanges );
    }


    trackChanges( sid, editareaText ) {
        let text = htmlEncode( UI.prepareTextToSend( editareaText ) );
        if ( this.state.segment.sid === sid && this.state.oldTranslation !== text || this.state.firstSave) {
            UI.setDisabledOfButtonApproved(this.props.id_segment, true);
            this.setState( {
                translation: text,
                isChangedTextarea: true
            } );
        } else {
            UI.setDisabledOfButtonApproved(this.props.id_segment);
            this.setState( {
                isChangedTextarea: false
            } );
        }
    }

    segmentOpened( sid, segment ) {
        let issues = [];
        segment.versions.forEach( function ( version ) {
            if ( !_.isEmpty( version.issues ) ) {
                issues = issues.concat( version.issues );
            }
        } );
        this.setState( {
            segment: segment,
            issues: issues
        } );
    }

    componentWillMount() {
        let categories = JSON.parse( config.lqa_nested_categories ).categories;
        this.setState( {
            categoriesIssue: categories
        } )
    }

    allowHTML( string ) {
        return {__html: string};
    }

    sendIssue( category, severity ) {

        let data = [];
        let deferred = $.Deferred();
        let self = this,
            firstSave = true,
            oldTranslation = this.state.oldTranslation;

        let issue = {
            'id_category': category.id,
            'severity': severity,
            'version': this.props.segment.version_number,
            'start_node': 0,
            'start_offset': 0,
            'send_node': 0,
            'end_offset': 0
        };


        if ( this.state.isChangedTextarea ) {
            let segment = this.props.segment;
            segment.translation = this.state.translation;
            segment.status = 'approved';
            API.SEGMENT.setTranslation( segment )
                .done( function ( response ) {
                    issue.version = response.translation.version;
                    oldTranslation = response.translation.translation;
                    firstSave = false;
                    console.log( response );
                    deferred.resolve();
                } )
                .fail( /*self.handleFail.bind(self)*/ );
        } else {
            deferred.resolve();
        }

        data.push( issue );

        deferred.then( function () {
            SegmentActions.removeClassToSegment( self.props.id_segment, "modified" );
            UI.currentSegment.data( 'modified', false );
            SegmentActions.submitIssue( self.props.id_segment, data, [] )
                .done( response => {
                    self.setState( {
                        isChangedTextarea: false,
                        oldTranslation: oldTranslation,
                        firstSave: firstSave
                    } );
                    $( self.selectIssueSeverity ).dropdown( 'set selected', -1 );
                    UI.setDisabledOfButtonApproved(self.props.id_segment);
                } )
                .fail( /* self.handleFail.bind(self)*/ );
        } );

    }

    issueCategories() {
        return JSON.parse( config.lqa_nested_categories ).categories;
    }

    categoryOptionChange( e ) {
        let currentCategory = this.issueCategories().find( category => {return category.id == e.target.value} );
        this.setState( {
            categorySelected: currentCategory
        } );
    }

    severityOptionChange( e ) {
        let selectedSeverity = e.target.value;
        console.log( selectedSeverity );
        this.sendIssue( this.state.categorySelected, selectedSeverity )
    }

    findCategory( id ) {
        return this.state.categoriesIssue.find( category => {
            return id == category.id
        } )
    }

    render() {
        let categoryOptions = [],
            categorySeverities = [],
            categoryOption,
            severityOption,
            issues = [],
            severitySelect,
            issue,
            self = this;

        this.state.categoriesIssue.forEach( function ( category, i ) {
            categoryOption = <option value={category.id} key={i} selected={self.state.categorySelected && category.id === self.state.categorySelected.id}>{category.label}</option>;
            categoryOptions.push( categoryOption );
        } );

        if ( this.state.categorySelected ) {
            this.state.categorySelected.severities.forEach( ( severity, i ) => {
                severityOption = <option value={severity.label} key={i}>{severity.label}</option>;
                categorySeverities.push( severityOption );
            } );
        }
        severitySelect =
            <select className="ui fluid dropdown" ref={( input ) => { this.selectIssueSeverity = input;}} onChange={( e ) => this.severityOptionChange( e )} disabled={!this.state.categorySelected}>
                <option value="-1">Select severity</option>
                {categorySeverities}
            </select>;

        this.state.issues.forEach( ( e, i ) => {
            issue = <SegmentFooterTabIssuesListItem key={i} issue={e} categories={this.state.categoriesIssue}/>;
            issues.push( issue );
        } );

        return <div key={"container_" + this.props.code}
                    className={"tab sub-editor " + this.props.active_class + " " + this.props.tab_class}
                    id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
            <div className="ui grid border-box">
                <div className="height wide column">
                    <div className="creation-issue-container ui form">
                        <div className="ui grid">
                            <div className="height wide column">
                                <div className="select-category">
                                    <div className="field">
                                        <select className="ui fluid dropdown" ref={( input ) => { this.selectIssueCategory = input;}} onChange={( e ) => this.categoryOptionChange( e )}>
                                            <option value="-1">Select issue</option>
                                            {categoryOptions}
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div className="height wide column">
                                <div className="select-severity">
                                    <div className="field" ref={( input ) => { this.selectIssueCategoryWrapper = input;}}>
                                        {severitySelect}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="height wide column">
                    <div className="issues-list">
                        {issues}
                    </div>
                </div>
            </div>

        </div>
    }
}

export default SegmentFooterTabIssues;