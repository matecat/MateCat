/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooter extends React.Component {

    constructor(props) {
        super(props);
        this.tabs = [];
        this.state = {
            tabs_labels: [],
            tabs_containers: []
        };
        this.registerTab = this.registerTab.bind(this);
        this.createFooter = this.createFooter.bind(this);
        this.createTabLabels = this.createTabLabels.bind(this);
        this.createTabContainers = this.createTabContainers.bind(this);
        this.changeTab = this.changeTab.bind(this);
    }

    createFooter(sid) {
        if (this.props.sid == sid) {
            var labels = this.createTabLabels();
            var containers = this.createTabContainers();
            this.setState({
                tabs_labels: labels,
                tabs_containers: containers
            });
        }
    }

    createTabLabels() {
        var sid = this.props.sid;
        var enabled = _.select(this.tabs, function(item) {
            return item.is_enabled(sid);
        });
        var tabsSorted = _.sortBy( enabled, 'tab_position');

        var active = this.getFirstTabActive();

        return  _.map( tabsSorted , function(item) {
            var active_class = active.code == item.code ?  'active' : '' ;
            var hidden_class = item.is_hidden( sid ) ? 'hide' : '' ;

            return {
                code: item.code,
                hidden_class : hidden_class,
                active_class : active_class,
                id_segment : sid ,
                tab_markup : item.tab_markup( sid ),
                code : item.code,
                tab_class : item.tab_class
            }
        });
    }

    createTabContainers() {
        var sid = this.props.sid;
        var enabled = _.select(this.tabs, function(item) {
            return item.is_enabled( sid );
        });
        var tabs = _.sortBy( enabled, 'tab_position');

        var active = this.getFirstTabActive();
        return _.map( tabs, function( item ) {
            var active_class =  active.code == item.code ? 'open' : '' ;

            return {
                code: item.code,
                active_class : active_class,
                id_segment : sid ,
                rendered_body : item.content_markup( sid ),
                tab_class : item.tab_class
            };
        });

    }

    getFirstTabActive() {
        // find a list of all enabled ones
        // call a function to determine if they want to be active
        // for those who want to be active
        // sort by activation priority and pick the first

        var active_candidates = _.select(this.tabs, function(item) {
            if ( !item.is_enabled( self ) ) return false;
            if ( item.is_hidden ( self ) ) return false;
            if ( typeof item.is_active == 'function' ) {
                return item.is_active( self );
            }
            return true; // every visible tabs wants to be active by default
        });

        var sorted = _
            .sortBy(active_candidates, 'activation_priority')
            .reverse();

        return _.first( sorted );
    }

    registerTab(tab) {
        // Ensure no duplicates
        var found = _.select(this.tabs, function(item) {
            return item.code == tab.code ;
        });

        if ( found.length ) {
            throw new Error("Trying to register a tab twice", tab);
        }

        this.tabs.push(tab);
    }
    changeTab(e) {
        e.preventDefault();

        var section = $(e.target).closest('section');
        var tab_class = $(e.target).closest('li').data('tab-class');
        var code = $(e.target).closest('li').data('code');
        var li = $(e.target).closest('li');

        $('.sub-editor', section).removeClass('open');
        $('.' + tab_class, section).addClass('open');

        $('.tab-switcher', section).removeClass('active');
        li.addClass('active');

        var item = _
            .chain(this.tabs)
            .select(function(item) { return item.code == code })
            .first()
            .value();

        if ( typeof item.on_activation  == 'function' ) {
            item.on_activation( $(this.footerRef) ) ;
        }
    }
    componentDidMount() {
        console.log("Mount SegmentFooter" + this.props.sid);
        SegmentStore.addListener(SegmentConstants.CREATE_FOOTER, this.createFooter);
        SegmentStore.addListener(SegmentConstants.REGISTER_TAB, this.registerTab);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooter" + this.props.sid);
        SegmentStore.removeListener(SegmentConstants.CREATE_FOOTER, this.createFooter);
        SegmentStore.removeListener(SegmentConstants.REGISTER_TAB, this.registerTab);
    }

    componentWillMount() {

    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var labels = [];
        var containers = [];
        var self = this;
        this.state.tabs_labels.forEach(function (tab) {
            var item = <li
                        key={tab.code}
                        className={tab.hidden_class + " " + tab.active_class + " tab-switcher tab-switcher-"+tab.code}
                        id={"segment-"+tab.id_segment + tab.code}
                        data-tab-class={tab.tab_class}
                        data-code={tab.code}
                        onClick={self.changeTab}>
                        <a tabIndex="-1" href="#">{tab.tab_markup}
                            <span className={"number"}/>
                        </a>
                        </li>;
            labels.push(item);
        });

        this.state.tabs_containers.forEach(function (tab) {
            var item = <div
                            key={"container_" + tab.code}
                            className={"tab sub-editor "+ tab.active_class + " " + tab.tab_class}
                            id={"segment-" + tab.id_segment + " " + tab.tab_class}
                            dangerouslySetInnerHTML={self.allowHTML(tab.rendered_body)}>
                        </div>;
            containers.push(item);
        });

        return (
            <div className="footer toggle"
                 ref={(ref) => this.footerRef = ref}>
                <ul className="submenu">
                    <li className="footerSwitcher"/>
                    {labels}
                </ul>
                {containers}
            </div>
        )
    }
}

export default SegmentFooter;
