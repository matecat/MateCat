<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/manage.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

class changeJobsStatusController extends ajaxcontroller {

    private $res_type;
    private $res_id;
    private $new_status;

    public function __construct() {
        parent::__construct();

        $this->res_type = $this->get_from_get_post('res');
        $this->res_id = $this->get_from_get_post('id');
        $this->new_status = $this->get_from_get_post('new_status');
        $this->undo = $this->get_from_get_post('undo');

        // parameters to select the first item of the next page, to return
        $this->lang_handler = Languages::getInstance();
        if (isset($_POST['page'])) {
            $this->page = ($_POST['page'] == '') ? 1 : $_POST['page'];
        } else {
            $this->page = 1;
        };

        if (isset($_POST['step'])) {
            $this->step = $_POST['step'];
        } else {
            $this->step = 100;
        };

        if (isset($_POST['project'])) {
            $this->project_id = $_POST['project'];
        } else {
            $this->project_id = false;
        };

        if (isset($_POST['only_if'])) {
            $this->only_if = $_POST['only_if'];
        } else {
            $this->only_if = false;
        };

        if (isset($_POST['filter'])) {
            $this->filter_enabled = true;
        } else {
            $this->filter_enabled = false;
        };

        if (isset($_POST['pn'])) {
            $this->search_in_pname = $_POST['pn'];
        } else {
            $this->search_in_pname = false;
        };

        if (isset($_POST['source'])) {
            $this->search_source = $_POST['source'];
        } else {
            $this->search_source = false;
        };

        if (isset($_POST['target'])) {
            $this->search_target = $_POST['target'];
        } else {
            $this->search_target = false;
        };

        if (isset($_POST['status'])) {
            $this->search_status = $_POST['status'];
        } else {
            $this->search_status = 'active';
        };

        if (isset($_POST['onlycompleted'])) {
            $this->search_onlycompleted = $_POST['onlycompleted'];
        } else {
            $this->search_onlycompleted = false;
        }
    }

    public function doAction() {

        if ($this->res_type == "prj") {
            $old_status = getCurrentJobsStatus($this->res_id);
            $strOld = '';
            foreach ($old_status as $item) {
                $strOld .= $item['id'] . ':' . $item['status_owner'] . ',';
            }
            $strOld = trim($strOld, ',');

            $this->result['old_status'] = $strOld;

            $st = updateJobsStatus($this->res_type, $this->res_id, $this->new_status, $this->only_if, $this->undo);

            $start = (($this->page - 1) * $this->step) + $this->step - 1;

            $start = (($this->page - 1) * $this->step) + $this->step - 1;

            $projects = ManageUtils::queryProjects($start, 1, $this->search_in_pname, $this->search_source, $this->search_target, $this->search_status, $this->search_onlycompleted, $this->filter_enabled, $this->lang_handler, $this->project_id);

            $projnum = getProjectsNumber($start, $this->step, $this->search_in_pname, $this->search_source, $this->search_target, $this->search_status, $this->search_onlycompleted, $this->filter_enabled);

            $this->result['code'] = 1;
            $this->result['data'] = "OK";
            $this->result['status'] = $this->new_status;
            $this->result['newItem'] = $projects;
            $this->result['page'] = $this->page;
            $this->result['pnumber'] = $projnum[0]['c'];
        }
    }

}

?>