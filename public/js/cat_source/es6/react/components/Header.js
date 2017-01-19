
var SubHeader = require("./SubHeader").default;

class Header extends React.Component {
    constructor (props) {
        super(props);
    }
    componentDidMount () {
        $('.team-dropdown').dropdown();
        $('.team-filter.modal')
            .modal('setting', 'transition', 'fade')
            .modal('attach events', '.team-filter.button', 'show')
        ;
    }

    openCreateTeams () {
        ManageActions.openCreateTeamModal();
    }

    openModifyTeam (name) {
        var team = {
            name: name
        };
        ManageActions.openModifyTeamModal(team);
    }


    render () {
        return <section className="nav-mc-bar">
                    <nav role="navigation">
                        <div className="nav-wrapper">
                            <div className="container-fluid">
                                <div className="row">
                                        <a href="/" className="logo logo-col"/>
                                    
                                
                                    {/*<div className="col l4 offset-l4 m4 offset-m4">
                                        <SearchInput
                                            closeSearchCallback={this.props.closeSearchCallback}
                                            onChange={this.props.searchFn}/>
                                    </div>
                                    <div className="col l2 m2 s4 right right-60">
                                        <FilterProjects
                                            filterFunction={this.props.filterFunction}/>
                                    </div>*/}

                                    <div className="col m2 offset-m8">
                                        <div className="ui fluid selection dropdown team-dropdown top-5">
                                            <input type="hidden" name="gender" />
                                            <i className="dropdown icon"/>
                                            <div className="default text">Gender</div>
                                            <div className="menu">
                                                <div className="header">Create Workspace
                                                    <a className="team-filter button show"
                                                        onClick={this.openCreateTeams.bind(this)}>
                                                        <i className="icon-plus3 right"/>
                                                    </a>
                                                </div>
                                                <div className="item" data-value="male" data-text="Personal">Personal

                                                </div>    
                                                <div className="item" data-value="male" data-text="Ebay">Ebay
                                                    <a className="team-filter button show right"
                                                       onClick={this.openModifyTeam.bind(this, 'Ebay')}>
                                                        <i className="icon-more_vert"/>
                                                    </a>
                                                </div>
                                                <div className="item" data-value="female" data-text="MSC">MSC
                                                    <a className="team-filter button show right"
                                                       onClick={this.openModifyTeam.bind(this, 'MSC')}>
                                                        <i className="icon-more_vert"/></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                         
                                        

                                    <div className="col l2 right profile-area">
                                        <ul className="right">
                                            <li>
                                                <a href="" className="right waves-effect waves-light">
                                                    <span id="nome-cognome" className="hide-on-med-and-down">Nome Cognome </span>
                                                    <button className="btn-floating btn-flat waves-effect waves-dark z-depth-0 center hoverable">RS</button>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </nav>
                    <SubHeader
                        filterFunction={this.props.filterFunction}
                        />
                </section>;
    }
}
export default Header ;