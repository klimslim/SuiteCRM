<?php
/**
 * Advanced OpenWorkflow, Automating SugarCRM.
 * @package Advanced OpenWorkflow for SugarCRM
 * @copyright SalesAgility Ltd http://www.salesagility.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU AFFERO GENERAL PUBLIC LICENSE
 * along with this program; if not, see http://www.gnu.org/licenses
 * or write to the Free Software Foundation,Inc., 51 Franklin Street,
 * Fifth Floor, Boston, MA 02110-1301  USA
 *
 * @author SalesAgility <info@salesagility.com>
 */


require_once('modules/AOW_Actions/actions/actionBase.php');
class actionCreateRecord extends actionBase {

    function actionCreateRecord($id = ''){
        parent::actionBase($id);
    }

    function loadJS(){

        return array('modules/AOW_Actions/actions/actionCreateRecord.js');
    }

    function edit_display($line,SugarBean $bean = null, $params = array()){
        global $app_list_strings;

        $modules = $app_list_strings['aow_moduleList'];

        $html = "<table border='0' cellpadding='0' cellspacing='0' width='100%'>";
        $html .= "<tr>";
        $html .= '<td id="name_label" scope="row" valign="top">'.translate("LBL_RECORD_TYPE","AOW_Actions").':<span class="required">*</span>&nbsp;:&nbsp;';
        $html .= "<select name='aow_actions_param[".$line."][record_type]' id='aow_actions_param_record_type".$line."'  onchange='show_crModuleFields($line);'>".get_select_options_with_id($modules, $params['record_type'])."</select></td>";
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= '<td colspan="4" scope="row"><table id="crLine'.$line.'_table" width="100%"></table></td>';
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= '<td colspan="4" scope="row"><input type="button" tabindex="116" style="display:none" class="button" value="'.translate("LBL_ADD_FIELD","AOW_Actions").'" id="addcrline'.$line.'" onclick="add_crLine('.$line.')" /></td>';
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= '<td colspan="4" scope="row"><table id="crRelLine'.$line.'_table" width="100%"></table></td>';
        $html .= "</tr>";
        $html .= "<tr>";
        $html .= '<td colspan="4" scope="row"><input type="button" tabindex="116" style="display:none" class="button" value="'.translate("LBL_ADD_RELATIONSHIP","AOW_Actions").'" id="addcrrelline'.$line.'" onclick="add_crRelLine('.$line.')" /></td>';
        $html .= "</tr>";


        if(isset($params['record_type']) && $params['record_type'] != ''){
            require_once("modules/AOW_WorkFlow/aow_utils.php");
            $html .= "<script id ='aow_script".$line."'>";
            $html .= "cr_fields[".$line."] = \"".trim(preg_replace('/\s+/', ' ', getModuleFields($params['record_type'])))."\";";
            $html .= "cr_relationships[".$line."] = \"".trim(preg_replace('/\s+/', ' ', getModuleRelationships($params['record_type'])))."\";";
            $html .= "cr_module[".$line."] = \"".$params['record_type']."\";";
            if(isset($params['field'])){
                foreach($params['field'] as $key => $field){
                    if(is_array($params['value'][$key]))$params['value'][$key] = json_encode($params['value'][$key]);

                    $html .= "load_crline('".$line."','".$field."','".$params['value'][$key]."','".$params['value_type'][$key]."');";
                }
            }
            if(isset($params['rel'])){
                foreach($params['rel'] as $key => $field){
                    if(is_array($params['rel_value'][$key]))$params['rel_value'][$key] = json_encode($params['rel_value'][$key]);

                    $html .= "load_crrelline('".$line."','".$field."','".$params['rel_value'][$key]."','".$params['rel_value_type'][$key]."');";
                }
            }
            $html .= "</script>";
        }
        return $html;

    }

    function run_action(SugarBean $bean, $params = array()){
        global $beanList;

        if(isset($params['record_type']) && $params['record_type'] != ''){
            if($beanList[$params['record_type']]){
                $record = new $beanList[$params['record_type']]();
                $this->set_record($record, $bean, $params);
                $this->set_relationships($record, $bean, $params);

                require_once('modules/Relationships/Relationship.php');
                $key = Relationship::retrieve_by_modules($bean->module_dir, $record->module_dir, $GLOBALS['db']);
                if (!empty($key)) {
                    foreach($bean->field_defs as $field=>$def){
                        if($def['type'] == 'link' && !empty($def['relationship']) && $def['relationship'] == $key){
                            $bean->load_relationship($field);
                            $bean->$field->add($record->id);
                            break;
                        }
                    }
                }

                return true;
            }
        }
        return false;
    }

