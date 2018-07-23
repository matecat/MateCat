
class HeaderJobInfo extends React.Component {

    render () {
        return <section className="row sub-head">
            <div className="ui grid">
                <div className="eight wide column">
                    <div className="ui right labeled fluid input search-state-filters">
                        Job Name
                    </div>
                </div>

                <div className="eight wide column pad-right-0">
                    <div> Job Team</div>
                    <div> Job Assignee</div>
                </div>
            </div>
        </section>
    }
}

export default HeaderJobInfo ;