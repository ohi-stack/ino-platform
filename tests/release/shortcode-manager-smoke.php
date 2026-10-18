<?php
/** Lightweight unit smoke for the read-only INO shortcode inventory. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
class INO_Test_WPDB {
    public $posts='wp_posts';
    public function prepare($sql) { return $sql; }
    public function get_results($sql) {
        return array((object)array('ID'=>23,'post_title'=>'Test Portal','post_name'=>'test-portal',
            'post_status'=>'publish','post_content'=>'[ino_portal]'),
            (object)array('ID'=>24,'post_title'=>'Removed Member Handler',
            'post_name'=>'orphan-shortcode','post_status'=>'draft',
            'post_content'=>'[ino_member_dashboard]'));
    }
}
function shortcode_exists($tag) {
    global $shortcode_tags;
    return isset($shortcode_tags[$tag]);
}
function has_shortcode($content,$tag) {
    return shortcode_exists($tag) && strpos($content,'['.$tag.']')!==false;
}
function expect_ino($predicate,$description) {
    if(!$predicate){fwrite(STDERR,"FAILED: ".$description."\n");exit(1);}
}
$wpdb=new INO_Test_WPDB();
$shortcode_tags=array();
require_once dirname(__DIR__,2).'/includes/class-ino-platform-shortcode-manager.php';
$class='INO_Platform_Shortcode_Manager';
$method=new ReflectionMethod($class,'catalogue');$method->setAccessible(true);
$groups=$method->invoke(null);
$tags=array();$types=array('workflow','interface','informational');
foreach($groups as $name=>$items){
    foreach($items as $tag=>$spec){
        expect_ino(strpos($tag,'ino_')===0,'ino_ prefix for '.$tag);
        expect_ino(!isset($tags[$tag]),'unique shortcode '.$tag);
        expect_ino(in_array($spec[1],$types,true),'maturity for '.$tag);
        expect_ino($spec[0]!=='' && $spec[2]!=='' && $spec[3]!=='','metadata for '.$tag);
        $tags[$tag]=true;
    }
}
expect_ino(count($tags)===39,'exactly 39 known shortcode tags');
foreach(array('ino_family_tree','ino_documents','ino_declarations_affidavits','ino_governance_records','ino_voting') as $tag){
    expect_ino(isset($tags[$tag]),'priority coverage '.$tag);
}
$shortcode_tags['ino_portal']=function(){return 'Never execute callback in diagnostics';};
$shortcode_tags['ino_extra_test']=function(){return 'Runtime discovered extension';};
$inv=new ReflectionMethod($class,'inventory');$inv->setAccessible(true);
$data=$inv->invoke(null);
expect_ino(count($data['rows'])===40,'active extension discovered without manual registration');
expect_ino($data['rows']['ino_portal']['registered']===true,'portal registered');
expect_ino($data['rows']['ino_portal']['callable']===true,'portal callable');
expect_ino($data['rows']['ino_portal']['owner']==='Closure','owner reported without execution');
expect_ino($data['rows']['ino_portal']['overridden']===true,'handler collision reported');
expect_ino(count($data['rows']['ino_portal']['pages'])===1,'page placement observed');
expect_ino($data['rows']['ino_member_dashboard']['registered']===false,'missing handler reported');
expect_ino(count($data['rows']['ino_member_dashboard']['pages'])===1,'orphaned shortcode assignment remains visible after handler removal');
expect_ino($data['rows']['ino_member_dashboard']['pages'][0]['status']==='draft','draft page assignment status visible');
expect_ino($data['rows']['ino_extra_test']['maturity']==='unknown','unknown maturity not certified');
expect_ino($data['partial']===false,'bounded index complete');
echo "INO shortcode manifest and handler/page placement smoke passed.\n";