    function set_record(SugarBean $record, SugarBean $bean, $params = array()){
        global $app_list_strings, $timedate;

        $record_vardefs = $record->getFieldDefinitions();

        if(isset($params['field'])){
            foreach($params['field'] as $key => $field){

                switch($params['value_type'][$key]) {
                    case 'Field':
                        $data = $bean->field_defs[$params['value'][$key]];

                        if($data['type'] == 'relate' && isset($data['id_name'])) {
                            $params['value'][$key] = $data['id_name'];
                        }
                        $value = $bean->$params['value'][$key];
                        break;
                    case 'Date':
                        switch($params['value'][$key][3]) {
                            case 'business_hours';
                                if(file_exists('modules/AOBH_BusinessHours/AOBH_BusinessHours.php')){
                                    require_once('modules/AOBH_BusinessHours/AOBH_BusinessHours.php');

                                    $businessHours = new AOBH_BusinessHours();

                                    $dateToUse = $params['value'][$key][0];
                                    $sign = $params['value'][$key][1];
                                    $amount = $params['value'][$key][2];

                                    if($sign != "plus"){
                                        $amount = 0-$amount;
                                    }
                                    if($dateToUse == "now"){
                                        $value = $businessHours->addBusinessHours($amount);
                                    }else if($dateToUse == "field"){
                                        $dateToUse = $params['field'][$key];
                                        $value = $businessHours->addBusinessHours($amount, $timedate->fromDb($bean->$dateToUse));
                                    }else{
                                        $value = $businessHours->addBusinessHours($amount, $timedate->fromDb($bean->$dateToUse));
                                    }
                                    $value = $timedate->asDb( $value );
                                    break;
                                }
                                $params['value'][$key][3] = 'hours';
                            //No business hours module found - fall through.
                            default:
                                if($params['value'][$key][0] == 'now'){
                                    $date = gmdate('Y-m-d H:i:s');
                                } else if($params['value'][$key][0] == 'field'){
                                    $date = $record->$params['field'][$key];
                                } else {
                                    $date = $bean->$params['value'][$key][0];
                                }

                                if($params['value'][$key][1] != 'now'){
                                    $value = date('Y-m-d H:i:s', strtotime($date . ' '.$app_list_strings['aow_date_operator'][$params['value'][$key][1]].$params['value'][$key][2].' '.$params['value'][$key][3]));
                                } else {
                                    $value = date('Y-m-d H:i:s', strtotime($date));
                                }
                                break;
                        }
                        break;
                    Case 'Round_Robin':
                    Case 'Least_Busy':
                    Case 'Random':
                        switch($params['value'][$key][0]) {
                            Case 'security_group':
                                if(file_exists('modules/SecurityGroups/SecurityGroup.php')){
                                    require_once('modules/SecurityGroups/SecurityGroup.php');
                                    $security_group = new SecurityGroup();
                                    $security_group->retrieve($params['value'][$key][1]);
                                    $group_users = $security_group->get_linked_beans( 'users','User');
                                    $users = array();
                                    $r_users = array();
                                    if($params['value'][$key][2] != ''){
                                        require_once('modules/ACLRoles/ACLRole.php');
                                        $role = new ACLRole();
                                        $role->retrieve($params['value'][$key][2]);
                                        $role_users = $role->get_linked_beans( 'users','User');
                                        foreach($role_users as $role_user){
                                            $r_users[$role_user->id] = $role_user->name;
                                        }
                                    }
                                    foreach($group_users as $group_user){
                                        if($params['value'][$key][2] != '' && !isset($r_users[$group_user->id])){
                                            continue;
                                        }
                                        $users[$group_user->id] = $group_user->name;
                                    }
                                    break;
                                }
                            //No Security Group module found - fall through.
                            Case 'role':
                                require_once('modules/ACLRoles/ACLRole.php');
                                $role = new ACLRole();
                                $role->retrieve($params['value'][$key][2]);
                                $role_users = $role->get_linked_beans( 'users','User');
                                $users = array();
                                foreach($role_users as $role_user){
                                    $users[$role_user->id] = $role_user->name;
                                }
                                break;
                            Case 'all':
                            default:
                                $users = get_user_array(false);
                                break;
                        }

                        // format the users array
                        $users = array_values(array_flip($users));

                        if(empty($users)){
                            $value = '';
                        }else if (sizeof($users) == 1) {
                            $value = $users[0];
                        } else {
                            switch($params['value_type'][$key]) {
                                Case 'Round_Robin':
                                    $value = getRoundRobinUser($users, $this->id);
                                    break;
                                Case 'Least_Busy':
                                    $user_id = 'assigned_user_id';
                                    if(isset($record_vardefs[$field]['id_name']) && $record_vardefs[$field]['id_name'] != ''){
                                        $user_id = $record_vardefs[$field]['id_name'];
                                    }
                                    $value = getLeastBusyUser($users, $user_id, $record);
                                    break;
                                Case 'Random':
                                default:
                                    shuffle($users);
                                    $value = $users[0];
                                    break;
                            }
                        }
                        setLastUser($value, $this->id);

                        break;
                    case 'Value':
                    default:
                        $value = $params['value'][$key];
                        break;
                }

                if($record_vardefs[$field]['type'] == 'relate' && isset($record_vardefs[$field]['id_name'])) {
                    $field = $record_vardefs[$field]['id_name'];
                }
                $record->$field = $value;
            }
        }
        $record->process_save_dates =false;

        $check_notify = $record->assigned_user_id != $record->fetched_row['assigned_user_id'];

        $record->save($check_notify);
    }

    function set_relationships(SugarBean $record, SugarBean $bean, $params = array()){

        $record_vardefs = $record->getFieldDefinitions();

        require_once('modules/Relationships/Relationship.php');
        if(isset($params['rel'])){
            foreach($params['rel'] as $key => $field){
                $def = $record_vardefs[$field];
                if($def['type'] == 'link' && !empty($def['relationship'])){
                    $record->load_relationship($field);
                    $record->$field->add($params['rel_value'][$key]);
                }
            }
        }
    }


}