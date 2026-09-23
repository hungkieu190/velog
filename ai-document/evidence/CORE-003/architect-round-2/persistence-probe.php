<?php
/** Mocked storage fault probe. No WordPress bootstrap or database access. */
define('ABSPATH', '/unused-review-fixture/');
require getcwd() . '/src/Core/Capabilities.php';
class ProbeDb {
 public $options='probe_options';
 public array $rows=[];
 public function prepare($query,$key) {return $key;}
 public function get_row($key) {return isset($this->rows[$key])?(object)$this->rows[$key]:null;}
 public function update($table,$values,$where) {$this->rows[$where['option_name']]=$values;return 1;}
}
class ProbeRoles {
 public $role_key='probe_user_roles';
 public array $roles=[];
 public function for_site() {}
}
class ProbeRole {
 public function __construct(public string $name,public array $capabilities) {}
 public function has_cap($cap) {return !empty($this->capabilities[$cap]);}
 public function add_cap($cap) {
  $this->capabilities[$cap]=true;
  // Simulate the final role-option write failing after earlier writes succeeded.
  if($this->name==='mf_velog_technician' && $cap==='mf_velog_finalize_own_services') return;
  probe_persist_roles();
 }
}
$wpdb=new ProbeDb();$probe_roles=new ProbeRoles();
$probe_roles->roles['administrator']=new ProbeRole('administrator',['read'=>true]);
function probe_persist_roles() {
 global $wpdb,$probe_roles;
 $data=[];foreach($probe_roles->roles as $name=>$role) $data[$name]=['name'=>$name,'capabilities'=>$role->capabilities];
 $wpdb->rows[$probe_roles->role_key]=['option_value'=>serialize($data),'autoload'=>'yes'];
}
function get_role($name) {global $probe_roles;return $probe_roles->roles[$name]??null;}
function wp_roles() {global $probe_roles;return $probe_roles;}
function add_role($name,$label,$caps) {global $probe_roles;$role=new ProbeRole($name,$caps);$probe_roles->roles[$name]=$role;probe_persist_roles();return $role;}
function get_option($name) {global $wpdb;return isset($wpdb->rows[$name])?unserialize($wpdb->rows[$name]['option_value']):false;}
function update_option($name,$value,$autoload=false) {global $wpdb;$wpdb->rows[$name]=['option_value'=>serialize($value),'autoload'=>$autoload?'yes':'no'];return true;}
function delete_option($name) {global $wpdb;unset($wpdb->rows[$name]);}
function wp_cache_delete($key,$group) {}
function __($text,$domain) {return $text;}
probe_persist_roles();
$result=MF\VeLog\Core\Capabilities::install();
$stored=get_option($probe_roles->role_key);
echo json_encode(['mocked_probe'=>true,'install_return'=>$result,'persisted_final_cap'=>$stored['mf_velog_technician']['capabilities']['mf_velog_finalize_own_services']??false,'schema'=>get_option('mf_velog_capability_schema_version')],JSON_PRETTY_PRINT)."\n";
